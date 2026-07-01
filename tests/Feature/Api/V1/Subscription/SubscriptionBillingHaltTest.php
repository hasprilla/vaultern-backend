<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Subscription;

use App\Models\Family;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionRenewalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\AuthenticatesUsers;
use Tests\TestCase;

class SubscriptionBillingHaltTest extends TestCase
{
    use AuthenticatesUsers;
    use RefreshDatabase;

    public function test_cancelled_subscription_is_not_renewed(): void
    {
        ['family' => $family, 'user' => $user] = $this->createUserWithFamily();

        $subscription = $this->createActiveSubscription($family, $user);
        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'renewal_card_last4' => null,
            'current_period_end' => now()->subDay(),
        ]);

        $result = app(SubscriptionRenewalService::class)->renew($subscription->fresh());

        $this->assertNull($result);
        $this->assertSame('cancelled', $subscription->fresh()->status);
    }

    public function test_deactivating_account_halts_family_billing(): void
    {
        ['tokens' => $tokens, 'family' => $family, 'user' => $user] = $this->createUserWithFamily();

        $subscription = $this->createActiveSubscription($family, $user);

        $this->postJson('/api/v1/profile/account/deactivate', [
            'password' => 'password',
        ], $this->authHeaders($tokens))
            ->assertOk();

        $subscription->refresh();

        $this->assertSame('cancelled', $subscription->status);
        $this->assertNotNull($subscription->cancelled_at);
        $this->assertNull($subscription->renewal_card_last4);
        $this->assertNull($subscription->renewal_user_id);
        $this->assertFalse($subscription->canAutoRenew());
    }

    private function createActiveSubscription(Family $family, User $user): Subscription
    {
        return Subscription::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $family->id,
            'plan_code' => 'family_plus',
            'billing' => 'monthly',
            'status' => 'active',
            'provider' => 'simulated',
            'current_period_end' => now()->addMonth(),
            'renewal_card_last4' => '4242',
            'renewal_card_brand' => 'visa',
            'renewal_card_holder_name' => 'MARIA GARCIA',
            'renewal_user_id' => $user->id,
        ]);
    }
}
