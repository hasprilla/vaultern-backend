<?php

declare(strict_types=1);

namespace App\Application\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/** Tokens temporales tras login válido cuando el dispositivo es nuevo. */
final class DeviceChallengeService
{
    private const TTL_MINUTES = 10;

    public function issue(int $userId): string
    {
        $plain = Str::random(64);
        Cache::put($this->key($plain), $userId, now()->addMinutes(self::TTL_MINUTES));

        return $plain;
    }

    public function consume(string $plainToken, int $expectedUserId): bool
    {
        $key = $this->key($plainToken);
        $stored = Cache::pull($key);
        if ($stored === null) {
            return false;
        }

        return (int) $stored === $expectedUserId;
    }

    private function key(string $plainToken): string
    {
        return 'device_challenge:'.hash('sha256', $plainToken);
    }
}
