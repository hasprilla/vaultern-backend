<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Rewards;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\FamilyRewardSetting */
final class FamilyRewardSettingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'points_per_task' => (int) $this->points_per_task,
            'allowance_per_task' => (float) $this->allowance_per_task,
        ];
    }
}
