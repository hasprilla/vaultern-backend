<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Support\Str;

final class TokenService
{
    public function issue(User $user, string $name = 'mobile'): array
    {
        $access  = hash('sha256', Str::random(64));
        $refresh = hash('sha256', Str::random(64));

        ApiToken::query()->create([
            'user_id'       => $user->id,
            'name'          => $name,
            'token'         => $access,
            'refresh_token' => $refresh,
            'expires_at'    => now()->addDays(30),
        ]);

        return [
            'access_token'  => $access,
            'refresh_token' => $refresh,
            'expires_at'    => now()->addDays(30)->toIso8601String(),
        ];
    }

    public function findUserByToken(?string $token): ?User
    {
        if ($token === null || $token === '') {
            return null;
        }

        $apiToken = ApiToken::query()->where('token', $token)->first();

        if ($apiToken === null || $apiToken->isExpired()) {
            return null;
        }

        return $apiToken->user;
    }

    public function refresh(string $refreshToken): ?array
    {
        $apiToken = ApiToken::query()->where('refresh_token', $refreshToken)->first();

        if ($apiToken === null || $apiToken->isExpired()) {
            return null;
        }

        $apiToken->delete();

        return $this->issue($apiToken->user);
    }

    public function revoke(User $user): void
    {
        $user->apiTokens()->delete();
    }
}
