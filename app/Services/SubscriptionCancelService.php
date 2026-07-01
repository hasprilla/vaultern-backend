<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Family;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionCancelService
{
    public function __construct(private readonly FamilyNotificationService $notifications) {}

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

            $subscription->update([
                'status'       => 'cancelled',
                'cancelled_at' => now(),
            ]);

            $this->notifications->notifyFamily(
                $user,
                'subscription_cancel',
                'Cancelación programada',
                "{$user->name} programó la cancelación del plan {$previousPlan}. Acceso hasta {$periodEnd->toDateString()}.",
                ['entity_type' => 'subscription', 'entity_id' => $subscription->id],
            );

            return [
                'plan_code'           => $subscription->plan_code,
                'previous_plan'       => $previousPlan,
                'cancelled_at'        => now()->toIso8601String(),
                'cancelled_by'        => $user->id,
                'access_until'        => $periodEnd->toDateString(),
                'current_period_end'  => $periodEnd->toIso8601String(),
                'free_from'           => $subscription->fresh()->freeFromDate()?->toIso8601String(),
                'pending_cancellation'=> true,
            ];
        });
    }
}
