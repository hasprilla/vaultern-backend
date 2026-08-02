<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Family;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPaymentEvent;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\SubscriptionChangePolicy;
use App\Support\SubscriptionPeriod;
use App\Support\SubscriptionPlanCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubscriptionCheckoutService
{
    public function __construct(
        private readonly SimulatedCardPaymentService $cardPayments,
        private readonly FamilyNotificationService $notifications,
    ) {}

    /**
     * Checkout simulado (tarjeta). Deshabilitado si Wompi está activo.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function checkout(Family $family, User $user, array $input): array
    {
        if (config('wompi.enabled')) {
            throw ValidationException::withMessages([
                'wompi' => 'Usa el checkout de Wompi (POST /subscriptions/checkout/wompi).',
            ]);
        }

        SubscriptionPlanCatalog::ensureSeeded();

        $planCode = (string) ($input['plan_code'] ?? '');
        $billing = ($input['billing'] ?? 'monthly') === 'yearly' ? 'yearly' : 'monthly';
        SubscriptionChangePolicy::assertBillingChangeAllowed($family, $billing);
        $isSimulated = (bool) ($input['simulated'] ?? true);
        $reference = 'ZMF-'.strtoupper(Str::random(12));

        $plan = SubscriptionPlan::query()
            ->where('code', $planCode)
            ->where('is_active', true)
            ->where('code', '!=', 'free')
            ->first();

        $amountCents = 0;
        if ($plan !== null) {
            $amountCents = $billing === 'yearly'
                ? ($plan->price_yearly_cents ?? $plan->price_monthly_cents * 12)
                : $plan->price_monthly_cents;
        }

        return DB::transaction(function () use ($family, $user, $plan, $planCode, $billing, $isSimulated, $amountCents, $reference, $input) {
            $payment = SubscriptionPayment::query()->create([
                'id' => (string) Str::uuid(),
                'family_id' => $family->id,
                'subscription_id' => $family->subscription?->id,
                'user_id' => $user->id,
                'plan_code' => $planCode !== '' ? $planCode : 'unknown',
                'billing' => $billing,
                'amount_cents' => $amountCents,
                'currency' => $plan?->currency ?? 'COP',
                'status' => 'pending',
                'provider' => $isSimulated ? 'simulated' : 'manual',
                'payment_reference' => $reference,
                'metadata' => ['mode' => $isSimulated ? 'simulated' : 'live'],
            ]);

            $this->logEvent($payment, $user, 'payment_initiated', 'Pago iniciado', [
                'plan_code' => $planCode,
                'billing' => $billing,
                'amount_cents' => $amountCents,
            ]);

            try {
                if ($plan === null) {
                    throw ValidationException::withMessages([
                        'plan_code' => 'Plan no disponible. Contacta soporte o intenta más tarde.',
                    ]);
                }

                $cardMeta = $this->cardPayments->validate([
                    'card_number' => $input['card_number'] ?? '',
                    'exp_month' => $input['exp_month'] ?? 0,
                    'exp_year' => $input['exp_year'] ?? 0,
                    'cvc' => $input['cvc'] ?? '',
                    'cardholder_name' => $input['cardholder_name'] ?? '',
                ]);

                $this->logEvent($payment, $user, 'card_validated', 'Tarjeta validada', [
                    'card_brand' => $cardMeta['brand'],
                    'card_last4' => $cardMeta['last4'],
                ]);

                $payment->update([
                    'card_brand' => $cardMeta['brand'],
                    'card_last4' => $cardMeta['last4'],
                    'card_holder_name' => $cardMeta['holder'],
                ]);

                $this->cardPayments->simulateCharge($cardMeta['last4']);
            } catch (ValidationException $e) {
                $reason = collect($e->errors())->flatten()->first() ?? 'Pago rechazado';
                $payment->update([
                    'status' => 'failed',
                    'failure_reason' => $reason,
                ]);
                $this->logEvent($payment, $user, 'payment_failed', $reason, [
                    'errors' => $e->errors(),
                ]);

                $this->notifications->notifyFamily(
                    $user,
                    'subscription_payment_failed',
                    'Pago rechazado',
                    "{$user->name}: {$reason} (ref. {$reference})",
                    ['entity_type' => 'subscription_payment', 'entity_id' => $payment->id],
                );

                return [
                    'success' => false,
                    'message' => $reason,
                    'plan_code' => $planCode,
                    'billing' => $billing,
                    'mode' => $isSimulated ? 'simulated' : 'live',
                    'payment' => $payment->fresh(['events']),
                    'subscription' => null,
                    'checkout_url' => null,
                ];
            }

            $subscription = $this->activateFromSuccessfulPayment(
                $family,
                $user,
                $payment,
                $plan,
                $billing,
                $isSimulated ? 'simulated' : 'manual',
                [
                    'last4' => $cardMeta['last4'],
                    'brand' => $cardMeta['brand'],
                    'holder' => $cardMeta['holder'],
                ],
            );

            return [
                'success' => true,
                'message' => $isSimulated
                    ? 'Plan activado. Pago simulado registrado.'
                    : 'Plan activado correctamente.',
                'plan_code' => $plan->code,
                'billing' => $billing,
                'mode' => $isSimulated ? 'simulated' : 'live',
                'payment' => $payment->fresh(['events']),
                'subscription' => $subscription->fresh(),
                'checkout_url' => null,
            ];
        });
    }

    /**
     * Activa suscripción tras pago exitoso (simulado o Wompi).
     *
     * @param  array{last4?: string, brand?: string, holder?: string}|null  $renewalCard
     */
    public function activateFromSuccessfulPayment(
        Family $family,
        User $user,
        SubscriptionPayment $payment,
        SubscriptionPlan $plan,
        string $billing,
        string $provider,
        ?array $renewalCard = null,
    ): Subscription {
        $periodEnd = SubscriptionPeriod::periodEndFrom(now(), $billing);

        $subscription = Subscription::query()->updateOrCreate(
            ['family_id' => $family->id],
            [
                'id' => $family->subscription?->id ?? (string) Str::uuid(),
                'plan_code' => $plan->code,
                'billing' => $billing,
                'status' => 'active',
                'provider' => $provider,
                'current_period_end' => $periodEnd,
                'cancelled_at' => null,
                'renewal_card_last4' => $renewalCard['last4'] ?? null,
                'renewal_card_brand' => $renewalCard['brand'] ?? null,
                'renewal_card_holder_name' => $renewalCard['holder'] ?? null,
                'renewal_user_id' => $user->id,
            ],
        );

        $payment->update([
            'status' => 'succeeded',
            'paid_at' => now(),
            'subscription_id' => $subscription->id,
            'failure_reason' => null,
        ]);

        $family->update(['plan' => $plan->code]);

        $this->logEvent($payment, $user, 'payment_succeeded', 'Pago exitoso', [
            'payment_reference' => $payment->payment_reference,
            'provider' => $provider,
        ]);

        $this->logEvent($payment, $user, 'subscription_activated', 'Suscripción activada', [
            'subscription_id' => $subscription->id,
            'plan_code' => $plan->code,
            'current_period_end' => $periodEnd->toIso8601String(),
        ]);

        $billingLabel = $billing === 'yearly' ? 'anual' : 'mensual';
        $until = $subscription->accessUntilDate() ?? '—';
        $this->notifications->notifyFamilyById(
            (string) $family->id,
            null,
            'subscription_checkout',
            'Suscripción actualizada',
            "{$user->name} activó el plan {$plan->name} ({$billingLabel}). Acceso hasta {$until}.",
            [
                'entity_type' => 'subscription',
                'entity_id' => $subscription->id,
                'plan_code' => $plan->code,
                'billing' => $billing,
                'current_period_end' => $until,
            ],
        );

        return $subscription;
    }

    private function logEvent(
        SubscriptionPayment $payment,
        User $user,
        string $type,
        ?string $message = null,
        ?array $payload = null,
    ): void {
        SubscriptionPaymentEvent::query()->create([
            'id' => (string) Str::uuid(),
            'payment_id' => $payment->id,
            'user_id' => $user->id,
            'event_type' => $type,
            'message' => $message,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }
}
