<?php

declare(strict_types=1);

namespace App\Application\Subscription;

use App\Models\Family;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;

final class GetPaymentReceiptAction
{
    /**
     * @return array{ok: true, pdf: string, filename: string}|array{ok: false, status: int, message: string}
     */
    public function execute(User $user, SubscriptionPayment $payment): array
    {
        if ($user->family_id === null || $payment->family_id !== $user->family_id) {
            return ['ok' => false, 'status' => 403, 'message' => 'No autorizado'];
        }

        if ($payment->status !== 'succeeded') {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Solo se puede emitir comprobante de pagos aprobados.',
            ];
        }

        $payment->loadMissing(['user:id,name,email', 'family:id,name']);
        $plan = SubscriptionPlan::query()->where('code', $payment->plan_code)->first();
        $family = $payment->family instanceof Family ? $payment->family : null;

        $amount = number_format(((int) $payment->amount_cents) / 100, 2, ',', '.');
        $currency = strtoupper((string) $payment->currency);
        $paidAt = optional($payment->paid_at ?? $payment->created_at)?->timezone(config('app.timezone'))->format('d/m/Y H:i');
        $planLabel = $plan?->name ?? $payment->plan_code;
        $billing = $payment->billing === 'yearly' ? 'Anual' : 'Mensual';
        $card = $payment->card_last4
            ? trim(($payment->card_brand ?? 'Tarjeta').' •••• '.$payment->card_last4)
            : '—';
        $provider = match ($payment->provider) {
            'wompi' => 'Wompi',
            'simulated' => 'Simulado',
            default => (string) ($payment->provider ?: '—'),
        };

        $pdf = Pdf::loadView('receipts.subscription_payment', [
            'reference' => $payment->payment_reference,
            'amount' => $amount,
            'currency' => $currency,
            'paidAt' => $paidAt,
            'planLabel' => $planLabel,
            'billing' => $billing,
            'card' => $card,
            'holder' => $payment->card_holder_name ?: '—',
            'provider' => $provider,
            'payerName' => $payment->user?->name ?? '—',
            'payerEmail' => $payment->user?->email ?? '—',
            'familyName' => $family?->name ?? '—',
            'issuedAt' => now()->timezone(config('app.timezone'))->format('d/m/Y H:i'),
        ])->setPaper('letter');

        $safeRef = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $payment->payment_reference) ?: $payment->id;

        return [
            'ok' => true,
            'pdf' => $pdf->output(),
            'filename' => "comprobante-zumifly-{$safeRef}.pdf",
        ];
    }
}
