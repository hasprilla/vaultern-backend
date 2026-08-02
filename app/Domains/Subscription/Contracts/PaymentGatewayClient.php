<?php

declare(strict_types=1);

namespace App\Domains\Subscription\Contracts;

interface PaymentGatewayClient
{
    public function isConfigured(): bool;

    public function integritySignature(string $reference, int $amountInCents, string $currency): string;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verifyEventChecksum(array $payload, ?string $headerChecksum): bool;

    /**
     * @return array<string, mixed>
     */
    public function getTransaction(string $transactionId): array;

    /**
     * @return list<array<string, mixed>>
     */
    public function searchTransactionsByReference(string $reference): array;
}
