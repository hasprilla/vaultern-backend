<?php

declare(strict_types=1);

namespace App\Application\Rewards;

use App\Models\FamilyRewardSetting;
use App\Models\User;

final class UpsertRewardSettingsAction
{
    /** @param array{points_per_task: int, allowance_per_task: float|int|string} $data */
    public function execute(User $actor, array $data): FamilyRewardSetting
    {
        abort_if($actor->family_id === null, 403);
        abort_if(! $actor->canManageTasks(), 403);

        return FamilyRewardSetting::query()->updateOrCreate(
            ['family_id' => $actor->family_id],
            [
                'points_per_task' => (int) $data['points_per_task'],
                'allowance_per_task' => (float) $data['allowance_per_task'],
            ],
        );
    }
}
