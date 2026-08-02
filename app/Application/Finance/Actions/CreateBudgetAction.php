<?php

declare(strict_types=1);

namespace App\Application\Finance\Actions;

use App\Models\Budget;
use App\Models\User;
use App\Services\FamilyNotificationService;
use App\Support\FamilyRealtime;
use Illuminate\Support\Str;

final class CreateBudgetAction
{
    public function __construct(
        private readonly FamilyNotificationService $notifications,
    ) {}

    /**
     * @param  array{
     *   name: string,
     *   amount: float|int|string,
     *   currency?: string|null,
     *   period?: string|null,
     *   start_date: string,
     *   end_date: string
     * }  $validated
     */
    public function execute(User $actor, array $validated): Budget
    {
        $budget = Budget::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $actor->family_id,
            'name' => $validated['name'],
            'amount' => $validated['amount'],
            'currency' => $validated['currency'] ?? 'COP',
            'period' => $validated['period'] ?? 'monthly',
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
        ]);

        $this->notifications->notifyFamily(
            $actor,
            'finance_budget',
            'Presupuesto creado',
            "{$actor->name} creó el presupuesto «{$budget->name}»",
            ['entity_type' => 'budget', 'entity_id' => $budget->id],
        );

        FamilyRealtime::financeChanged(
            familyId: (string) $actor->family_id,
            entityType: 'budget',
            entityId: (string) $budget->id,
            action: 'created',
            actorId: (int) $actor->id,
        );

        return $budget;
    }
}
