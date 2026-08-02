<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Family;
use Illuminate\Validation\ValidationException;

/**
 * Reglas de cambio de ciclo/plan mientras hay acceso pagado vigente.
 */
final class SubscriptionChangePolicy
{
    /**
     * Bloquea pasar de anual → mensual si el periodo anual sigue vigente.
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
                    .'No puedes cambiar a mensual mientras el periodo anual siga vigente. '
                    .'Cancela la renovación si quieres, y al vencer podrás elegir mensual.',
            ]);
        }
    }
}
