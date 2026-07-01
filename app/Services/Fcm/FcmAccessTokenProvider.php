<?php

declare(strict_types=1);

namespace App\Services\Fcm;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FcmAccessTokenProvider
{
    public function get(bool $force = false): ?string
    {
        if (! $force && ! config('firebase.enabled')) {
            return null;
        }

        $credentialsPath = config('firebase.credentials');
        if (! is_string($credentialsPath) || ! is_file($credentialsPath)) {
            return null;
        }

        return Cache::remember('fcm_access_token', 3300, function () use ($credentialsPath) {
            $credentials = json_decode((string) file_get_contents($credentialsPath), true);
            if (! is_array($credentials)) {
                throw new RuntimeException('Firebase credentials JSON inválido.');
            }

            $jwt = $this->buildJwt($credentials);
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]);

            if (! $response->successful()) {
                throw new RuntimeException('No se pudo obtener token OAuth de Firebase.');
            }

            return $response->json('access_token');
        });
    }

    /** @param array<string, mixed> $credentials */
    private function buildJwt(array $credentials): string
    {
        $now = time();
        $header = $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = $this->base64Url(json_encode([
            'iss'   => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ], JSON_THROW_ON_ERROR));

        $privateKey = openssl_pkey_get_private((string) $credentials['private_key']);
        if ($privateKey === false) {
            throw new RuntimeException('Clave privada Firebase inválida.');
        }

        $signature = '';
        openssl_sign("$header.$payload", $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return "$header.$payload.".$this->base64Url($signature);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
