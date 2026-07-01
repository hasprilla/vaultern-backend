<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Family;
use App\Models\Subscription;
use App\Models\User;

class SubscriptionBillingService
{
    public function __construct(private readonly FamilyNotificationService $notifications) {}

    public function shouldHaltForUser(User $user): bool
    {
        if ($user->family_id === null) {
            return false;
        }

        $family = Family::query()->with('subscription')->find($user->family_id);
        $subscription = $family?->subscription;

        if ($subscription === null) {
            return false;
        }

        if ($subscription->renewal_user_id === $user->id) {
            return true;
        }

        return in_array($user->role, ['padre', 'madre'], true);
    }

    /**
     * Detiene cobros futuros: cancela renovación, borra tarjeta guardada y mantiene acceso hasta fin de periodo.
     *
     * @return array<string, mixed>|null
     */
    public function haltFutureCharges(
        Family $family,
        User $byUser,
        string $reason,
        bool $notify = false,
    ): ?array {
        $subscription = $family->subscription;

        if ($subscription === null) {
            return null;
        }

        $periodEnd = $subscription->current_period_end ?? now();

        $subscription->update([
            'status'                   => 'cancelled',
            'cancelled_at'             => $subscription->cancelled_at ?? now(),
            'renewal_card_last4'       => null,
            'renewal_card_brand'       => null,
            'renewal_card_holder_name' => null,
            'renewal_user_id'          => null,
        ]);

        $subscription->refresh();
        $family->reconcileSubscriptionPlan();

        if ($notify) {
            $this->notifications->notifyFamily(
                $byUser,
                'subscription_billing_halted',
                'Cobros detenidos',
                "{$byUser->name}: {$reason}. No se realizarán más cargos.",
                ['entity_type' => 'subscription', 'entity_id' => $subscription->id],
            );
        }

        return [
            'plan_code'            => $subscription->plan_code,
            'access_until'         => $subscription->accessUntilDate(),
            'current_period_end'   => $subscription->accessUntilDate(),
            'free_from'            => $subscription->freeFromDate()?->toDateString(),
            'pending_cancellation' => $subscription->isPendingCancellation(),
            'auto_renew'           => false,
        ];
    }

    public function clearPaymentMethod(Subscription $subscription): void
    {
        $subscription->update([
            'renewal_card_last4'       => null,
            'renewal_card_brand'       => null,
            'renewal_card_holder_name' => null,
            'renewal_user_id'          => null,
        ]);
    }
}
