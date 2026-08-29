<?php

declare(strict_types=1);

namespace App\Application\Rewards;

use App\Models\FamilyRewardItem;
use App\Models\User;
use Illuminate\Support\Collection;

final class ListRewardItemsQuery
{
    /** @return Collection<int, FamilyRewardItem> */
    public function execute(User $actor, bool $activeOnly = true): Collection
    {
        abort_if($actor->family_id === null, 403);

        $query = FamilyRewardItem::query()
            ->where('family_id', $actor->family_id)
            ->orderBy('title');

        if ($activeOnly) {
            $query->where('active', true);
        }

        return $query->get();
    }
}
