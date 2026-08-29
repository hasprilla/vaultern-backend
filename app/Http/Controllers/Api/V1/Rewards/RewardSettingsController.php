<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Rewards;

use App\Application\Rewards\UpsertRewardSettingsAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Rewards\FamilyRewardSettingResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RewardSettingsController extends Controller
{
    public function __construct(private readonly UpsertRewardSettingsAction $upsert) {}

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'points_per_task' => ['required', 'integer', 'min:1'],
            'allowance_per_task' => ['required', 'numeric', 'min:0'],
        ]);

        return response()->json([
            'data' => new FamilyRewardSettingResource(
                $this->upsert->execute($request->user(), $data),
            ),
        ]);
    }
}
