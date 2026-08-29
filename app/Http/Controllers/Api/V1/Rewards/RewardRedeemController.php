<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Rewards;

use App\Application\Rewards\RedeemRewardAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RewardRedeemController extends Controller
{
    public function __construct(private readonly RedeemRewardAction $redeem) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'child_user_id' => ['required', 'integer', 'exists:users,id'],
            'item_id' => ['required', 'uuid', 'exists:family_reward_items,id'],
        ]);

        $result = $this->redeem->execute($request->user(), $data);

        return response()->json([
            'data' => [
                'event_id' => (string) $result['event']->id,
                'child_user_id' => (string) $result['balance']->child_user_id,
                'points' => (int) $result['balance']->points,
                'item_id' => (string) $result['item']->id,
                'cost_points' => (int) $result['item']->cost_points,
            ],
        ], 201);
    }
}
