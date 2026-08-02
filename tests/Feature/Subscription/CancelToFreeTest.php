<?php

declare(strict_types=1);

namespace Tests\Feature\Subscription;

use App\Models\Family;
use App\Models\FamilyPaymentMethod;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionRenewalService;
use App\Support\SubscriptionPlanCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\AuthenticatesUsers;
use Tests\TestCase;

class CancelToFreeTest extends TestCase
{
    use AuthenticatesUsers;
    use RefreshDatabase;

    public function test_active_cancel_schedules_free_and_blocks_renewal_charge(): void
    {
        SubscriptionPlanCatalog::ensureSeeded();
        ['tokens' => $tokens, 'family' => $family, 'user' => $user] = $this->createUserWithFamily();

        $subscription = $this->createPaidSubscription($family, $user, [
            'status' => 'active',
            'current_period_end' => now()->addMonth(),
        ]);
        $this->attachChargeableMethod($family, $user);

        $this->postJson('/api/v1/subscriptions/cancel', [], $this->authHeaders($tokens))
            ->assertOk()
            ->assertJsonPath('data.pending_cancellation', true)
            ->assertJsonPath('data.moved_to_free', false)
            ->assertJsonPath('data.auto_renew', false);

        $subscription->refresh();
        $this->assertSame('cancelled', $subscription->status);
        $this->assertNotNull($subscription->cancelled_at);
        $this->assertFalse($subscription->canAutoRenew());
        $this->assertSame('family_plus', $family->fresh()->activePlanCode());

        $result = app(SubscriptionRenewalService::class)->renew($subscription->fresh());
        $this->assertNull($result);
        $this->assertSame('cancelled', $subscription->fresh()->status);
        $this->assertSame(0, $subscription->payments()->where('status', 'succeeded')->count());
    }

    public function test_past_due_cancel_stops_retries_and_moves_to_free(): void
    {
        SubscriptionPlanCatalog::ensureSeeded();
        ['tokens' => $tokens, 'family' => $family, 'user' => $user] = $this->createUserWithFamily();

        $subscription = $this->createPaidSubscription($family, $user, [
            'status' => 'past_due',
            'current_period_end' => now()->subDay(),
            'renewal_grace_ends_at' => now()->addDay(),
        ]);
        $this->attachChargeableMethod($family, $user);
        $family->update(['plan' => 'family_plus']);

        $this->postJson('/api/v1/subscriptions/cancel', [], $this->authHeaders($tokens))
            ->assertOk()
            ->assertJsonPath('data.moved_to_free', true)
            ->assertJsonPath('data.pending_cancellation', false)
            ->assertJsonPath('data.plan_code', 'free');

        $subscription->refresh();
        $this->assertSame('expired', $subscription->status);
        $this->assertNotNull($subscription->cancelled_at);
        $this->assertNull($subscription->renewal_grace_ends_at);
        $this->assertSame('free', $family->fresh()->activePlanCode());

        $result = app(SubscriptionRenewalService::class)->renew($subscription->fresh());
        $this->assertNull($result);
        $this->assertSame(0, $subscription->payments()->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPaidSubscription(Family $family, User $user, array $overrides = []): Subscription
    {
        $subscription = Subscription::query()->create(array_merge([
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
        ], $overrides));

        $family->update(['plan' => 'family_plus']);

        return $subscription;
    }

    private function attachChargeableMethod(Family $family, User $user): void
    {
        FamilyPaymentMethod::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $family->id,
            'user_id' => $user->id,
            'provider' => 'simulated',
            'provider_payment_source_id' => 'src_test_'.Str::random(8),
            'brand' => 'visa',
            'last4' => '4242',
            'holder_name' => 'MARIA GARCIA',
            'is_default' => true,
            'status' => 'active',
        ]);
    }
}
