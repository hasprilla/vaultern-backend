<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Models\User;
use App\Support\DeviceSecurityQuestions;
use Illuminate\Support\Facades\Hash;

/**
 * Configura o rota la clave secreta + pregunta de seguridad del dispositivo.
 *
 * @phpstan-type Success array{ok: true}
 * @phpstan-type Failure array{ok: false, status: int, message: string}
 */
final class SetupDeviceRecoveryAction
{
    /**
     * @param  array{secret: string, security_question_key: string, security_answer: string}  $input
     * @return Success|Failure
     */
    public function execute(User $user, array $input): array
    {
        $secret = trim((string) ($input['secret'] ?? ''));
        $questionKey = (string) ($input['security_question_key'] ?? '');
        $answer = (string) ($input['security_answer'] ?? '');

        if (strlen($secret) < 8 || strlen($secret) > 128) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'La clave secreta debe tener entre 8 y 128 caracteres.',
            ];
        }

        if (! DeviceSecurityQuestions::isValidKey($questionKey)) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Pregunta de seguridad no válida.',
            ];
        }

        $normalizedAnswer = DeviceSecurityQuestions::normalizeAnswer($answer);
        if (mb_strlen($normalizedAnswer) < 2) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'La respuesta de seguridad es demasiado corta.',
            ];
        }

        // Evitar reutilizar la misma clave al rotar tras cambio de dispositivo.
        if ($user->device_secret_must_rotate
            && is_string($user->device_secret_hash)
            && Hash::check($secret, $user->device_secret_hash)) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Debes elegir una clave secreta distinta a la anterior.',
            ];
        }

        $user->forceFill([
            'device_secret_hash' => Hash::make($secret),
            'security_question_key' => $questionKey,
            'security_answer_hash' => Hash::make($normalizedAnswer),
            'device_secret_must_rotate' => false,
        ])->save();

        return ['ok' => true];
    }
}
