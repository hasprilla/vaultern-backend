<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Infrastructure\Auth\TokenService;
use App\Models\FamilyJoinRequest;
use App\Models\FamilyMember;
use App\Models\User;
use App\Support\DeviceSecurityQuestions;
use Illuminate\Support\Facades\Hash;

/**
 * @phpstan-type LoginSuccess array{ok: true, user: User, tokens: array<string, mixed>, must_setup_device_recovery?: bool, must_rotate_device_secret?: bool}
 * @phpstan-type LoginFailure array{ok: false, status: int, message: string, code?: string, data?: array<string, mixed>}
 */
final class LoginUserAction
{
    public function __construct(
        private readonly TokenService $tokens,
        private readonly DeviceRegistrationService $devices,
        private readonly DeviceChallengeService $challenges,
    ) {}

    /**
     * @param  array{email: string, password: string, device_id?: string|null, platform?: string|null, fcm_token?: string|null}  $credentials
     * @return LoginSuccess|LoginFailure
     */
    public function execute(array $credentials): array
    {
        $user = User::query()->where('email', $credentials['email'])->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            return ['ok' => false, 'status' => 401, 'message' => 'Unauthorized'];
        }

        if ($user->role === 'hijo') {
            return [
                'ok' => false,
                'status' => 403,
                'message' => 'Los hijos no acceden a la app. Solo padres y madres gestionan la familia.',
            ];
        }

        if ($user->email_verified_at === null) {
            $joinedViaApproval = $user->family_id !== null
                && FamilyMember::query()
                    ->where('user_id', $user->id)
                    ->where('status', 'active')
                    ->exists()
                && FamilyJoinRequest::query()
                    ->where('email', $user->email)
                    ->where('status', 'approved')
                    ->exists();

            if ($joinedViaApproval) {
                $user->forceFill(['email_verified_at' => now()])->save();
            } else {
                return [
                    'ok' => false,
                    'status' => 403,
                    'message' => 'Confirma tu correo con el código enviado al registrarte.',
                    'code' => 'email_not_verified',
                ];
            }
        }

        if ($user->account_status === 'deactivated') {
            return [
                'ok' => false,
                'status' => 403,
                'message' => 'Tu cuenta está desactivada temporalmente. Puedes reactivarla desde el inicio de sesión.',
                'code' => 'account_deactivated',
            ];
        }

        if ($user->trashed() || $user->account_status === 'deleted') {
            return [
                'ok' => false,
                'status' => 403,
                'message' => 'Esta cuenta fue eliminada.',
            ];
        }

        $user->ensureActiveFamilyContext();

        $deviceId = $credentials['device_id'] ?? null;
        $hasDeviceId = is_string($deviceId) && $deviceId !== '';

        // Dispositivo nuevo + recuperación configurada → challenge (clave o pregunta).
        if ($hasDeviceId
            && $user->hasDeviceRecoveryConfigured()
            && ! $this->devices->isTrustedDevice($user, $deviceId)) {
            $challengeToken = $this->challenges->issue((int) $user->id);
            $questionKey = (string) $user->security_question_key;

            return [
                'ok' => false,
                'status' => 403,
                'message' => 'Detectamos un dispositivo nuevo. Confirma tu identidad con la clave secreta o la pregunta de seguridad.',
                'code' => 'requires_device_verification',
                'data' => [
                    'user_id' => $user->id,
                    'challenge_token' => $challengeToken,
                    'security_question_key' => $questionKey,
                    'security_question' => DeviceSecurityQuestions::label($questionKey),
                ],
            ];
        }

        // Dispositivo conocido o primer acceso (aún sin recuperación): registrar como trusted.
        if ($hasDeviceId) {
            $this->devices->registerTrusted(
                $user,
                $deviceId,
                $credentials['platform'] ?? null,
                $credentials['fcm_token'] ?? null,
            );
        }

        if ($user->mfa_enabled) {
            return [
                'ok' => false,
                'status' => 403,
                'message' => 'Ingresa el código de autenticación en dos pasos.',
                'code' => 'requires_mfa',
                'data' => ['user_id' => $user->id],
            ];
        }

        return [
            'ok' => true,
            'user' => $user->fresh() ?? $user,
            'tokens' => $this->tokens->issue($user),
            'must_setup_device_recovery' => ! $user->hasDeviceRecoveryConfigured(),
            'must_rotate_device_secret' => (bool) $user->device_secret_must_rotate,
        ];
    }
}
