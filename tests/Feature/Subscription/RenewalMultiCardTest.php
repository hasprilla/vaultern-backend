<?php

declare(strict_types=1);

namespace Tests\Feature\Subscription;

use App\Models\Family;
use App\Models\FamilyPaymentMethod;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionRenewalService;
use App\Support\SubscriptionPlanCatalog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RenewalMultiCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_retries_next_card_and_never_double_charges_same_period(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 12:00:00'));
        SubscriptionPlanCatalog::ensureSeeded();

        $family = Family::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Fam Test',
            'plan' => 'family_plus',
            'invite_code' => 'ABC123',
        ]);
        $user = User::factory()->create([
            'family_id' => $family->id,
            'role' => 'padre',
        ]);

        $plan = SubscriptionPlan::query()->where('code', 'family_plus')->firstOrFail();

        $subscription = Subscription::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $family->id,
            'plan_code' => $plan->code,
            'billing' => 'monthly',
            'status' => 'active',
            'provider' => 'simulated',
            'current_period_end' => now()->subDay(),
            'renewal_user_id' => $user->id,
            'renewal_card_last4' => '0002',
        ]);

        FamilyPaymentMethod::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $family->id,
            'user_id' => $user->id,
            'provider' => 'simulated',
            'last4' => '0002', // decline
            'brand' => 'visa',
            'holder_name' => 'Test One',
            'is_default' => true,
            'status' => 'active',
        ]);
        FamilyPaymentMethod::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $family->id,
            'user_id' => $user->id,
            'provider' => 'simulated',
            'last4' => '4242', // approve
            'brand' => 'visa',
            'holder_name' => 'Test Two',
            'is_default' => false,
            'status' => 'active',
        ]);

        $renewals = app(SubscriptionRenewalService::class);
        $this->assertTrue($renewals->renew($subscription));

        $subscription->refresh();
        $this->assertSame('active', $subscription->status);
        $this->assertNull($subscription->renewal_grace_ends_at);
        $this->assertTrue($subscription->current_period_end->greaterThan(now()));

        $succeeded = SubscriptionPayment::query()
            ->where('subscription_id', $subscription->id)
            ->where('status', 'succeeded')
            ->where('metadata->mode', 'renewal')
            ->count();
        $this->assertSame(1, $succeeded);

        // Segunda corrida no debe cobrar de nuevo el mismo periodo.
        $this->assertNull($renewals->renew($subscription->fresh()));
        $this->assertSame(
            1,
            SubscriptionPayment::query()
                ->where('subscription_id', $subscription->id)
                ->where('status', 'succeeded')
                ->where('metadata->mode', 'renewal')
                ->count(),
        );

        Carbon::setTestNow();
    }

    public function test_grace_expires_to_free_after_two_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 12:00:00'));
        SubscriptionPlanCatalog::ensureSeeded();

        $family = Family::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Fam Grace',
            'plan' => 'family_plus',
            'invite_code' => 'XYZ789',
        ]);
        $user = User::factory()->create([
            'family_id' => $family->id,
            'role' => 'padre',
        ]);
        $plan = SubscriptionPlan::query()->where('code', 'family_plus')->firstOrFail();

        $subscription = Subscription::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $family->id,
            'plan_code' => $plan->code,
            'billing' => 'monthly',
            'status' => 'past_due',
            'provider' => 'simulated',
            'current_period_end' => now()->subDays(3),
            'renewal_grace_ends_at' => now()->subMinute(),
            'renewal_user_id' => $user->id,
        ]);

        FamilyPaymentMethod::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $family->id,
            'user_id' => $user->id,
            'provider' => 'simulated',
            'last4' => '0002',
            'brand' => 'visa',
            'is_default' => true,
            'status' => 'active',
        ]);

        $result = app(SubscriptionRenewalService::class)->renew($subscription);
        $this->assertFalse($result);

        $subscription->refresh();
        $family->refresh();
        $this->assertSame('expired', $subscription->status);
        $this->assertSame('free', $family->plan);

        Carbon::setTestNow();
    }
}
