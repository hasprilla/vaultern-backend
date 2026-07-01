<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Family;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPaymentEvent;
use App\Models\SubscriptionPlan;
use App\Models\User;
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
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function checkout(Family $family, User $user, array $input): array
    {
        $plan = SubscriptionPlan::query()
            ->where('code', $input['plan_code'])
            ->where('is_active', true)
            ->first();

        if ($plan === null) {
            throw ValidationException::withMessages([
                'plan_code' => 'Plan no disponible. Contacta soporte o intenta más tarde.',
            ]);
        }

        $billing = $input['billing'] ?? 'monthly';
        $isSimulated = (bool) ($input['simulated'] ?? true);
        $amountCents = $billing === 'yearly'
            ? ($plan->price_yearly_cents ?? $plan->price_monthly_cents * 12)
            : $plan->price_monthly_cents;

        $reference = 'ZMF-'.strtoupper(Str::random(12));

        return DB::transaction(function () use ($family, $user, $plan, $billing, $isSimulated, $amountCents, $reference, $input) {
            $payment = SubscriptionPayment::query()->create([
                'id' => (string) Str::uuid(),
                'family_id' => $family->id,
                'subscription_id' => $family->subscription?->id,
                'user_id' => $user->id,
                'plan_code' => $plan->code,
                'billing' => $billing,
                'amount_cents' => $amountCents,
                'currency' => $plan->currency,
                'status' => 'pending',
                'provider' => $isSimulated ? 'simulated' : 'manual',
                'payment_reference' => $reference,
                'metadata' => ['mode' => $isSimulated ? 'simulated' : 'live'],
            ]);

            $this->logEvent($payment, $user, 'payment_initiated', 'Pago iniciado', [
                'plan_code' => $plan->code,
                'billing' => $billing,
                'amount_cents' => $amountCents,
            ]);

            try {
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

                $this->cardPayments->simulateCharge($cardMeta['last4']);

                $payment->update([
                    'card_brand' => $cardMeta['brand'],
                    'card_last4' => $cardMeta['last4'],
                    'card_holder_name' => $cardMeta['holder'],
                    'status' => 'succeeded',
                    'paid_at' => now(),
                ]);

                $this->logEvent($payment, $user, 'payment_succeeded', 'Pago simulado exitoso', [
                    'payment_reference' => $reference,
                ]);
            } catch (ValidationException $e) {
                $reason = collect($e->errors())->flatten()->first() ?? 'Pago rechazado';
                $payment->update([
                    'status' => 'failed',
                    'failure_reason' => $reason,
                ]);
                $this->logEvent($payment, $user, 'payment_failed', $reason, [
                    'errors' => $e->errors(),
                ]);
                throw $e;
            }

            $periodEnd = $billing === 'yearly' ? now()->addYear() : now()->addMonth();

            $subscription = Subscription::query()->updateOrCreate(
                ['family_id' => $family->id],
                [
                    'id' => $family->subscription?->id ?? (string) Str::uuid(),
                    'plan_code' => $plan->code,
                    'billing' => $billing,
                    'status' => 'active',
                    'provider' => $isSimulated ? 'simulated' : 'manual',
                    'current_period_end' => $periodEnd,
                ],
            );

            $payment->update(['subscription_id' => $subscription->id]);
            $family->update(['plan' => $plan->code]);

            $this->logEvent($payment, $user, 'subscription_activated', 'Suscripción activada', [
                'subscription_id' => $subscription->id,
                'plan_code' => $plan->code,
                'current_period_end' => $periodEnd->toIso8601String(),
            ]);

            $this->notifications->notifyFamily(
                $user,
                'subscription_checkout',
                'Plan activado',
                "{$user->name} activó el plan {$plan->code}",
                ['entity_type' => 'subscription', 'entity_id' => $subscription->id],
            );

            return [
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
