<?php

declare(strict_types=1);

namespace App\Application\Finance\Actions;

use App\Models\Budget;
use App\Models\User;
use App\Services\FamilyNotificationService;
use App\Support\FamilyRealtime;

final class DeleteBudgetAction
{
    public function __construct(
        private readonly FamilyNotificationService $notifications,
    ) {}

    public function execute(User $actor, Budget $budget): void
    {
        $budgetId = (string) $budget->id;
        $name = $budget->name;
        $budget->delete();

        $this->notifications->notifyFamily(
            $actor,
            'finance_budget',
            'Presupuesto eliminado',
            "{$actor->name} eliminó el presupuesto «{$name}»",
            ['entity_type' => 'budget', 'entity_id' => $budgetId],
        );

        FamilyRealtime::financeChanged(
            familyId: (string) $actor->family_id,
            entityType: 'budget',
            entityId: $budgetId,
            action: 'deleted',
            actorId: (int) $actor->id,
        );
    }
}
