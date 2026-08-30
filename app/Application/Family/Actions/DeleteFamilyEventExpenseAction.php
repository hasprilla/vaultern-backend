<?php

declare(strict_types=1);

namespace App\Application\Family\Actions;

use App\Models\FamilyEvent;
use App\Models\FamilyEventExpense;
use App\Models\User;

final class DeleteFamilyEventExpenseAction
{
    public function execute(User $actor, FamilyEvent $event, FamilyEventExpense $expense): void
    {
        abort_if((string) $actor->family_id !== (string) $event->family_id, 403);
        abort_if((string) $expense->event_id !== (string) $event->id, 404);
        $expense->delete();
    }
}
