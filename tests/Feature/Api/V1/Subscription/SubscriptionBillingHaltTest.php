<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Subscription;

use App\Models\Family;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Services\SubscriptionBillingService;
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

    public function test_user_can_resume_scheduled_cancellation(): void
    {
        ['tokens' => $tokens, 'family' => $family, 'user' => $user] = $this->createUserWithFamily();

        $subscription = $this->createActiveSubscription($family, $user);
        SubscriptionPayment::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $family->id,
            'subscription_id' => $subscription->id,
            'user_id' => $user->id,
            'plan_code' => 'family_plus',
            'billing' => 'monthly',
            'amount_cents' => 499,
            'currency' => 'EUR',
            'status' => 'succeeded',
            'provider' => 'simulated',
            'payment_reference' => 'ZMF-TESTRESUME01',
            'card_brand' => 'visa',
            'card_last4' => '4242',
            'card_holder_name' => 'MARIA GARCIA',
            'paid_at' => now(),
        ]);
        app(SubscriptionBillingService::class)->haltFutureCharges(
            $family->fresh(['subscription']),
            $user,
            'Cancelación programada',
        );

        $this->postJson('/api/v1/subscriptions/resume', [], $this->authHeaders($tokens))
            ->assertOk()
            ->assertJsonPath('data.pending_cancellation', false)
            ->assertJsonPath('data.auto_renew', true);

        $subscription->refresh();

        $this->assertSame('active', $subscription->status);
        $this->assertNull($subscription->cancelled_at);
        $this->assertSame('4242', $subscription->renewal_card_last4);
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
