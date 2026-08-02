<?php

declare(strict_types=1);

namespace App\Services;

use App\Infrastructure\Wompi\WompiHttpClient;
use App\Models\FamilyPaymentMethod;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPaymentEvent;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\SubscriptionPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SubscriptionRenewalService
{
    public function __construct(
        private readonly SimulatedCardPaymentService $cardPayments,
        private readonly FamilyPaymentMethodService $paymentMethods,
        private readonly WompiHttpClient $wompi,
        private readonly FamilyNotificationService $notifications,
    ) {}

    /**
     * @return array{processed: int, renewed: int, failed: int, expired: int}
     */
    public function renewDueSubscriptions(): array
    {
        $stats = ['processed' => 0, 'renewed' => 0, 'failed' => 0, 'expired' => 0];

        // Active vencidas con métodos / past_due en gracia.
        Subscription::query()
            ->whereIn('status', ['active', 'past_due'])
            ->whereNull('cancelled_at')
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

        // Sin métodos cobrables / canceladas al vencer: a free (sin intentar cobro).
        Subscription::query()
            ->whereIn('status', ['active', 'cancelled', 'past_due'])
            ->whereNotNull('current_period_end')
            ->whereDate('current_period_end', '<', now()->toDateString())
            ->with(['family', 'renewalUser'])
            ->orderBy('current_period_end')
            ->chunkById(50, function ($subscriptions) use (&$stats) {
                foreach ($subscriptions as $subscription) {
                    if ($subscription->status === 'past_due' && ! $subscription->graceExpired()) {
                        continue;
                    }
                    if ($subscription->isDueForRenewal()) {
                        continue;
                    }
                    // past_due con gracia vencida ya se maneja en renew(); aquí solo leftovers.
                    if ($subscription->status === 'past_due' && $subscription->graceExpired()) {
                        continue;
                    }
                    if ($subscription->status === 'expired') {
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
     * @return bool|null true=renewed, false=failed/expired, null=skipped
     */
    public function renew(Subscription $subscription): ?bool
    {
        return DB::transaction(function () use ($subscription) {
            /** @var Subscription|null $locked */
            $locked = Subscription::query()
                ->whereKey($subscription->id)
                ->lockForUpdate()
                ->first();

            if ($locked === null) {
                return null;
            }

            $locked->loadMissing('family');
            $family = $locked->family;
            if ($family === null) {
                return null;
            }

            // Anti pago doble: si ya se renovó este periodo, salir.
            if ($locked->current_period_end !== null
                && now()->toDateString() <= $locked->current_period_end->toDateString()
                && $locked->status === 'active') {
                return null;
            }

            $periodKey = $locked->renewalPeriodKey();
            if ($periodKey !== null && $this->hasSucceededRenewalForPeriod($locked, $periodKey)) {
                $this->markRenewedFromExistingPayment($locked, $periodKey);

                return true;
            }

            // No intentar si hay un cobro de renovación pendiente (en vuelo).
            if ($periodKey !== null && $this->hasInFlightRenewalForPeriod($locked, $periodKey)) {
                Log::info('subscription.renewal.skip_inflight', [
                    'subscription_id' => $locked->id,
                    'period_key' => $periodKey,
                ]);

                return null;
            }

            $methods = $this->paymentMethods->chargeableOrdered($family);
            if ($methods->isEmpty() && $locked->renewal_card_last4 !== null) {
                // Compat: mirror legacy sin fila en family_payment_methods.
                $methods = collect([
                    new FamilyPaymentMethod([
                        'id' => 'legacy-mirror',
                        'family_id' => $family->id,
                        'user_id' => $locked->renewal_user_id,
                        'provider' => $locked->provider === 'wompi' ? 'wompi' : 'simulated',
                        'provider_payment_source_id' => null,
                        'brand' => $locked->renewal_card_brand,
                        'last4' => $locked->renewal_card_last4,
                        'holder_name' => $locked->renewal_card_holder_name,
                        'is_default' => true,
                        'status' => 'active',
                    ]),
                ]);
                // Legacy wompi without source is not chargeable.
                $methods = $methods->filter(fn (FamilyPaymentMethod $m) => $m->isChargeable())->values();
            }

            if ($methods->isEmpty()) {
                if ($locked->status === 'past_due' && ! $locked->graceExpired()) {
                    return null;
                }
                $this->expireSubscription($locked, 'No hay método de pago cobrable para renovar.');

                return false;
            }

            if ($locked->status === 'active') {
                $graceEnds = ($locked->current_period_end ?? now())->copy()->addDays(2);
                $locked->update([
                    'status' => 'past_due',
                    'renewal_grace_ends_at' => $graceEnds,
                ]);
                $locked->refresh();
            }

            if ($locked->graceExpired()) {
                $this->expireSubscription(
                    $locked,
                    'No se pudo cobrar la renovación en el periodo de gracia (48 h).',
                    null,
                    null,
                    'grace_expired',
                );

                return false;
            }

            // Cambio programado: cobrar el plan pendiente al vencer (no el actual).
            $renewPlanCode = filled($locked->pending_plan_code)
                ? (string) $locked->pending_plan_code
                : (string) $locked->plan_code;
            $billing = filled($locked->pending_billing)
                ? (($locked->pending_billing === 'yearly') ? 'yearly' : 'monthly')
                : (($locked->billing ?? 'monthly') === 'yearly' ? 'yearly' : 'monthly');

            $plan = SubscriptionPlan::query()
                ->where('code', $renewPlanCode)
                ->where('is_active', true)
                ->first();

            if ($plan === null) {
                $this->expireSubscription($locked, 'El plan ya no está disponible.');

                return false;
            }

            $amountCents = $billing === 'yearly'
                ? ($plan->price_yearly_cents ?? $plan->price_monthly_cents * 12)
                : $plan->price_monthly_cents;

            $user = User::query()->find($locked->renewal_user_id)
                ?? $family->users()->orderBy('created_at')->first();

            if ($user === null) {
                $this->expireSubscription($locked, 'No se encontró un usuario para la renovación.');

                return false;
            }

            $previousPeriodEnd = $locked->current_period_end;
            $newPeriodEnd = SubscriptionPeriod::periodEndFrom(
                SubscriptionPeriod::freeFromAfter($previousPeriodEnd),
                $billing,
            );

            $lastFailure = null;

            foreach ($methods as $method) {
                // Re-check anti doble antes de cada intento de tarjeta.
                if ($periodKey !== null && $this->hasSucceededRenewalForPeriod($locked, $periodKey)) {
                    $this->markRenewedFromExistingPayment($locked, $periodKey);

                    return true;
                }

                $reference = 'ZMF-R-'.strtoupper(Str::random(10));
                $payment = SubscriptionPayment::query()->create([
                    'id' => (string) Str::uuid(),
                    'family_id' => $family->id,
                    'subscription_id' => $locked->id,
                    'user_id' => $user->id,
                    'plan_code' => $plan->code,
                    'billing' => $billing,
                    'amount_cents' => $amountCents,
                    'currency' => $plan->currency,
                    'status' => 'pending',
                    'provider' => $method->provider === 'wompi' ? 'wompi' : 'simulated',
                    'payment_reference' => $reference,
                    'card_brand' => $method->brand,
                    'card_last4' => $method->last4,
                    'card_holder_name' => $method->holder_name,
                    'metadata' => [
                        'mode' => 'renewal',
                        'auto_renew' => true,
                        'period_key' => $periodKey,
                        'payment_method_id' => $method->id,
                    ],
                ]);

                $this->logEvent($payment, $user, 'renewal_attempt', 'Intento de renovación automática', [
                    'plan_code' => $plan->code,
                    'billing' => $billing,
                    'amount_cents' => $amountCents,
                    'payment_method_id' => $method->id,
                    'card_last4' => $method->last4,
                    'period_key' => $periodKey,
                ]);

                try {
                    $chargeStatus = $this->chargeMethod(
                        $method,
                        $payment,
                        $user,
                        $amountCents,
                        (string) $plan->currency,
                        $reference,
                    );

                    // PENDING: no probar más tarjetas (evitar cobro doble si Wompi aprueba luego).
                    if ($chargeStatus === 'pending') {
                        $this->logEvent($payment, $user, 'renewal_pending', 'Cobro en proceso; no se reintentará otra tarjeta', [
                            'period_key' => $periodKey,
                            'payment_method_id' => $method->id,
                        ]);

                        return null;
                    }

                    // Barrera final anti doble: si otro proceso ya cobró, no marcar success otra vez.
                    if ($periodKey !== null && $this->hasSucceededRenewalForPeriod($locked, $periodKey, $payment->id)) {
                        $payment->update([
                            'status' => 'failed',
                            'failure_reason' => 'Cobro omitido: el periodo ya fue renovado (anti pago doble).',
                        ]);
                        $this->logEvent($payment, $user, 'renewal_duplicate_blocked', 'Pago doble bloqueado', [
                            'period_key' => $periodKey,
                        ]);

                        return true;
                    }

                    $payment->update([
                        'status' => 'succeeded',
                        'paid_at' => now(),
                    ]);

                    $this->logEvent($payment, $user, 'renewal_succeeded', 'Renovación automática exitosa', [
                        'payment_reference' => $reference,
                        'current_period_end' => $newPeriodEnd->toIso8601String(),
                        'period_key' => $periodKey,
                    ]);

                    $locked->update([
                        'status' => 'active',
                        'plan_code' => $plan->code,
                        'billing' => $billing,
                        'current_period_end' => $newPeriodEnd,
                        'cancelled_at' => null,
                        'renewal_grace_ends_at' => null,
                        'pending_plan_code' => null,
                        'pending_billing' => null,
                        'renewal_card_last4' => $method->last4,
                        'renewal_card_brand' => $method->brand,
                        'renewal_card_holder_name' => $method->holder_name,
                        'renewal_user_id' => $method->user_id ?? $user->id,
                    ]);

                    if ($method->exists && $method->id !== 'legacy-mirror') {
                        $this->paymentMethods->setDefault($family, $method);
                    }

                    $family->update(['plan' => $plan->code]);

                    $this->notifications->notifyFamilyById(
                        (string) $family->id,
                        null,
                        'subscription_renewed',
                        'Suscripción renovada',
                        "El plan {$plan->name} se renovó automáticamente (ref. {$reference}). Próximo vencimiento: {$newPeriodEnd->toDateString()}.",
                        [
                            'entity_type' => 'subscription',
                            'entity_id' => $locked->id,
                            'plan_code' => $plan->code,
                            'billing' => $billing,
                        ],
                    );

                    return true;
                } catch (ValidationException|RuntimeException $e) {
                    $reason = $e instanceof ValidationException
                        ? (collect($e->errors())->flatten()->first() ?? 'Pago de renovación rechazado')
                        : $e->getMessage();
                    $lastFailure = $reason;

                    $payment->update([
                        'status' => 'failed',
                        'failure_reason' => $reason,
                    ]);

                    $this->logEvent($payment, $user, 'renewal_failed', $reason, [
                        'payment_method_id' => $method->id,
                        'card_last4' => $method->last4,
                        'period_key' => $periodKey,
                    ]);
                }
            }

            // Todas fallaron; si aún hay gracia, esperar siguiente cron.
            if (! $locked->graceExpired()) {
                $this->notifications->notifyFamilyById(
                    (string) $family->id,
                    null,
                    'subscription_renewal_retry',
                    'Reintentando cobro',
                    'No se pudo renovar con las tarjetas guardadas. Reintentaremos hasta '
                        .($locked->renewal_grace_ends_at?->toDateTimeString() ?? '48 h')
                        .'. Motivo: '.($lastFailure ?? 'pago rechazado'),
                    ['entity_type' => 'subscription', 'entity_id' => $locked->id],
                );

                return false;
            }

            $this->expireSubscription(
                $locked,
                $lastFailure ?? 'No se pudo cobrar la renovación.',
                $user,
                null,
                'grace_expired',
            );

            return false;
        });
    }

    /**
     * @return 'approved'|'pending'
     */
    private function chargeMethod(
        FamilyPaymentMethod $method,
        SubscriptionPayment $payment,
        User $user,
        int $amountCents,
        string $currency,
        string $reference,
    ): string {
        if ($method->provider === 'wompi') {
            if (! filled($method->provider_payment_source_id)) {
                throw new RuntimeException('La tarjeta Wompi no tiene payment_source_id cobrable.');
            }

            $tx = $this->wompi->chargePaymentSource(
                $method->provider_payment_source_id,
                $amountCents,
                $currency,
                $reference,
                (string) $user->email,
            );

            $status = strtoupper((string) ($tx['status'] ?? ''));
            $payment->update([
                'metadata' => array_merge($payment->metadata ?? [], [
                    'wompi_transaction' => [
                        'id' => $tx['id'] ?? null,
                        'status' => $tx['status'] ?? null,
                    ],
                ]),
            ]);

            if ($status === 'APPROVED') {
                return 'approved';
            }

            if ($status === 'PENDING') {
                // Deja el payment en pending; in-flight bloquea nuevos intentos del mismo periodo.
                return 'pending';
            }

            throw new RuntimeException('Wompi rechazó el cobro ('.$status.').');
        }

        $this->cardPayments->simulateCharge((string) $method->last4);

        return 'approved';
    }

    private function hasSucceededRenewalForPeriod(
        Subscription $subscription,
        string $periodKey,
        ?string $exceptPaymentId = null,
    ): bool {
        $query = SubscriptionPayment::query()
            ->where('subscription_id', $subscription->id)
            ->where('status', 'succeeded')
            ->where('metadata->mode', 'renewal')
            ->where('metadata->period_key', $periodKey);

        if ($exceptPaymentId !== null) {
            $query->where('id', '!=', $exceptPaymentId);
        }

        return $query->exists();
    }

    private function hasInFlightRenewalForPeriod(Subscription $subscription, string $periodKey): bool
    {
        return SubscriptionPayment::query()
            ->where('subscription_id', $subscription->id)
            ->where('status', 'pending')
            ->where('metadata->mode', 'renewal')
            ->where('metadata->period_key', $periodKey)
            ->where('created_at', '>=', now()->subMinutes(15))
            ->exists();
    }

    private function markRenewedFromExistingPayment(Subscription $subscription, string $periodKey): void
    {
        if ($subscription->status === 'active'
            && $subscription->current_period_end !== null
            && now()->toDateString() <= $subscription->current_period_end->toDateString()) {
            return;
        }

        $payment = SubscriptionPayment::query()
            ->where('subscription_id', $subscription->id)
            ->where('status', 'succeeded')
            ->where('metadata->mode', 'renewal')
            ->where('metadata->period_key', $periodKey)
            ->orderByDesc('paid_at')
            ->first();

        if ($payment === null) {
            return;
        }

        $billing = $subscription->billing ?? 'monthly';
        $newPeriodEnd = SubscriptionPeriod::periodEndFrom(
            SubscriptionPeriod::freeFromAfter($subscription->current_period_end),
            $billing,
        );

        $subscription->update([
            'status' => 'active',
            'current_period_end' => $newPeriodEnd,
            'cancelled_at' => null,
            'renewal_grace_ends_at' => null,
        ]);
        $subscription->family?->update(['plan' => $subscription->plan_code]);
    }

    private function expireSubscription(
        Subscription $subscription,
        string $reason,
        ?User $user = null,
        ?string $reference = null,
        string $eventType = 'subscription_expired',
    ): void {
        $subscription->loadMissing('family');
        $subscription->update([
            'status' => 'expired',
            'renewal_grace_ends_at' => null,
        ]);
        $subscription->family?->reconcileSubscriptionPlan();

        $family = $subscription->family;
        if ($family === null) {
            return;
        }

        $suffix = $reference ? " (ref. {$reference})" : '';
        $title = $eventType === 'grace_expired' ? 'Pasaste al plan Free' : 'Suscripción vencida';
        $this->notifications->notifyFamilyById(
            (string) $family->id,
            null,
            $eventType === 'grace_expired' ? 'subscription_grace_expired' : 'subscription_expired',
            $title,
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
