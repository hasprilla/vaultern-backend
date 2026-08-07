<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Rewards;

use App\Application\Rewards\GetRewardsSummaryAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RewardsController extends Controller
{
    public function __construct(private readonly GetRewardsSummaryAction $summary) {}

    public function summary(Request $request): JsonResponse
    {
        $actor = $request->user();
        if ($actor === null || $actor->family_id === null) {
            return response()->json(['message' => 'Sin familia activa.'], 403);
        }

        return response()->json(['data' => $this->summary->execute($actor)]);
    }
}
