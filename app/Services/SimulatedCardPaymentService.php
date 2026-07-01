<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Validation\ValidationException;

class SimulatedCardPaymentService
{
    private const DECLINE_LAST4 = ['0002', '0010', '9999'];

    private const BRAND_LENGTHS = [
        'visa' => [16],
        'mastercard' => [16],
        'amex' => [15],
    ];

    public function validate(array $card): array
    {
        $number = preg_replace('/\D/', '', $card['card_number'] ?? '') ?? '';
        $expMonth = (int) ($card['exp_month'] ?? 0);
        $expYear = (int) ($card['exp_year'] ?? 0);
        $cvc = preg_replace('/\D/', '', $card['cvc'] ?? '') ?? '';
        $holder = trim((string) ($card['cardholder_name'] ?? ''));

        $brand = $this->detectBrand($number);
        if ($brand === null) {
            throw ValidationException::withMessages([
                'card_number' => 'Solo se aceptan Visa, Mastercard o American Express.',
            ]);
        }

        $allowedLengths = self::BRAND_LENGTHS[$brand];
        if (! in_array(strlen($number), $allowedLengths, true)) {
            throw ValidationException::withMessages([
                'card_number' => 'Longitud inválida para '.$this->brandLabel($brand).'.',
            ]);
        }

        if (! $this->passesLuhn($number)) {
            throw ValidationException::withMessages(['card_number' => 'Número de tarjeta inválido (Luhn).']);
        }

        if ($expMonth < 1 || $expMonth > 12) {
            throw ValidationException::withMessages(['exp_month' => 'Mes de vencimiento inválido (01-12).']);
        }

        $fullYear = $expYear < 100 ? 2000 + $expYear : $expYear;
        $now = now();
        if ($fullYear < $now->year || $fullYear > $now->year + 20) {
            throw ValidationException::withMessages(['exp_year' => 'Año de vencimiento inválido.']);
        }

        $expiry = \Carbon\Carbon::create($fullYear, $expMonth, 1)->endOfMonth();
        if ($expiry->isPast()) {
            throw ValidationException::withMessages(['exp_year' => 'La tarjeta está vencida.']);
        }

        $cvcLen = $brand === 'amex' ? 4 : 3;
        if (strlen($cvc) !== $cvcLen || ! ctype_digit($cvc)) {
            throw ValidationException::withMessages([
                'cvc' => $brand === 'amex' ? 'CID debe tener 4 dígitos.' : 'CVC debe tener 3 dígitos.',
            ]);
        }

        if (strlen($holder) < 3 || strlen($holder) > 26) {
            throw ValidationException::withMessages(['cardholder_name' => 'Nombre del titular inválido.']);
        }

        if (! preg_match("/^[A-Za-zÀ-ÿ][A-Za-zÀ-ÿ\\s'\\-]{2,25}$/u", $holder)) {
            throw ValidationException::withMessages(['cardholder_name' => 'Nombre del titular inválido.']);
        }

        $words = preg_split('/\\s+/', $holder) ?: [];
        if (count($words) < 2 || collect($words)->contains(fn (string $w) => strlen($w) < 2)) {
            throw ValidationException::withMessages([
                'cardholder_name' => 'Ingresa nombre y apellido como en la tarjeta.',
            ]);
        }

        return [
            'last4' => substr($number, -4),
            'brand' => $brand,
            'holder' => $holder,
        ];
    }

    public function simulateCharge(string $last4): void
    {
        if (in_array($last4, self::DECLINE_LAST4, true)) {
            throw ValidationException::withMessages([
                'card_number' => 'Pago rechazado por el emisor (simulado). Prueba otra tarjeta.',
            ]);
        }
    }

    public function detectBrand(string $number): ?string
    {
        if (preg_match('/^4/', $number)) {
            return 'visa';
        }
        if (preg_match('/^3[47]/', $number)) {
            return 'amex';
        }
        if (preg_match('/^5[1-5]/', $number) || preg_match('/^2(2[2-9]|[3-6]\\d|7[01]|720)/', $number)) {
            return 'mastercard';
        }

        return null;
    }

    private function brandLabel(string $brand): string
    {
        return match ($brand) {
            'visa' => 'Visa',
            'mastercard' => 'Mastercard',
            'amex' => 'American Express',
            default => $brand,
        };
    }

    private function passesLuhn(string $number): bool
    {
        $sum = 0;
        $alt = false;
        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $n = (int) $number[$i];
            if ($alt) {
                $n *= 2;
                if ($n > 9) {
                    $n -= 9;
                }
            }
            $sum += $n;
            $alt = ! $alt;
        }

        return $sum % 10 === 0;
    }
}
