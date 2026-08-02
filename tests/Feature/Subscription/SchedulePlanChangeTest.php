<?php

declare(strict_types=1);

namespace Tests\Feature\Subscription;

use App\Models\Family;
use App\Models\Subscription;
use App\Models\User;
use App\Support\SubscriptionPlanCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\AuthenticatesUsers;
use Tests\TestCase;

class SchedulePlanChangeTest extends TestCase
{
    use AuthenticatesUsers;
    use RefreshDatabase;

    public function test_cannot_checkout_same_plan_while_active(): void
    {
        SubscriptionPlanCatalog::ensureSeeded();
        ['tokens' => $tokens, 'family' => $family, 'user' => $user] = $this->createUserWithFamily();

        Subscription::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $family->id,
            'plan_code' => 'family_plus',
            'billing' => 'monthly',
            'status' => 'active',
            'provider' => 'simulated',
            'current_period_end' => now()->addMonth(),
            'renewal_user_id' => $user->id,
        ]);
        $family->update(['plan' => 'family_plus']);

        // Con Wompi deshabilitado el checkout simulado debe rechazar.
        config(['wompi.enabled' => false]);

        $this->postJson('/api/v1/subscriptions/checkout', [
            'plan_code' => 'family_plus',
            'billing' => 'monthly',
            'card_number' => '4242424242424242',
            'exp_month' => 12,
            'exp_year' => (int) date('Y') + 2,
            'cvc' => '123',
            'cardholder_name' => 'Maria Garcia',
        ], $this->authHeaders($tokens))
            ->assertStatus(422);
    }

    public function test_schedule_change_stores_pending_without_changing_active_plan(): void
    {
        SubscriptionPlanCatalog::ensureSeeded();
        ['tokens' => $tokens, 'family' => $family, 'user' => $user] = $this->createUserWithFamily();

        Subscription::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $family->id,
            'plan_code' => 'family_plus',
            'billing' => 'monthly',
            'status' => 'active',
            'provider' => 'simulated',
            'current_period_end' => now()->addMonth(),
            'renewal_user_id' => $user->id,
        ]);
        $family->update(['plan' => 'family_plus']);

        $this->postJson('/api/v1/subscriptions/schedule-change', [
            'plan_code' => 'family_pro',
            'billing' => 'yearly',
        ], $this->authHeaders($tokens))
            ->assertOk()
            ->assertJsonPath('data.pending_plan_code', 'family_pro')
            ->assertJsonPath('data.pending_billing', 'yearly');

        $family->refresh();
        $this->assertSame('family_plus', $family->plan);
        $this->assertSame('family_plus', $family->subscription->plan_code);
        $this->assertSame('family_pro', $family->subscription->pending_plan_code);
    }
}
