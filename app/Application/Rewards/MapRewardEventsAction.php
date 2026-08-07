<?php

declare(strict_types=1);

namespace App\Application\Rewards;

use App\Models\ChildRewardEvent;
use Illuminate\Support\Collection;

final class MapRewardEventsAction
{
    /**
     * @param  Collection<int|string, string>  $names
     * @return list<array<string, mixed>>
     */
    public function execute(string $familyId, Collection $names): array
    {
        return ChildRewardEvent::query()
            ->where('family_id', $familyId)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->map(fn (ChildRewardEvent $e) => [
                'id' => (string) $e->id,
                'child_user_id' => (string) $e->child_user_id,
                'child_name' => $names[$e->child_user_id] ?? 'Hijo',
                'points_delta' => (int) $e->points_delta,
                'allowance_delta' => (float) $e->allowance_delta,
                'note' => $e->note,
                'created_at' => $e->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
