<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPaymentEvent;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\SubscriptionPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubscriptionRenewalService
{
    public function __construct(
        private readonly SimulatedCardPaymentService $cardPayments,
        private readonly FamilyNotificationService $notifications,
    ) {}

    /**
     * @return array{processed: int, renewed: int, failed: int, expired: int}
     */
    public function renewDueSubscriptions(): array
    {
        $stats = ['processed' => 0, 'renewed' => 0, 'failed' => 0, 'expired' => 0];

        Subscription::query()
            ->where('status', 'active')
            ->whereNull('cancelled_at')
            ->whereNotNull('renewal_card_last4')
            ->whereNotNull('current_period_end')
            ->whereDate('current_period_end', '<', now()->toDateString())
            ->with('family')
            ->orderBy('current_period_end')
            ->chunkById(50, function ($subscriptions) use (&$stats) {
                foreach ($subscriptions as $subscription) {
                    $stats['processed']++;
                    $result = $this->renew($subscription);
                    if ($result === true) {
                        $stats['renewed']++;
                    } elseif ($result === false) {
                        $stats['failed']++;
                    }
                }
            });

        // Wompi / sin tarjeta / canceladas al vencer: expirar y avisar a toda la familia.
        Subscription::query()
            ->whereIn('status', ['active', 'cancelled'])
            ->whereNotNull('current_period_end')
            ->whereDate('current_period_end', '<', now()->toDateString())
            ->with(['family', 'renewalUser'])
            ->orderBy('current_period_end')
            ->chunkById(50, function ($subscriptions) use (&$stats) {
                foreach ($subscriptions as $subscription) {
                    if ($subscription->isDueForRenewal()) {
                        continue;
                    }

                    $stats['processed']++;
                    $this->expireSubscription(
                        $subscription,
                        'El periodo de suscripción venció.',
                        $subscription->renewalUser,
                    );
                    $stats['expired']++;
                }
            });

        return $stats;
    }

    /**
     * @return bool|null true=renewed, false=failed, null=skipped
     */
    public function renew(Subscription $subscription): ?bool
    {
        if (! $subscription->isDueForRenewal()) {
            return null;
        }

        $subscription->loadMissing('family');
        $family = $subscription->family;

        if ($family === null) {
            return null;
        }

        $this->hydrateRenewalCardFromLastPayment($subscription);

        if ($subscription->renewal_card_last4 === null) {
            $this->expireSubscription($subscription, 'No hay método de pago guardado para renovar.');

            return false;
        }

        $plan = SubscriptionPlan::query()
            ->where('code', $subscription->plan_code)
            ->where('is_active', true)
            ->first();

        if ($plan === null) {
            $this->expireSubscription($subscription, 'El plan ya no está disponible.');

            return false;
        }

        $billing = $subscription->billing ?? 'monthly';
        $amountCents = $billing === 'yearly'
            ? ($plan->price_yearly_cents ?? $plan->price_monthly_cents * 12)
            : $plan->price_monthly_cents;

        $user = User::query()->find($subscription->renewal_user_id)
            ?? $family->users()->orderBy('created_at')->first();

        if ($user === null) {
            $this->expireSubscription($subscription, 'No se encontró un usuario para la renovación.');

            return false;
        }

        $reference = 'ZMF-'.strtoupper(Str::random(12));
        $previousPeriodEnd = $subscription->current_period_end;
        $newPeriodEnd = SubscriptionPeriod::periodEndFrom(
            SubscriptionPeriod::freeFromAfter($previousPeriodEnd),
            $billing,
        );

        return DB::transaction(function () use (
            $subscription,
            $family,
            $user,
            $plan,
            $billing,
            $amountCents,
            $reference,
            $newPeriodEnd,
        ) {
            $payment = SubscriptionPayment::query()->create([
                'id' => (string) Str::uuid(),
                'family_id' => $family->id,
                'subscription_id' => $subscription->id,
                'user_id' => $user->id,
                'plan_code' => $plan->code,
                'billing' => $billing,
                'amount_cents' => $amountCents,
                'currency' => $plan->currency,
                'status' => 'pending',
                'provider' => $subscription->provider === 'simulated' ? 'simulated' : 'manual',
                'payment_reference' => $reference,
                'card_brand' => $subscription->renewal_card_brand,
                'card_last4' => $subscription->renewal_card_last4,
                'card_holder_name' => $subscription->renewal_card_holder_name,
                'metadata' => ['mode' => 'renewal', 'auto_renew' => true],
            ]);

            $this->logEvent($payment, $user, 'renewal_initiated', 'Renovación automática iniciada', [
                'plan_code' => $plan->code,
                'billing' => $billing,
                'amount_cents' => $amountCents,
            ]);

            try {
                $this->cardPayments->simulateCharge($subscription->renewal_card_last4);

                $payment->update([
                    'status' => 'succeeded',
                    'paid_at' => now(),
                ]);

                $this->logEvent($payment, $user, 'renewal_succeeded', 'Renovación automática exitosa', [
                    'payment_reference' => $reference,
                    'current_period_end' => $newPeriodEnd->toIso8601String(),
                ]);

                $subscription->update([
                    'status' => 'active',
                    'current_period_end' => $newPeriodEnd,
                    'cancelled_at' => null,
                ]);

                $family->update(['plan' => $plan->code]);

                $this->notifications->notifyFamilyById(
                    (string) $family->id,
                    null,
                    'subscription_renewed',
                    'Suscripción renovada',
                    "El plan {$plan->name} se renovó automáticamente (ref. {$reference}). Próximo vencimiento: {$newPeriodEnd->toDateString()}.",
                    ['entity_type' => 'subscription', 'entity_id' => $subscription->id],
                );

                return true;
            } catch (ValidationException $e) {
                $reason = collect($e->errors())->flatten()->first() ?? 'Pago de renovación rechazado';

                $payment->update([
                    'status' => 'failed',
                    'failure_reason' => $reason,
                ]);

                $this->logEvent($payment, $user, 'renewal_failed', $reason, [
                    'errors' => $e->errors(),
                ]);

                $this->expireSubscription($subscription, $reason, $user, $reference);

                return false;
            }
        });
    }

    private function hydrateRenewalCardFromLastPayment(Subscription $subscription): void
    {
        if ($subscription->renewal_card_last4 !== null) {
            return;
        }

        $lastPayment = $subscription->payments()
            ->where('status', 'succeeded')
            ->whereNotNull('card_last4')
            ->orderByDesc('paid_at')
            ->first();

        if ($lastPayment === null) {
            return;
        }

        $subscription->update([
            'renewal_card_last4' => $lastPayment->card_last4,
            'renewal_card_brand' => $lastPayment->card_brand,
            'renewal_card_holder_name' => $lastPayment->card_holder_name,
            'renewal_user_id' => $lastPayment->user_id,
        ]);

        $subscription->refresh();
    }

    private function expireSubscription(
        Subscription $subscription,
        string $reason,
        ?User $user = null,
        ?string $reference = null,
    ): void {
        $subscription->loadMissing('family');
        $subscription->update(['status' => 'expired']);
        $subscription->family?->reconcileSubscriptionPlan();

        $family = $subscription->family;
        if ($family === null) {
            return;
        }

        $suffix = $reference ? " (ref. {$reference})" : '';
        $this->notifications->notifyFamilyById(
            (string) $family->id,
            null,
            'subscription_expired',
            'Suscripción vencida',
            "El plan {$subscription->plan_code} venció. {$reason}{$suffix}",
            [
                'entity_type' => 'subscription',
                'entity_id' => $subscription->id,
                'plan_code' => $subscription->plan_code,
            ],
        );
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
