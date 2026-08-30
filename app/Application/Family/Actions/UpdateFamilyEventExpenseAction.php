<?php

declare(strict_types=1);

namespace App\Application\Family\Actions;

use App\Models\FamilyEvent;
use App\Models\FamilyEventExpense;
use App\Models\User;

final class UpdateFamilyEventExpenseAction
{
    /** @param array<string, mixed> $data */
    public function execute(
        User $actor,
        FamilyEvent $event,
        FamilyEventExpense $expense,
        array $data,
    ): FamilyEventExpense {
        abort_if((string) $actor->family_id !== (string) $event->family_id, 403);
        abort_if((string) $expense->event_id !== (string) $event->id, 404);

        $expense->fill(array_intersect_key($data, array_flip([
            'title', 'amount', 'currency', 'category', 'paid',
        ])));
        $expense->save();

        return $expense;
    }
}
