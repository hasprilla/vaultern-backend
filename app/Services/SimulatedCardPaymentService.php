<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Validation\ValidationException;

class SimulatedCardPaymentService
{
    /** Tarjetas de prueba: últimos 4 dígitos → resultado */
    private const DECLINE_LAST4 = ['0002', '0010', '9999'];

    public function validate(array $card): array
    {
        $number = preg_replace('/\D/', '', $card['card_number'] ?? '') ?? '';
        $expMonth = (int) ($card['exp_month'] ?? 0);
        $expYear = (int) ($card['exp_year'] ?? 0);
        $cvc = preg_replace('/\D/', '', $card['cvc'] ?? '') ?? '';
        $holder = trim((string) ($card['cardholder_name'] ?? ''));

        if (strlen($number) < 13 || strlen($number) > 19) {
            throw ValidationException::withMessages(['card_number' => 'Número de tarjeta inválido.']);
        }

        if (! $this->passesLuhn($number)) {
            throw ValidationException::withMessages(['card_number' => 'Número de tarjeta inválido.']);
        }

        if ($expMonth < 1 || $expMonth > 12) {
            throw ValidationException::withMessages(['exp_month' => 'Mes de vencimiento inválido.']);
        }

        $fullYear = $expYear < 100 ? 2000 + $expYear : $expYear;
        $expiry = \Carbon\Carbon::create($fullYear, $expMonth, 1)->endOfMonth();
        if ($expiry->isPast()) {
            throw ValidationException::withMessages(['exp_year' => 'La tarjeta está vencida.']);
        }

        $cvcLen = $this->detectBrand($number) === 'amex' ? 4 : 3;
        if (strlen($cvc) !== $cvcLen) {
            throw ValidationException::withMessages(['cvc' => 'CVC inválido.']);
        }

        if (strlen($holder) < 3) {
            throw ValidationException::withMessages(['cardholder_name' => 'Nombre del titular requerido.']);
        }

        return [
            'last4' => substr($number, -4),
            'brand' => $this->detectBrand($number),
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

    public function detectBrand(string $number): string
    {
        if (str_starts_with($number, '4')) {
            return 'visa';
        }
        if (preg_match('/^5[1-5]/', $number) || preg_match('/^2[2-7]/', $number)) {
            return 'mastercard';
        }
        if (preg_match('/^3[47]/', $number)) {
            return 'amex';
        }

        return 'card';
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
