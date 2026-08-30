<?php

declare(strict_types=1);

namespace App\Application\Family\Actions;

use App\Models\FamilyEvent;
use App\Models\User;

final class UpdateFamilyEventAction
{
    /** @param array<string, mixed> $data */
    public function execute(User $actor, FamilyEvent $event, array $data): FamilyEvent
    {
        abort_if((string) $actor->family_id !== (string) $event->family_id, 403);

        $event->fill(array_intersect_key($data, array_flip([
            'title', 'description', 'starts_at', 'ends_at', 'location', 'status',
            'kind', 'child_user_id', 'budget_amount', 'currency',
        ])));
        $event->save();

        return $event->load(['creator:id,name', 'guests', 'child:id,name', 'expenses']);
    }
}
