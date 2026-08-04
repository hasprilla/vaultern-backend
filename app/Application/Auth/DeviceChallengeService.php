<?php

declare(strict_types=1);

namespace App\Application\Auth;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tokens temporales tras login válido cuando el dispositivo es nuevo.
 *
 * Persistidos en BD (no Cache): con CACHE_STORE=array el token se pierde
 * entre el login y el verify → "desafío expirado o no es válido".
 */
final class DeviceChallengeService
{
    private const TTL_MINUTES = 15;

    public function issue(int $userId): string
    {
        $this->purgeExpired();

        $plain = Str::random(64);
        $hash = hash('sha256', $plain);

        DB::table('device_login_challenges')->insert([
            'token_hash' => $hash,
            'user_id' => $userId,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $plain;
    }

    /** Valida sin consumir (permite reintentar clave/respuesta sin re-login). */
    public function isValid(string $plainToken, int $expectedUserId): bool
    {
        if ($plainToken === '' || strlen($plainToken) < 32) {
            return false;
        }

        $row = DB::table('device_login_challenges')
            ->where('token_hash', hash('sha256', $plainToken))
            ->where('user_id', $expectedUserId)
            ->where('expires_at', '>', now())
            ->first();

        return $row !== null;
    }

    /** Invalida el token tras verificación exitosa. */
    public function consume(string $plainToken, int $expectedUserId): bool
    {
        if (! $this->isValid($plainToken, $expectedUserId)) {
            return false;
        }

        $deleted = DB::table('device_login_challenges')
            ->where('token_hash', hash('sha256', $plainToken))
            ->where('user_id', $expectedUserId)
            ->delete();

        return $deleted > 0;
    }

    private function purgeExpired(): void
    {
        DB::table('device_login_challenges')
            ->where('expires_at', '<=', now())
            ->delete();
    }
}
