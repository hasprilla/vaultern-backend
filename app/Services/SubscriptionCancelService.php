<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Family;
use App\Models\User;
use App\Support\SubscriptionPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionCancelService
{
    public function __construct(
        private readonly FamilyNotificationService $notifications,
        private readonly SubscriptionBillingService $billing,
    ) {}

    public function cancel(Family $family, User $user): array
    {
        $subscription = $family->subscription;

        if ($subscription === null || $family->activePlanCode() === 'free') {
            throw ValidationException::withMessages([
                'plan' => 'No tienes una suscripción activa que cancelar.',
            ]);
        }

        if ($subscription->isPendingCancellation()) {
            throw ValidationException::withMessages([
                'plan' => 'La suscripción ya está programada para cancelarse al final del periodo.',
            ]);
        }

        if ($subscription->status !== 'active') {
            throw ValidationException::withMessages([
                'plan' => 'No tienes una suscripción activa que cancelar.',
            ]);
        }

        return DB::transaction(function () use ($family, $subscription, $user) {
            $previousPlan = $subscription->plan_code;
            $periodEnd = $subscription->current_period_end ?? now();

            $result = $this->billing->haltFutureCharges(
                $family,
                $user,
                "Cancelación programada del plan {$previousPlan}. Acceso hasta ".SubscriptionPeriod::accessUntilDate($periodEnd),
            );

            $this->notifications->notifyFamilyById(
                (string) $family->id,
                null,
                'subscription_cancel',
                'Cancelación programada',
                "{$user->name} programó la cancelación del plan {$previousPlan}. Acceso hasta ".SubscriptionPeriod::accessUntilDate($periodEnd).'.',
                ['entity_type' => 'subscription', 'entity_id' => $subscription->id],
            );

            return array_merge($result ?? [], [
                'previous_plan'        => $previousPlan,
                'cancelled_at'         => now()->toIso8601String(),
                'cancelled_by'         => $user->id,
                'pending_cancellation' => true,
            ]);
        });
    }
}
