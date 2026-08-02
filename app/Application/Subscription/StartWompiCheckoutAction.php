<?php

declare(strict_types=1);

namespace App\Application\Subscription;

use App\Models\Family;
use App\Models\User;

final class StartWompiCheckoutAction
{
    public function __construct(
        private readonly WompiCheckoutService $checkout,
    ) {}

    /**
     * @return array{checkout_url: string, payment_id: string, reference: string, payment: \App\Models\SubscriptionPayment}
     */
    public function execute(Family $family, User $user, string $planCode, string $billing): array
    {
        return $this->checkout->startCheckout($family, $user, $planCode, $billing);
    }
}
