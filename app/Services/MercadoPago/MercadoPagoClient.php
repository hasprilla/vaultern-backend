<?php

declare(strict_types=1);

namespace App\Services\MercadoPago;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MercadoPagoClient
{
    public function isConfigured(): bool
    {
        return (bool) config('mercadopago.enabled')
            && filled(config('mercadopago.access_token'));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createPreference(array $payload): array
    {
        return $this->request('POST', '/checkout/preferences', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayment(string $paymentId): array
    {
        return $this->request('GET', '/v1/payments/'.$paymentId);
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        $token = (string) config('mercadopago.access_token');
        if ($token === '') {
            throw new RuntimeException('Mercado Pago no configurado (ACCESS_TOKEN).');
        }

        $url = config('mercadopago.api_base').$path;

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
            $detail = $e->response?->json('message')
                ?? $e->response?->body()
                ?? $e->getMessage();
            throw new RuntimeException('Error Mercado Pago: '.$detail, previous: $e);
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];

        return $json;
    }
}
