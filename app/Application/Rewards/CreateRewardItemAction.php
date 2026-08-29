<?php

declare(strict_types=1);

namespace App\Application\Rewards;

use App\Models\FamilyRewardItem;
use App\Models\User;

final class CreateRewardItemAction
{
    /** @param array{title: string, cost_points: int} $data */
    public function execute(User $actor, array $data): FamilyRewardItem
    {
        abort_if($actor->family_id === null, 403);
        abort_if(! $actor->canManageTasks(), 403);

        return FamilyRewardItem::query()->create([
            'family_id' => $actor->family_id,
            'title' => $data['title'],
            'cost_points' => (int) $data['cost_points'],
            'active' => true,
        ]);
    }
}
