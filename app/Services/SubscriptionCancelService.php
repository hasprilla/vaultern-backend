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
        private readonly SubscriptionRenewalService $renewal,
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

        if (! in_array($subscription->status, ['active', 'past_due'], true)) {
            throw ValidationException::withMessages([
                'plan' => 'No tienes una suscripción activa que cancelar.',
            ]);
        }

        return DB::transaction(function () use ($family, $subscription, $user) {
            $previousPlan = $subscription->plan_code;
            $wasPastDue = $subscription->status === 'past_due';
            $periodEnd = $subscription->current_period_end;
            $periodEnded = $periodEnd !== null
                && now()->toDateString() > $periodEnd->toDateString();

            $accessUntil = $periodEnd !== null
                ? SubscriptionPeriod::accessUntilDate($periodEnd)
                : null;

            $result = $this->billing->haltFutureCharges(
                $family,
                $user,
                $wasPastDue || $periodEnded
                    ? "Paso a Free del plan {$previousPlan}. No se realizarán más cargos."
                    : "Cancelación programada del plan {$previousPlan}. Acceso hasta {$accessUntil}. No se realizarán más cargos.",
            );

            $movedToFree = false;
            if ($wasPastDue || $periodEnded) {
                $fresh = $family->fresh(['subscription'])?->subscription;
                if ($fresh !== null && $fresh->status !== 'expired') {
                    $this->renewal->expireToFree(
                        $fresh,
                        'Cancelaste la renovación. No se realizarán más cargos.',
                    );
                }
                $movedToFree = true;
                $family->refresh();
            }

            $message = $movedToFree
                ? "{$user->name} pasó al plan Free. No se realizarán más cargos automáticos."
                : "{$user->name} programó el paso a Free del plan {$previousPlan}. "
                    ."Mantendrá el acceso hasta {$accessUntil}. No se realizarán más cobros automáticos.";

            $this->notifications->notifyFamilyById(
                (string) $family->id,
                null,
                $movedToFree ? 'subscription_moved_to_free' : 'subscription_cancel',
                $movedToFree ? 'Pasaste al plan Free' : 'Paso a Free programado',
                $message,
                ['entity_type' => 'subscription', 'entity_id' => $subscription->id],
            );

            $subscription = $family->fresh(['subscription'])?->subscription;

            return array_merge($result ?? [], [
                'previous_plan'        => $previousPlan,
                'cancelled_at'         => now()->toIso8601String(),
                'cancelled_by'         => $user->id,
                'pending_cancellation' => ! $movedToFree,
                'moved_to_free'        => $movedToFree,
                'plan_code'            => $movedToFree ? 'free' : ($subscription?->plan_code ?? $previousPlan),
                'auto_renew'           => false,
            ]);
        });
    }
}
