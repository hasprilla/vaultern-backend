<?php

declare(strict_types=1);

namespace App\Services\MercadoPago;

use App\Models\Family;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPaymentEvent;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\FamilyNotificationService;
use App\Services\SubscriptionCheckoutService;
use App\Support\SubscriptionPlanCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class MercadoPagoCheckoutService
{
    public function __construct(
        private readonly MercadoPagoClient $client,
        private readonly SubscriptionCheckoutService $checkoutService,
        private readonly FamilyNotificationService $notifications,
    ) {}

    /**
     * @return array{checkout_url: string, payment_id: string, preference_id: string, payment: SubscriptionPayment}
     */
    public function startCheckout(Family $family, User $user, string $planCode, string $billing): array
    {
        if (! $this->client->isConfigured()) {
            throw ValidationException::withMessages([
                'mercadopago' => 'Mercado Pago no está habilitado en el servidor.',
            ]);
        }

        SubscriptionPlanCatalog::ensureSeeded();

        $billing = $billing === 'yearly' ? 'yearly' : 'monthly';

        $plan = SubscriptionPlan::query()
            ->where('code', $planCode)
            ->where('is_active', true)
            ->where('code', '!=', 'free')
            ->first();

        if ($plan === null) {
            throw ValidationException::withMessages([
                'plan_code' => 'Plan no disponible. Contacta soporte o intenta más tarde.',
            ]);
        }

        $amountCents = $billing === 'yearly'
            ? ($plan->price_yearly_cents ?? $plan->price_monthly_cents * 12)
            : $plan->price_monthly_cents;

        if ($amountCents <= 0) {
            throw ValidationException::withMessages([
                'plan_code' => 'El plan no tiene un precio válido.',
            ]);
        }

        $currency = strtoupper((string) ($plan->currency ?: 'COP'));
        $reference = 'ZMF-'.strtoupper(Str::random(12));
        $appUrl = rtrim((string) config('app.url'), '/');

        return DB::transaction(function () use (
            $family,
            $user,
            $plan,
            $billing,
            $amountCents,
            $currency,
            $reference,
            $appUrl,
        ) {
            $payment = SubscriptionPayment::query()->create([
                'id' => (string) Str::uuid(),
                'family_id' => $family->id,
                'subscription_id' => $family->subscription?->id,
                'user_id' => $user->id,
                'plan_code' => $plan->code,
                'billing' => $billing,
                'amount_cents' => $amountCents,
                'currency' => $currency,
                'status' => 'pending',
                'provider' => 'mercadopago',
                'payment_reference' => $reference,
                'metadata' => ['mode' => 'mercadopago'],
            ]);

            $this->logEvent($payment, $user, 'payment_initiated', 'Checkout Mercado Pago iniciado', [
                'plan_code' => $plan->code,
                'billing' => $billing,
                'amount_cents' => $amountCents,
            ]);

            $unitPrice = round($amountCents / 100, 2);
            $returnBase = $appUrl.'/api/v1/subscriptions/mp/return';

            try {
                $preference = $this->client->createPreference([
                    'items' => [[
                        'id' => $plan->code,
                        'title' => $plan->name.' ('.($billing === 'yearly' ? 'anual' : 'mensual').')',
                        'quantity' => 1,
                        'currency_id' => $currency,
                        'unit_price' => $unitPrice,
                    ]],
                    'payer' => array_filter([
                        'email' => $user->email,
                        'name' => $user->name,
                    ]),
                    'external_reference' => $payment->id,
                    'notification_url' => $appUrl.'/api/v1/webhooks/mercadopago',
                    'back_urls' => [
                        'success' => $returnBase.'?result=success&payment_id='.$payment->id,
                        'failure' => $returnBase.'?result=failure&payment_id='.$payment->id,
                        'pending' => $returnBase.'?result=pending&payment_id='.$payment->id,
                    ],
                    'auto_return' => 'approved',
                    'metadata' => [
                        'payment_id' => $payment->id,
                        'family_id' => $family->id,
                        'plan_code' => $plan->code,
                        'billing' => $billing,
                    ],
                    'statement_descriptor' => 'ZUMIFLY',
                ]);
            } catch (RuntimeException $e) {
                $payment->update([
                    'status' => 'failed',
                    'failure_reason' => $e->getMessage(),
                ]);
                $this->logEvent($payment, $user, 'payment_failed', $e->getMessage());

                throw ValidationException::withMessages([
                    'mercadopago' => 'No se pudo iniciar el pago con Mercado Pago. Intenta más tarde.',
                ]);
            }

            $preferenceId = (string) ($preference['id'] ?? '');
            $checkoutUrl = $this->client->usesTestCredentials()
                ? (string) ($preference['sandbox_init_point'] ?? $preference['init_point'] ?? '')
                : (string) ($preference['init_point'] ?? $preference['sandbox_init_point'] ?? '');

            if ($preferenceId === '' || $checkoutUrl === '') {
                $payment->update([
                    'status' => 'failed',
                    'failure_reason' => 'Preferencia MP incompleta',
                ]);
                $this->logEvent($payment, $user, 'payment_failed', 'Preferencia MP incompleta', $preference);

                throw ValidationException::withMessages([
                    'mercadopago' => 'Respuesta incompleta de Mercado Pago.',
                ]);
            }

            $meta = $payment->metadata ?? [];
            $meta['preference_id'] = $preferenceId;
            $meta['init_point'] = $checkoutUrl;
            $payment->update(['metadata' => $meta]);

            $this->logEvent($payment, $user, 'preference_created', 'Preferencia Mercado Pago creada', [
                'preference_id' => $preferenceId,
            ]);

            return [
                'checkout_url' => $checkoutUrl,
                'payment_id' => $payment->id,
                'preference_id' => $preferenceId,
                'payment' => $payment->fresh(['events']),
            ];
        });
    }

    /**
     * Consulta MP por external_reference y actualiza el pago local (útil si el webhook no llega).
     *
     * @return array{status: string, payment: SubscriptionPayment, synced: bool}
     */
    public function syncPayment(SubscriptionPayment $payment): array
    {
        if (! $this->client->isConfigured()) {
            return ['status' => $payment->status, 'payment' => $payment, 'synced' => false];
        }

        if ($payment->status === 'succeeded') {
            return ['status' => $payment->status, 'payment' => $payment->fresh(['events']), 'synced' => true];
        }

        $results = $this->client->searchPaymentsByExternalReference($payment->id);
        if ($results === []) {
            $this->logEvent($payment, $payment->user, 'mp_sync_empty', 'Sin pagos en Mercado Pago para esta referencia');

            return ['status' => $payment->status, 'payment' => $payment->fresh(['events']), 'synced' => false];
        }

        // Preferir approved; si no, el más reciente.
        usort($results, function (array $a, array $b): int {
            $rank = static fn (array $p): int => match (strtolower((string) ($p['status'] ?? ''))) {
                'approved' => 0,
                'in_process', 'pending', 'authorized' => 1,
                default => 2,
            };

            return $rank($a) <=> $rank($b);
        });

        $mpPayment = $results[0];
        $mpPaymentId = (string) ($mpPayment['id'] ?? '');
        $status = strtolower((string) ($mpPayment['status'] ?? ''));

        if ($mpPaymentId === '') {
            return ['status' => $payment->status, 'payment' => $payment->fresh(['events']), 'synced' => false];
        }

        $this->applyMercadoPagoStatus($payment, $mpPayment, $status, $mpPaymentId);

        $fresh = $payment->fresh(['events']);

        return ['status' => (string) $fresh?->status, 'payment' => $fresh, 'synced' => true];
    }

    /**
     * Procesa notificación de MP (idempotente).
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleWebhook(array $payload, ?string $topic = null, ?string $resourceId = null): void
    {
        $type = strtolower((string) ($payload['type'] ?? $topic ?? 'payment'));
        $mpPaymentId = (string) (
            data_get($payload, 'data.id')
            ?? $resourceId
            ?? $payload['id']
            ?? ''
        );

        // Solo procesamos notificaciones de pago.
        if ($type !== '' && ! str_contains($type, 'payment')) {
            Log::info('mp.webhook.ignored', ['type' => $type]);

            return;
        }

        if ($mpPaymentId === '') {
            Log::warning('mp.webhook.missing_payment_id', ['payload' => $payload]);

            return;
        }

        if (! $this->client->isConfigured()) {
            Log::error('mp.webhook.not_configured');

            return;
        }

        try {
            $mpPayment = $this->client->getPayment($mpPaymentId);
        } catch (RuntimeException $e) {
            Log::warning('mp.webhook.get_payment_failed', [
                'mp_payment_id' => $mpPaymentId,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $externalRef = (string) ($mpPayment['external_reference'] ?? '');
        $status = strtolower((string) ($mpPayment['status'] ?? ''));

        $payment = null;
        if ($externalRef !== '') {
            $payment = SubscriptionPayment::query()->find($externalRef);
        }
        if ($payment === null) {
            $payment = SubscriptionPayment::query()
                ->where('metadata->mp_payment_id', $mpPaymentId)
                ->first();
        }

        if ($payment === null) {
            Log::warning('mp.webhook.payment_not_found', [
                'mp_payment_id' => $mpPaymentId,
                'external_reference' => $externalRef,
            ]);

            return;
        }

        $this->applyMercadoPagoStatus($payment, $mpPayment, $status, $mpPaymentId);
    }

    /**
     * @param  array<string, mixed>  $mpPayment
     */
    public function applyMercadoPagoStatus(
        SubscriptionPayment $payment,
        array $mpPayment,
        string $status,
        string $mpPaymentId,
    ): void {
        DB::transaction(function () use ($payment, $mpPayment, $status, $mpPaymentId) {
            $payment->refresh();

            $meta = $payment->metadata ?? [];
            $meta['mp_payment_id'] = $mpPaymentId;
            $meta['mp_status'] = $status;
            $meta['mp_status_detail'] = $mpPayment['status_detail'] ?? null;
            $payment->update(['metadata' => $meta]);

            $user = $payment->user;
            $this->logEvent($payment, $user, 'mp_webhook_received', 'Webhook Mercado Pago', [
                'mp_payment_id' => $mpPaymentId,
                'mp_status' => $status,
            ]);

            if ($payment->status === 'succeeded') {
                return;
            }

            if (in_array($status, ['approved'], true)) {
                $plan = SubscriptionPlan::query()
                    ->where('code', $payment->plan_code)
                    ->where('is_active', true)
                    ->first();

                if ($plan === null) {
                    $payment->update([
                        'status' => 'failed',
                        'failure_reason' => 'Plan no disponible tras el pago',
                    ]);
                    $this->logEvent($payment, $user, 'payment_failed', 'Plan no disponible tras el pago');

                    return;
                }

                $family = Family::query()->with('subscription')->find($payment->family_id);
                if ($family === null || $user === null) {
                    $payment->update([
                        'status' => 'failed',
                        'failure_reason' => 'Familia o usuario no encontrado',
                    ]);

                    return;
                }

                $this->checkoutService->activateFromSuccessfulPayment(
                    $family,
                    $user,
                    $payment,
                    $plan,
                    (string) $payment->billing,
                    'mercadopago',
                );

                return;
            }

            if (in_array($status, ['rejected', 'cancelled', 'canceled'], true)) {
                $reason = (string) ($mpPayment['status_detail'] ?? 'Pago rechazado por Mercado Pago');
                $payment->update([
                    'status' => 'failed',
                    'failure_reason' => $reason,
                ]);
                $this->logEvent($payment, $user, 'payment_failed', $reason, [
                    'mp_payment_id' => $mpPaymentId,
                    'mp_status' => $status,
                ]);

                if ($user !== null) {
                    $this->notifications->notifyFamily(
                        $user,
                        'subscription_payment_failed',
                        'Pago rechazado',
                        "{$user->name}: {$reason} (ref. {$payment->payment_reference})",
                        ['entity_type' => 'subscription_payment', 'entity_id' => $payment->id],
                    );
                }

                return;
            }

            // pending / in_process / etc.
            $this->logEvent($payment, $user, 'payment_pending', 'Pago pendiente en Mercado Pago', [
                'mp_payment_id' => $mpPaymentId,
                'mp_status' => $status,
            ]);
        });
    }

    private function logEvent(
        SubscriptionPayment $payment,
        ?User $user,
        string $type,
        ?string $message = null,
        ?array $payload = null,
    ): void {
        SubscriptionPaymentEvent::query()->create([
            'id' => (string) Str::uuid(),
            'payment_id' => $payment->id,
            'user_id' => $user?->id,
            'event_type' => $type,
            'message' => $message,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }
}
