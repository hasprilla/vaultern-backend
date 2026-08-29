<?php

declare(strict_types=1);

namespace App\Application\Rewards;

use App\Models\FamilyRewardItem;
use App\Models\User;

final class UpdateRewardItemAction
{
    /** @param array{title?: string, cost_points?: int, active?: bool} $data */
    public function execute(User $actor, FamilyRewardItem $item, array $data): FamilyRewardItem
    {
        abort_if($actor->family_id === null, 403);
        abort_if(! $actor->canManageTasks(), 403);
        abort_if((string) $item->family_id !== (string) $actor->family_id, 404);

        $item->fill(array_filter([
            'title' => $data['title'] ?? null,
            'cost_points' => isset($data['cost_points']) ? (int) $data['cost_points'] : null,
            'active' => array_key_exists('active', $data) ? (bool) $data['active'] : null,
        ], fn ($v) => $v !== null));

        $item->save();

        return $item->refresh();
    }
}
