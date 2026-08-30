<?php

declare(strict_types=1);

namespace App\Application\Family\Actions;

use App\Models\FamilyEvent;
use App\Models\FamilyEventExpense;
use App\Models\User;
use Illuminate\Support\Str;

final class CreateFamilyEventExpenseAction
{
    /** @param array<string, mixed> $data */
    public function execute(User $actor, FamilyEvent $event, array $data): FamilyEventExpense
    {
        abort_if((string) $actor->family_id !== (string) $event->family_id, 403);
        abort_if($event->kind !== 'child_party', 422, 'Solo fiestas de hijos admiten gastos');

        return FamilyEventExpense::query()->create([
            'id' => (string) Str::uuid(),
            'event_id' => $event->id,
            'created_by' => $actor->id,
            'title' => $data['title'],
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? $event->currency ?? 'COP',
            'category' => $data['category'] ?? null,
            'paid' => (bool) ($data['paid'] ?? false),
        ]);
    }
}
