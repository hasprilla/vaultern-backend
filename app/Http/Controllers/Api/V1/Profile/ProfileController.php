<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Profile;

use App\Application\Auth\DeviceRegistrationService;
use App\Application\Profile\Actions\ChangePasswordAction;
use App\Application\Profile\Actions\DeactivateAccountAction;
use App\Application\Profile\Actions\DeleteAccountAction;
use App\Application\Profile\Actions\DisableMfaAction;
use App\Application\Profile\Actions\EnableMfaAction;
use App\Application\Profile\Actions\ReactivateAccountAction;
use App\Application\Profile\Actions\SetupMfaAction;
use App\Application\Profile\Actions\UpdateAvatarAction;
use App\Application\Profile\Actions\UpdateNotificationPreferencesAction;
use App\Application\Profile\Actions\UpdateProfileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Profile\ChangePasswordRequest;
use App\Http\Requests\Api\V1\Profile\ConfirmPasswordRequest;
use App\Http\Requests\Api\V1\Profile\ReactivateAccountRequest;
use App\Http\Requests\Api\V1\Profile\UpdateFcmTokenRequest;
use App\Http\Requests\Api\V1\Profile\UpdateNotificationPreferencesRequest;
use App\Http\Requests\Api\V1\Profile\UpdateProfileRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Device;
use App\Models\Family;
use App\Models\User;
use App\Services\PlanFeatureService;
use App\Support\NotificationPreferences;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    public function __construct(
        private readonly PlanFeatureService $planFeatures,
        private readonly UpdateProfileAction $updateProfile,
        private readonly UpdateAvatarAction $updateAvatarAction,
        private readonly ChangePasswordAction $changePasswordAction,
        private readonly SetupMfaAction $setupMfaAction,
        private readonly EnableMfaAction $enableMfaAction,
        private readonly DisableMfaAction $disableMfaAction,
        private readonly UpdateNotificationPreferencesAction $updateNotificationPreferencesAction,
        private readonly DeactivateAccountAction $deactivateAccount,
        private readonly ReactivateAccountAction $reactivateAccount,
        private readonly DeleteAccountAction $deleteAccount,
    ) {}

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->updateProfile->execute($request->user(), $request->validated());

        return response()->json([
            'message' => 'Perfil actualizado.',
            'data' => new UserResource($user),
        ]);
    }

    public function updateAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:5120'],
        ]);

        $result = $this->updateAvatarAction->execute($request->user(), $request->file('avatar'));

        if (($result['ok'] ?? false) !== true) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        return response()->json([
            'message' => 'Foto de perfil actualizada.',
            'data' => new UserResource($result['user']),
        ]);
    }

    /**
     * Sirve el avatar desde el disco `public` (no depende del symlink web).
     */
    public function showAvatar(Request $request, string $user): StreamedResponse|JsonResponse
    {
        $actor = $request->user();
        $model = User::query()->findOrFail($user);

        $sameFamily = $actor->family_id !== null
            && $model->family_id !== null
            && (string) $actor->family_id === (string) $model->family_id;

        if ((string) $actor->id !== (string) $model->id && ! $sameFamily) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $path = $model->avatar;
        if ($path === null || $path === '') {
            return response()->json(['message' => 'Sin foto de perfil.'], 404);
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return response()->json(['message' => 'Avatar externo no soportado aquí.'], 404);
        }

        if (! Storage::disk('public')->exists($path)) {
            return response()->json(['message' => 'Archivo de avatar no encontrado.'], 404);
        }

        return Storage::disk('public')->response($path, null, [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->changePasswordAction->execute(
            $request->user(),
            $request->validated('password'),
        );

        return response()->json([
            'message' => 'Contraseña actualizada correctamente.',
        ]);
    }

    public function updateFcmToken(UpdateFcmTokenRequest $request): JsonResponse
    {
        $user = $request->user();
        $token = $request->validated('fcm_token');

        $query = Device::query()->where('user_id', $user->id);

        if ($user->device_fingerprint) {
            $query->where('device_fingerprint', $user->device_fingerprint);
        }

        $updated = $query->orderByDesc('last_seen_at')->limit(1)->update([
            'fcm_token' => $token,
            'last_seen_at' => now(),
        ]);

        if ($updated === 0 && $user->device_fingerprint) {
            app(DeviceRegistrationService::class)->register(
                $user,
                $user->device_fingerprint,
                null,
                $token,
            );
        }

        return response()->json(['message' => 'Token FCM actualizado.']);
    }

    public function planUsage(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->family_id === null) {
            return response()->json(['data' => []]);
        }

        $family = Family::query()->findOrFail($user->family_id);
        $features = $this->planFeatures->featuresForFamily($family);
        $ocrLimit = $this->planFeatures->familyFeatureLimit($family, 'ocr_scans_monthly', 5);
        $ocrUsed = \App\Models\OcrJob::query()
            ->where('family_id', $family->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
        $childrenCount = User::query()
            ->where('family_id', $family->id)
            ->where('role', 'hijo')
            ->count();

        return response()->json([
            'data' => [
                'plan_code' => $family->activePlanCode(),
                'features' => $features,
                'ocr' => [
                    'used' => $ocrUsed,
                    'limit' => $ocrLimit,
                    'remaining' => max(0, $ocrLimit - $ocrUsed),
                ],
                'children' => [
                    'used' => $childrenCount,
                    'limit' => $this->planFeatures->familyFeatureLimit($family, 'max_children', 2),
                ],
            ],
        ]);
    }

    public function setupMfa(Request $request): JsonResponse
    {
        $result = $this->setupMfaAction->execute($request->user());

        if (($result['ok'] ?? false) !== true) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        return response()->json([
            'data' => [
                'secret' => $result['secret'],
                'provisioning_uri' => $result['provisioning_uri'],
            ],
        ]);
    }

    public function enableMfa(Request $request): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'size:6']]);
        $result = $this->enableMfaAction->execute($request->user(), $validated['code']);

        if (($result['ok'] ?? false) !== true) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        return response()->json(['message' => 'Autenticación en dos pasos activada.']);
    }

    public function disableMfa(ConfirmPasswordRequest $request): JsonResponse
    {
        $this->disableMfaAction->execute($request->user());

        return response()->json(['message' => 'Autenticación en dos pasos desactivada.']);
    }

    public function notificationPreferences(): JsonResponse
    {
        $user = request()->user();

        return response()->json([
            'data' => NotificationPreferences::merge($user->notification_preferences),
        ]);
    }

    public function updateNotificationPreferences(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        $updated = $this->updateNotificationPreferencesAction->execute(
            $request->user(),
            $request->validated(),
        );

        return response()->json([
            'message' => 'Preferencias de notificaciones actualizadas.',
            'data' => $updated,
        ]);
    }

    public function deactivate(ConfirmPasswordRequest $request): JsonResponse
    {
        $this->deactivateAccount->execute($request->user());

        return response()->json([
            'message' => 'Tu cuenta fue desactivada temporalmente. Puedes reactivarla iniciando sesión.',
        ]);
    }

    public function reactivate(ReactivateAccountRequest $request): JsonResponse
    {
        $result = $this->reactivateAccount->execute($request->validated());

        if (($result['ok'] ?? false) !== true) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        return response()->json([
            'message' => 'Cuenta reactivada correctamente.',
            'data' => [
                ...$result['tokens'],
                'user' => new UserResource($result['user']),
            ],
        ]);
    }

    public function destroy(ConfirmPasswordRequest $request): JsonResponse
    {
        $this->deleteAccount->execute($request->user());

        return response()->json([
            'message' => 'Tu cuenta fue eliminada de forma permanente.',
        ]);
    }
}
