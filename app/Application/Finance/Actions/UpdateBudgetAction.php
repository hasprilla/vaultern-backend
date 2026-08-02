<?php

declare(strict_types=1);

namespace App\Application\Finance\Actions;

use App\Models\Budget;
use App\Models\User;
use App\Services\FamilyNotificationService;
use App\Support\FamilyRealtime;

final class UpdateBudgetAction
{
    public function __construct(
        private readonly FamilyNotificationService $notifications,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(User $actor, Budget $budget, array $validated): Budget
    {
        $budget->update($validated);
        $budget = $budget->fresh() ?? $budget;

        $this->notifications->notifyFamily(
            $actor,
            'finance_budget',
            'Presupuesto actualizado',
            "{$actor->name} actualizó el presupuesto «{$budget->name}»",
            ['entity_type' => 'budget', 'entity_id' => $budget->id],
        );

        FamilyRealtime::financeChanged(
            familyId: (string) $actor->family_id,
            entityType: 'budget',
            entityId: (string) $budget->id,
            action: 'updated',
            actorId: (int) $actor->id,
        );

        return $budget;
    }
}
