<?php

declare(strict_types=1);

namespace App\Infrastructure\Wompi;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WompiHttpClient implements \App\Domains\Subscription\Contracts\PaymentGatewayClient
{
    public function isConfigured(): bool
    {
        return (bool) config('wompi.enabled')
            && filled(config('wompi.public_key'))
            && filled(config('wompi.private_key'))
            && filled(config('wompi.integrity_secret'));
    }

    public function usesSandbox(): bool
    {
        if ((bool) config('wompi.sandbox')) {
            return true;
        }

        $publicKey = (string) config('wompi.public_key');

        return str_starts_with($publicKey, 'pub_test_');
    }

    /**
     * Firma de integridad Widget / Web Checkout:
     * SHA256(reference + amountInCents + currency + integritySecret)
     */
    public function integritySignature(string $reference, int $amountInCents, string $currency): string
    {
        $secret = (string) config('wompi.integrity_secret');
        if ($secret === '') {
            throw new RuntimeException('Wompi no configurado (INTEGRITY_SECRET).');
        }

        return hash('sha256', $reference.$amountInCents.strtoupper($currency).$secret);
    }

    /**
     * Valida checksum de evento (webhook) X-Event-Checksum / signature.checksum.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifyEventChecksum(array $payload, ?string $headerChecksum): bool
    {
        $eventsSecret = (string) config('wompi.events_secret');
        if ($eventsSecret === '') {
            // Sin secret de eventos: aceptar (útil en local) pero no ideal en prod.
            return true;
        }

        $properties = data_get($payload, 'signature.properties');
        if (! is_array($properties) || $properties === []) {
            return false;
        }

        $concat = '';
        foreach ($properties as $property) {
            $concat .= (string) data_get($payload, 'data.'.$property, '');
        }
        $concat .= (string) ($payload['timestamp'] ?? '');
        $concat .= $eventsSecret;

        $expected = hash('sha256', $concat);
        $checksum = (string) ($headerChecksum ?: data_get($payload, 'signature.checksum', ''));

        return $checksum !== '' && hash_equals($expected, $checksum);
    }

    /**
     * @return array<string, mixed>
     */
    public function getTransaction(string $transactionId): array
    {
        $json = $this->request('GET', '/transactions/'.$transactionId);
        $data = $json['data'] ?? $json;

        return is_array($data) ? $data : [];
    }

    /**
     * Busca transacciones por reference (nuestra payment_reference).
     *
     * @return list<array<string, mixed>>
     */
    public function searchTransactionsByReference(string $reference): array
    {
        try {
            $json = $this->request('GET', '/transactions', null, ['reference' => $reference]);
        } catch (RuntimeException) {
            return [];
        }

        $data = $json['data'] ?? null;
        if (is_array($data) && array_is_list($data)) {
            return array_values(array_filter($data, 'is_array'));
        }
        if (is_array($data) && isset($data['id'])) {
            return [$data];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $body = null, array $query = []): array
    {
        $token = (string) config('wompi.private_key');
        if ($token === '') {
            throw new RuntimeException('Wompi no configurado (PRIVATE_KEY).');
        }

        $url = config('wompi.api_base').$path;
        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?').http_build_query($query);
        }

        $pending = Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->timeout(30);

        try {
            $response = match (strtoupper($method)) {
                'GET' => $pending->get($url),
                'POST' => $pending->post($url, $body ?? []),
                default => throw new RuntimeException("Método HTTP no soportado: {$method}"),
            };

            $response->throw();
        } catch (RequestException $e) {
            $detail = $e->response?->json('error.reason')
                ?? $e->response?->json('error.message')
                ?? $e->response?->body()
                ?? $e->getMessage();
            throw new RuntimeException('Error Wompi: '.$detail, previous: $e);
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];

        return $json;
    }
}
