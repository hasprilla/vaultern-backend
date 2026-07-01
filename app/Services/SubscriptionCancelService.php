<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Family;
use App\Models\Subscription;
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

        return DB::transaction(function () use ($family, $subscription, $user) {
            $previousPlan = $subscription->plan_code;

            $subscription->update([
                'status'              => 'cancelled',
                'current_period_end'  => now(),
            ]);

            $family->update(['plan' => 'free']);

            $this->notifications->notifyFamily(
                $user,
                'subscription_cancel',
                'Suscripción cancelada',
                "{$user->name} canceló la suscripción (plan {$previousPlan})",
                ['entity_type' => 'subscription', 'entity_id' => $subscription->id],
            );

            return [
                'plan_code'      => 'free',
                'previous_plan'  => $previousPlan,
                'cancelled_at'   => now()->toIso8601String(),
                'cancelled_by'   => $user->id,
            ];
        });
    }
}
