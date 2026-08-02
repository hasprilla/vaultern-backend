<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Family;
use App\Models\SubscriptionPlan;
use Illuminate\Validation\ValidationException;

/**
 * Reglas de cambio de ciclo/plan mientras hay acceso pagado vigente.
 */
final class SubscriptionChangePolicy
{
    /**
     * Checkout inmediato solo si NO hay acceso pago vigente.
     *
     * @throws ValidationException
     */
    public static function assertCheckoutAllowed(Family $family, string $planCode): void
    {
        $subscription = $family->subscription;

        if ($subscription === null || ! $subscription->hasPaidAccess()) {
            return;
        }

        self::assertNotSamePlan($family, $planCode);

        $until = $subscription->accessUntilDate() ?? 'el fin del periodo';

        throw ValidationException::withMessages([
            'plan_code' => "Ya tienes un plan activo hasta {$until}. "
                .'Los cambios de plan se programan y se cobran al vencimiento '
                .'(usa POST /subscriptions/schedule-change).',
        ]);
    }

    /**
     * Bloquea recomprar el mismo plan mientras hay acceso pago.
     *
     * @throws ValidationException
     */
    public static function assertNotSamePlan(Family $family, string $planCode): void
    {
        $subscription = $family->subscription;

        if ($subscription === null || ! $subscription->hasPaidAccess()) {
            return;
        }

        if ($subscription->plan_code === $planCode) {
            throw ValidationException::withMessages([
                'plan_code' => 'Ya tienes este plan activo. No puedes comprarlo de nuevo. '
                    .'Elige otro plan para programar el cambio al vencimiento.',
            ]);
        }
    }

    /**
     * Bloquea pasar de anual → mensual de forma inmediata (checkout).
     * Los cambios diferidos usan assertCanScheduleChange.
     *
     * @throws ValidationException
     */
    public static function assertBillingChangeAllowed(Family $family, string $newBilling): void
    {
        $billing = $newBilling === 'yearly' ? 'yearly' : 'monthly';
        $subscription = $family->subscription;

        if ($subscription === null) {
            return;
        }

        if (! $subscription->hasPaidAccess()) {
            return;
        }

        $currentBilling = ($subscription->billing ?? 'monthly') === 'yearly' ? 'yearly' : 'monthly';

        if ($currentBilling === 'yearly' && $billing === 'monthly') {
            $until = $subscription->accessUntilDate() ?? 'el fin del periodo';

            throw ValidationException::withMessages([
                'billing' => "Tu suscripción anual está activa hasta {$until}. "
                    .'No puedes cambiar a mensual de inmediato. '
                    .'Programa el cambio para que aplique al vencimiento.',
            ]);
        }
    }

    /**
     * Valida un cambio de plan programado (sin cobro ahora).
     *
     * @throws ValidationException
     */
    public static function assertCanScheduleChange(Family $family, string $planCode, string $billing): void
    {
        $subscription = $family->subscription;

        if ($subscription === null || ! $subscription->hasPaidAccess()) {
            throw ValidationException::withMessages([
                'plan_code' => 'No hay una suscripción activa para programar un cambio. Usa el checkout.',
            ]);
        }

        if ($subscription->isPendingCancellation()) {
            throw ValidationException::withMessages([
                'plan_code' => 'Tienes una cancelación programada. Revierte la cancelación antes de cambiar de plan.',
            ]);
        }

        $billing = $billing === 'yearly' ? 'yearly' : 'monthly';
        $currentBilling = ($subscription->billing ?? 'monthly') === 'yearly' ? 'yearly' : 'monthly';

        $plan = SubscriptionPlan::query()
            ->where('code', $planCode)
            ->where('is_active', true)
            ->where('code', '!=', 'free')
            ->first();

        if ($plan === null) {
            throw ValidationException::withMessages([
                'plan_code' => 'Plan no disponible.',
            ]);
        }

        // Mismo plan + mismo ciclo: no tiene sentido programar.
        // Mismo plan + otro ciclo (mensual↔anual) o plan distinto: sí, al vencimiento.
        if ($currentBilling === $billing && $subscription->plan_code === $planCode) {
            throw ValidationException::withMessages([
                'plan_code' => 'Ya tienes este plan y ciclo de facturación.',
            ]);
        }
    }
}
