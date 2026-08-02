<?php

declare(strict_types=1);

namespace App\Application\Subscription;

use App\Models\SubscriptionPayment;

final class SyncWompiPaymentAction
{
    public function __construct(
        private readonly WompiCheckoutService $checkout,
    ) {}

    /**
     * @return array{status: string, payment: SubscriptionPayment, synced: bool}
     */
    public function execute(SubscriptionPayment $payment, ?string $transactionId = null): array
    {
        return $this->checkout->syncPayment($payment, $transactionId);
    }
}
