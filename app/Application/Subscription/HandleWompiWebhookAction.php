<?php

declare(strict_types=1);

namespace App\Application\Subscription;

final class HandleWompiWebhookAction
{
    public function __construct(
        private readonly WompiCheckoutService $checkout,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(array $payload, ?string $checksumHeader = null): void
    {
        $this->checkout->handleWebhook($payload, $checksumHeader);
    }
}
