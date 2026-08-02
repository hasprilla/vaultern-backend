<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Enmascarado PCI-friendly: nunca expone PAN completo; solo marca + últimos 4.
 */
final class CardMask
{
    public static function display(?string $brand, ?string $last4): string
    {
        $digits = preg_replace('/\D+/', '', (string) $last4) ?? '';
        if (strlen($digits) < 4) {
            return '•••• •••• •••• ••••';
        }

        $tail = substr($digits, -4);
        $label = trim((string) $brand);
        if ($label === '') {
            $label = 'Tarjeta';
        }

        return "{$label}  •••• •••• •••• {$tail}";
    }

    /**
     * Extrae marca / last4 / titular desde payload de transacción Wompi.
     *
     * @param  array<string, mixed>  $tx
     * @return array{last4: string, brand: string, holder: string}|null
     */
    public static function fromWompiTransaction(array $tx): ?array
    {
        $method = $tx['payment_method'] ?? null;
        if (! is_array($method)) {
            return null;
        }

        $extra = $method['extra'] ?? [];
        if (! is_array($extra)) {
            $extra = [];
        }

        $last4 = (string) (
            $extra['last_four']
            ?? $extra['last_four_digits']
            ?? $method['last_four']
            ?? ''
        );
        $last4 = substr(preg_replace('/\D+/', '', $last4) ?? '', -4);
        if (strlen($last4) !== 4) {
            return null;
        }

        $brand = (string) (
            $extra['brand']
            ?? $extra['name']
            ?? $method['type']
            ?? 'Tarjeta'
        );

        $customer = is_array($tx['customer_data'] ?? null) ? $tx['customer_data'] : [];
        $holder = (string) (
            $extra['card_holder']
            ?? $extra['cardholder_name']
            ?? ($customer['full_name'] ?? null)
            ?? $tx['customer_email']
            ?? ''
        );

        return [
            'last4' => $last4,
            'brand' => $brand !== '' ? $brand : 'Tarjeta',
            'holder' => $holder,
        ];
    }
}
