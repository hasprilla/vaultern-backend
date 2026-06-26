<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Profile\ConfirmPasswordRequest;
use App\Http\Requests\Api\V1\Profile\ReactivateAccountRequest;
use App\Http\Requests\Api\V1\Profile\UpdateNotificationPreferencesRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Infrastructure\Auth\TokenService;
use App\Models\FamilyMember;
use App\Models\User;
use App\Support\NotificationPreferences;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function __construct(private readonly TokenService $tokens) {}

    public function notificationPreferences(): JsonResponse
    {
        $user = request()->user();

        return response()->json([
            'data' => NotificationPreferences::merge($user->notification_preferences),
        ]);
    }

    public function updateNotificationPreferences(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        $user = $request->user();
        $merged = NotificationPreferences::merge($user->notification_preferences);
        $updated = array_merge($merged, $request->validated());

        $user->update(['notification_preferences' => $updated]);

        return response()->json([
            'message' => 'Preferencias de notificaciones actualizadas.',
            'data'    => $updated,
        ]);
    }

    public function deactivate(ConfirmPasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user) {
            $user->update([
                'account_status'  => 'deactivated',
                'deactivated_at'  => now(),
            ]);

            FamilyMember::query()
                ->where('user_id', $user->id)
                ->update(['status' => 'inactive']);

            $this->tokens->revoke($user);
        });

        return response()->json([
            'message' => 'Tu cuenta fue desactivada temporalmente. Puedes reactivarla iniciando sesión.',
        ]);
    }

    public function reactivate(ReactivateAccountRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        if ($user === null || ! Hash::check($request->validated('password'), $user->password)) {
            return response()->json(['message' => 'Credenciales inválidas.'], 401);
        }

        if ($user->account_status !== 'deactivated') {
            return response()->json(['message' => 'Esta cuenta no está desactivada.'], 422);
        }

        $user->update([
            'account_status' => 'active',
            'deactivated_at' => null,
        ]);

        FamilyMember::query()
            ->where('user_id', $user->id)
            ->update(['status' => 'active']);

        $tokenData = $this->tokens->issue($user);

        return response()->json([
            'message' => 'Cuenta reactivada correctamente.',
            'data'    => [
                ...$tokenData,
                'user' => new UserResource($user->fresh()),
            ],
        ]);
    }

    public function destroy(ConfirmPasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user) {
            $this->tokens->revoke($user);

            FamilyMember::query()
                ->where('user_id', $user->id)
                ->update(['status' => 'inactive']);

            $user->update([
                'account_status' => 'deleted',
                'deactivated_at' => now(),
                'name'           => 'Usuario eliminado',
                'email'          => 'deleted_'.$user->id.'@deleted.zumifly.local',
                'notification_preferences' => NotificationPreferences::defaults(),
            ]);

            $user->delete();
        });

        return response()->json([
            'message' => 'Tu cuenta fue eliminada de forma permanente.',
        ]);
    }
}
