<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Rewards;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\FamilyRewardItem */
final class FamilyRewardItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'title' => $this->title,
            'cost_points' => (int) $this->cost_points,
            'active' => (bool) $this->active,
        ];
    }
}
