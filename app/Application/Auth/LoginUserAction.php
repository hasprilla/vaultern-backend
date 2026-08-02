<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Infrastructure\Auth\TokenService;
use App\Models\FamilyJoinRequest;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * @phpstan-type LoginSuccess array{ok: true, user: User, tokens: array<string, mixed>}
 * @phpstan-type LoginFailure array{ok: false, status: int, message: string, code?: string, data?: array<string, mixed>}
 */
final class LoginUserAction
{
    public function __construct(
        private readonly TokenService $tokens,
        private readonly DeviceRegistrationService $devices,
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

        // Desactivar en un núcleo ≠ bloquear login. Solo account_status lo hace.
        // Si el núcleo actual está inactivo, apunta a otro donde siga activo.
        $user->ensureActiveFamilyContext();

        $deviceId = $credentials['device_id'] ?? null;
        if (is_string($deviceId) && $deviceId !== '') {
            $this->devices->register(
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
            'user' => $user,
            'tokens' => $this->tokens->issue($user),
        ];
    }
}
