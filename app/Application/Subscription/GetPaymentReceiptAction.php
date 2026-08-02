<?php

declare(strict_types=1);

namespace App\Application\Subscription;

use App\Models\Family;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\CardMask;
use Barryvdh\DomPDF\Facade\Pdf;
use Throwable;

final class GetPaymentReceiptAction
{
    /**
     * @return array{ok: true, pdf: string, filename: string}|array{ok: false, status: int, message: string}
     */
    public function execute(User $user, SubscriptionPayment $payment): array
    {
        if (
            $user->family_id === null
            || (string) $payment->family_id !== (string) $user->family_id
        ) {
            return ['ok' => false, 'status' => 403, 'message' => 'No autorizado'];
        }

        if ($payment->status !== 'succeeded') {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Solo se puede emitir comprobante de pagos aprobados.',
            ];
        }

        if (! class_exists(\Dompdf\Dompdf::class)) {
            return [
                'ok' => false,
                'status' => 500,
                'message' => 'Falta DomPDF en el servidor. Sube vendor/ (barryvdh + dompdf) y ejecuta: php artisan package:discover && php artisan config:clear',
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
        $card = CardMask::display($payment->card_brand, $payment->card_last4);
        $payerName = $payment->card_holder_name
            ?: ($payment->user?->name ?? '—');
        $provider = match ($payment->provider) {
            'wompi' => 'Wompi',
            'simulated' => 'Simulado',
            default => (string) ($payment->provider ?: '—'),
        };

        try {
            $pdf = Pdf::loadView('receipts.subscription_payment', [
                'logoSrc' => $this->logoDataUri(),
                'reference' => $payment->payment_reference,
                'amount' => $amount,
                'currency' => $currency,
                'paidAt' => $paidAt,
                'planLabel' => $planLabel,
                'billing' => $billing,
                'card' => $card,
                'holder' => $payerName,
                'provider' => $provider,
                'payerName' => $payerName,
                'payerEmail' => $payment->user?->email ?? '—',
                'familyName' => $family?->name ?? '—',
                'issuedAt' => now()->timezone(config('app.timezone'))->format('d/m/Y H:i'),
            ])->setPaper('letter');

            $binary = $pdf->output();
        } catch (Throwable $e) {
            report($e);

            return [
                'ok' => false,
                'status' => 500,
                'message' => 'No se pudo generar el PDF del comprobante. Verifica vendor DomPDF y permisos de storage/framework en cPanel.',
            ];
        }

        $safeRef = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $payment->payment_reference) ?: $payment->id;

        return [
            'ok' => true,
            'pdf' => $binary,
            'filename' => "comprobante-zumifly-{$safeRef}.pdf",
        ];
    }

    private function logoDataUri(): ?string
    {
        $path = public_path('images/zumifly-logo.png');
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $bytes = file_get_contents($path);
        if ($bytes === false || $bytes === '') {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($bytes);
    }
}
