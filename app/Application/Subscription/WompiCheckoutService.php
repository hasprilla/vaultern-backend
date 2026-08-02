<?php

declare(strict_types=1);

namespace App\Application\Subscription;

use App\Domains\Subscription\Contracts\PaymentGatewayClient;
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

class WompiCheckoutService
{
    public function __construct(
        private readonly PaymentGatewayClient $client,
        private readonly SubscriptionCheckoutService $checkoutService,
        private readonly FamilyNotificationService $notifications,
    ) {}

    /**
     * @return array{checkout_url: string, payment_id: string, reference: string, payment: SubscriptionPayment}
     */
    public function startCheckout(Family $family, User $user, string $planCode, string $billing): array
    {
        if (! $this->client->isConfigured()) {
            throw ValidationException::withMessages([
                'wompi' => 'Wompi no está habilitado en el servidor.',
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
                'provider' => 'wompi',
                'payment_reference' => $reference,
                'metadata' => ['mode' => 'wompi'],
            ]);

            $this->logEvent($payment, $user, 'payment_initiated', 'Checkout Wompi iniciado', [
                'plan_code' => $plan->code,
                'billing' => $billing,
                'amount_cents' => $amountCents,
            ]);

            try {
                $signature = $this->client->integritySignature($reference, $amountCents, $currency);
            } catch (RuntimeException $e) {
                $payment->update([
                    'status' => 'failed',
                    'failure_reason' => $e->getMessage(),
                ]);
                $this->logEvent($payment, $user, 'payment_failed', $e->getMessage());

                throw ValidationException::withMessages([
                    'wompi' => 'No se pudo iniciar el pago con Wompi. Intenta más tarde.',
                ]);
            }

            $checkoutUrl = $appUrl.'/api/v1/subscriptions/wompi/pay/'.$payment->id;
            $redirectUrl = $appUrl.'/api/v1/subscriptions/wompi/return?payment_id='.$payment->id;

            $meta = $payment->metadata ?? [];
            $meta['integrity_signature'] = $signature;
            $meta['redirect_url'] = $redirectUrl;
            $meta['public_key'] = (string) config('wompi.public_key');
            $payment->update(['metadata' => $meta]);

            $this->logEvent($payment, $user, 'checkout_prepared', 'Checkout Wompi preparado', [
                'reference' => $reference,
                'amount_cents' => $amountCents,
            ]);

            return [
                'checkout_url' => $checkoutUrl,
                'payment_id' => $payment->id,
                'reference' => $reference,
                'payment' => $payment->fresh(['events']),
            ];
        });
    }

    /**
     * Datos para el formulario Web Checkout (página HTML auto-submit).
     *
     * @return array<string, string|int>
     */
    public function checkoutFormFields(SubscriptionPayment $payment): array
    {
        if ($payment->provider !== 'wompi' || $payment->status !== 'pending') {
            throw ValidationException::withMessages([
                'payment' => 'Este pago no está disponible para checkout Wompi.',
            ]);
        }

        if (! $this->client->isConfigured()) {
            throw ValidationException::withMessages([
                'wompi' => 'Wompi no está habilitado en el servidor.',
            ]);
        }

        $payment->loadMissing('user');

        $meta = $payment->metadata ?? [];
        $signature = (string) ($meta['integrity_signature'] ?? '');
        if ($signature === '') {
            $signature = $this->client->integritySignature(
                (string) $payment->payment_reference,
                (int) $payment->amount_cents,
                (string) $payment->currency,
            );
        }

        $redirectUrl = (string) ($meta['redirect_url'] ?? '');
        if ($redirectUrl === '') {
            $appUrl = rtrim((string) config('app.url'), '/');
            $redirectUrl = $appUrl.'/api/v1/subscriptions/wompi/return?payment_id='.$payment->id;
        }

        return [
            'public-key' => (string) config('wompi.public_key'),
            'currency' => strtoupper((string) $payment->currency),
            'amount-in-cents' => (int) $payment->amount_cents,
            'reference' => (string) $payment->payment_reference,
            'signature:integrity' => $signature,
            'redirect-url' => $redirectUrl,
            'customer-data:email' => (string) ($payment->user?->email ?? ''),
            'customer-data:full-name' => (string) ($payment->user?->name ?? ''),
        ];
    }

    /**
     * Consulta Wompi por transaction id o reference y actualiza el pago local.
     *
     * @return array{status: string, payment: SubscriptionPayment, synced: bool}
     */
    public function syncPayment(SubscriptionPayment $payment, ?string $transactionId = null): array
    {
        if (! $this->client->isConfigured()) {
            return ['status' => $payment->status, 'payment' => $payment, 'synced' => false];
        }

        if ($payment->status === 'succeeded') {
            return ['status' => $payment->status, 'payment' => $payment->fresh(['events']), 'synced' => true];
        }

        $tx = null;
        $txId = $transactionId
            ?: (string) data_get($payment->metadata, 'wompi_transaction_id', '');

        if ($txId !== '') {
            try {
                $tx = $this->client->getTransaction($txId);
            } catch (RuntimeException $e) {
                Log::warning('wompi.sync.get_failed', [
                    'payment_id' => $payment->id,
                    'transaction_id' => $txId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($tx === null) {
            $results = $this->client->searchTransactionsByReference((string) $payment->payment_reference);
            if ($results === []) {
                $this->logEvent($payment, $payment->user, 'wompi_sync_empty', 'Sin transacciones Wompi para esta referencia');

                return ['status' => $payment->status, 'payment' => $payment->fresh(['events']), 'synced' => false];
            }

            usort($results, function (array $a, array $b): int {
                $rank = static fn (array $t): int => match (strtoupper((string) ($t['status'] ?? ''))) {
                    'APPROVED' => 0,
                    'PENDING' => 1,
                    default => 2,
                };

                return $rank($a) <=> $rank($b);
            });
            $tx = $results[0];
        }

        $wompiId = (string) ($tx['id'] ?? '');
        $status = strtoupper((string) ($tx['status'] ?? ''));
        if ($wompiId === '' || $status === '') {
            return ['status' => $payment->status, 'payment' => $payment->fresh(['events']), 'synced' => false];
        }

        $this->applyWompiStatus($payment, $tx, $status, $wompiId);
        $fresh = $payment->fresh(['events']);

        return ['status' => (string) $fresh?->status, 'payment' => $fresh, 'synced' => true];
    }

    /**
     * Procesa evento Wompi (idempotente).
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleWebhook(array $payload, ?string $checksumHeader = null): void
    {
        $event = (string) ($payload['event'] ?? '');
        if ($event !== '' && $event !== 'transaction.updated') {
            Log::info('wompi.webhook.ignored', ['event' => $event]);

            return;
        }

        if (! $this->client->verifyEventChecksum($payload, $checksumHeader)) {
            Log::warning('wompi.webhook.invalid_checksum');

            return;
        }

        $tx = data_get($payload, 'data.transaction');
        if (! is_array($tx)) {
            Log::warning('wompi.webhook.missing_transaction', ['payload' => $payload]);

            return;
        }

        $wompiId = (string) ($tx['id'] ?? '');
        $reference = (string) ($tx['reference'] ?? '');
        $status = strtoupper((string) ($tx['status'] ?? ''));

        if ($wompiId === '' || $status === '') {
            return;
        }

        // Preferir datos frescos de la API (no confiar solo en el webhook).
        if ($this->client->isConfigured()) {
            try {
                $tx = $this->client->getTransaction($wompiId);
                $status = strtoupper((string) ($tx['status'] ?? $status));
                $reference = (string) ($tx['reference'] ?? $reference);
            } catch (RuntimeException $e) {
                Log::warning('wompi.webhook.get_transaction_failed', [
                    'transaction_id' => $wompiId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $payment = null;
        if ($reference !== '') {
            $payment = SubscriptionPayment::query()
                ->where('payment_reference', $reference)
                ->where('provider', 'wompi')
                ->first();
        }
        if ($payment === null) {
            $payment = SubscriptionPayment::query()
                ->where('metadata->wompi_transaction_id', $wompiId)
                ->first();
        }

        if ($payment === null) {
            Log::warning('wompi.webhook.payment_not_found', [
                'transaction_id' => $wompiId,
                'reference' => $reference,
            ]);

            return;
        }

        $this->applyWompiStatus($payment, $tx, $status, $wompiId);
    }

    /**
     * @param  array<string, mixed>  $tx
     */
    public function applyWompiStatus(
        SubscriptionPayment $payment,
        array $tx,
        string $status,
        string $wompiTransactionId,
    ): void {
        DB::transaction(function () use ($payment, $tx, $status, $wompiTransactionId) {
            $payment->refresh();

            $meta = $payment->metadata ?? [];
            $meta['wompi_transaction_id'] = $wompiTransactionId;
            $meta['wompi_status'] = $status;
            $meta['wompi_status_message'] = $tx['status_message'] ?? null;
            $meta['wompi_payment_method_type'] = $tx['payment_method_type'] ?? null;
            $payment->update(['metadata' => $meta]);

            $user = $payment->user;
            $this->logEvent($payment, $user, 'wompi_webhook_received', 'Evento/sync Wompi', [
                'wompi_transaction_id' => $wompiTransactionId,
                'wompi_status' => $status,
            ]);

            if ($payment->status === 'succeeded') {
                return;
            }

            if ($status === 'APPROVED') {
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
                    'wompi',
                );

                return;
            }

            if (in_array($status, ['DECLINED', 'VOIDED', 'ERROR'], true)) {
                $reason = (string) ($tx['status_message'] ?? 'Pago rechazado por Wompi');
                $payment->update([
                    'status' => 'failed',
                    'failure_reason' => $reason,
                ]);
                $this->logEvent($payment, $user, 'payment_failed', $reason, [
                    'wompi_transaction_id' => $wompiTransactionId,
                    'wompi_status' => $status,
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

            // PENDING u otros.
            $this->logEvent($payment, $user, 'payment_pending', 'Pago pendiente en Wompi', [
                'wompi_transaction_id' => $wompiTransactionId,
                'wompi_status' => $status,
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
