<?php

declare(strict_types=1);

namespace App\Application\Profile;

use App\Models\Family;
use App\Models\User;
use App\Services\SubscriptionBillingService;

final class HaltFamilyBillingForUser
{
    public function __construct(
        private readonly SubscriptionBillingService $subscriptionBilling,
    ) {}

    public function execute(User $user, string $reason): void
    {
        if (! $this->subscriptionBilling->shouldHaltForUser($user)) {
            return;
        }

        $family = Family::query()->with('subscription')->find($user->family_id);

        if ($family === null) {
            return;
        }

        $this->subscriptionBilling->haltFutureCharges($family, $user, $reason, notify: true);
    }
}
