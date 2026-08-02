<?php

declare(strict_types=1);

namespace App\Application\Subscription\Actions;

use App\Models\Family;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\FamilyNotificationService;
use App\Support\SubscriptionChangePolicy;
use Illuminate\Support\Facades\DB;

final class SchedulePlanChangeAction
{
    public function __construct(
        private readonly FamilyNotificationService $notifications,
    ) {}

    /**
     * @return array{pending_plan_code: string, pending_billing: string, pending_change_at: string|null, plan_name: string}
     */
    public function execute(Family $family, User $user, string $planCode, string $billing): array
    {
        $billing = $billing === 'yearly' ? 'yearly' : 'monthly';
        SubscriptionChangePolicy::assertCanScheduleChange($family, $planCode, $billing);

        $plan = SubscriptionPlan::query()
            ->where('code', $planCode)
            ->where('is_active', true)
            ->firstOrFail();

        return DB::transaction(function () use ($family, $user, $plan, $billing) {
            $subscription = $family->subscription;
            $subscription->update([
                'pending_plan_code' => $plan->code,
                'pending_billing' => $billing,
                // Un cambio programado implica querer renovar al nuevo plan.
                'cancelled_at' => null,
                'status' => $subscription->status === 'cancelled' ? 'active' : $subscription->status,
            ]);
            $subscription->refresh();

            $when = $subscription->accessUntilDate() ?? 'el vencimiento';
            $billingLabel = $billing === 'yearly' ? 'anual' : 'mensual';

            $this->notifications->notifyFamilyById(
                (string) $family->id,
                null,
                'subscription_plan_change_scheduled',
                'Cambio de plan programado',
                "{$user->name} programó el cambio a {$plan->name} ({$billingLabel}). "
                ."Se cobrará y aplicará el {$when}. Hasta entonces sigue el plan actual.",
                [
                    'entity_type' => 'subscription',
                    'entity_id' => $subscription->id,
                    'pending_plan_code' => $plan->code,
                    'pending_billing' => $billing,
                ],
            );

            return [
                'pending_plan_code' => $plan->code,
                'pending_billing' => $billing,
                'pending_change_at' => $subscription->accessUntilDate(),
                'plan_name' => $plan->name,
            ];
        });
    }

    /**
     * @return array{ok: bool}
     */
    public function cancel(Family $family, User $user): array
    {
        $subscription = $family->subscription;
        if ($subscription === null || $subscription->pending_plan_code === null) {
            return ['ok' => false];
        }

        $previous = $subscription->pending_plan_code;
        $subscription->update([
            'pending_plan_code' => null,
            'pending_billing' => null,
        ]);

        $this->notifications->notifyFamilyById(
            (string) $family->id,
            null,
            'subscription_plan_change_cancelled',
            'Cambio de plan cancelado',
            "{$user->name} canceló el cambio programado a {$previous}. Se renovará el plan actual.",
            [
                'entity_type' => 'subscription',
                'entity_id' => $subscription->id,
            ],
        );

        return ['ok' => true];
    }
}
