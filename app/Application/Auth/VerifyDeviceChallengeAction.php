<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Infrastructure\Auth\TokenService;
use App\Models\User;
use App\Support\DeviceSecurityQuestions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * @phpstan-type Success array{ok: true, user: User, tokens: array<string, mixed>, must_rotate_secret: bool, requires_mfa: bool}
 * @phpstan-type Failure array{ok: false, status: int, message: string, code?: string, data?: array<string, mixed>}
 */
final class VerifyDeviceChallengeAction
{
    public function __construct(
        private readonly DeviceChallengeService $challenges,
        private readonly DeviceRegistrationService $devices,
        private readonly TokenService $tokens,
    ) {}

    /**
     * @param  array{
     *   user_id: int|string,
     *   challenge_token: string,
     *   device_id: string,
     *   platform?: string|null,
     *   fcm_token?: string|null,
     *   secret?: string|null,
     *   security_answer?: string|null
     * }  $input
     * @return Success|Failure
     */
    public function execute(array $input): array
    {
        $userId = (int) $input['user_id'];
        $token = (string) $input['challenge_token'];
        $deviceId = trim((string) $input['device_id']);
        $secret = isset($input['secret']) ? trim((string) $input['secret']) : '';
        $answer = isset($input['security_answer']) ? (string) $input['security_answer'] : '';

        if ($deviceId === '') {
            return ['ok' => false, 'status' => 422, 'message' => 'Falta el identificador del dispositivo.'];
        }

        if ($secret === '' && trim($answer) === '') {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Ingresa tu clave secreta o la respuesta a tu pregunta de seguridad.',
            ];
        }

        $rateKey = 'device_verify:'.$userId;
        if (RateLimiter::tooManyAttempts($rateKey, 8)) {
            return [
                'ok' => false,
                'status' => 429,
                'message' => 'Demasiados intentos. Espera unos minutos e inténtalo de nuevo.',
                'code' => 'too_many_attempts',
            ];
        }

        if (! $this->challenges->consume($token, $userId)) {
            RateLimiter::hit($rateKey, 600);

            return [
                'ok' => false,
                'status' => 403,
                'message' => 'El desafío expiró o no es válido. Vuelve a iniciar sesión.',
                'code' => 'challenge_expired',
            ];
        }

        $user = User::query()->find($userId);
        if ($user === null || ! $user->hasDeviceRecoveryConfigured()) {
            return [
                'ok' => false,
                'status' => 403,
                'message' => 'No hay recuperación de dispositivo configurada.',
            ];
        }

        $ok = false;
        if ($secret !== '' && is_string($user->device_secret_hash) && Hash::check($secret, $user->device_secret_hash)) {
            $ok = true;
        }

        if (! $ok && trim($answer) !== '' && is_string($user->security_answer_hash)) {
            $normalized = DeviceSecurityQuestions::normalizeAnswer($answer);
            if (Hash::check($normalized, $user->security_answer_hash)) {
                $ok = true;
            }
        }

        if (! $ok) {
            RateLimiter::hit($rateKey, 600);

            return [
                'ok' => false,
                'status' => 403,
                'message' => 'Clave secreta o respuesta incorrecta.',
                'code' => 'device_challenge_failed',
            ];
        }

        RateLimiter::clear($rateKey);

        $this->devices->registerTrusted(
            $user,
            $deviceId,
            $input['platform'] ?? null,
            $input['fcm_token'] ?? null,
        );
        $this->devices->revokeOtherTrustedDevices($user, $deviceId);

        // Tras cambio de dispositivo debe actualizar la clave secreta.
        $user->forceFill(['device_secret_must_rotate' => true])->save();
        $user->refresh();

        if ($user->mfa_enabled) {
            return [
                'ok' => true,
                'user' => $user,
                'tokens' => [],
                'must_rotate_secret' => true,
                'requires_mfa' => true,
            ];
        }

        return [
            'ok' => true,
            'user' => $user,
            'tokens' => $this->tokens->issue($user),
            'must_rotate_secret' => true,
            'requires_mfa' => false,
        ];
    }
}
