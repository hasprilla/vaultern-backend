<?php

declare(strict_types=1);

namespace App\Application\Rewards;

use App\Models\FamilyRewardItem;
use App\Models\FamilyRewardSetting;
use App\Support\SchemaCompat;

final class ResolveFamilyRewardSettingsAction
{
    /** @return array{points_per_task: int, allowance_per_task: float} */
    public function execute(string $familyId): array
    {
        $row = $familyId !== '' && SchemaCompat::hasTable('family_reward_settings')
            ? FamilyRewardSetting::query()->where('family_id', $familyId)->first()
            : null;

        return [
            'points_per_task' => (int) ($row?->points_per_task ?? AwardTaskRewardAction::POINTS_PER_TASK),
            'allowance_per_task' => (float) ($row?->allowance_per_task ?? AwardTaskRewardAction::ALLOWANCE_PER_TASK),
        ];
    }

    /** @return list<array{id: string, title: string, cost_points: int}> */
    public function activeItems(string $familyId): array
    {
        if ($familyId === '' || ! SchemaCompat::hasTable('family_reward_items')) {
            return [];
        }

        return FamilyRewardItem::query()
            ->where('family_id', $familyId)
            ->where('active', true)
            ->orderBy('title')
            ->get(['id', 'title', 'cost_points'])
            ->map(fn (FamilyRewardItem $i) => [
                'id' => (string) $i->id,
                'title' => $i->title,
                'cost_points' => (int) $i->cost_points,
            ])->values()->all();
    }
}
