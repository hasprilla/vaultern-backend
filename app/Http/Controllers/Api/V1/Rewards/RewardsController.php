<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Rewards;

use App\Http\Controllers\Controller;
use App\Models\ChildRewardBalance;
use App\Models\ChildRewardEvent;
use App\Models\User;
use App\Support\SchemaCompat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RewardsController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $actor = $request->user();
        if ($actor === null || $actor->family_id === null) {
            return response()->json(['message' => 'Sin familia activa.'], 403);
        }

        if (! SchemaCompat::hasTable('child_reward_balances')) {
            return response()->json(['data' => ['children' => [], 'events' => []]]);
        }

        $balances = ChildRewardBalance::query()
            ->where('family_id', $actor->family_id)
            ->orderByDesc('points')
            ->get();

        $childIds = $balances->pluck('child_user_id')->all();
        $names = User::query()->whereIn('id', $childIds)->pluck('name', 'id');

        $children = $balances->map(fn (ChildRewardBalance $b) => [
            'child_user_id' => (string) $b->child_user_id,
            'name' => $names[$b->child_user_id] ?? 'Hijo',
            'points' => (int) $b->points,
            'allowance_balance' => (float) $b->allowance_balance,
            'currency' => $b->currency,
        ])->values()->all();

        $events = ChildRewardEvent::query()
            ->where('family_id', $actor->family_id)
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

        return response()->json([
            'data' => [
                'points_per_task' => 10,
                'allowance_per_task' => 500,
                'currency' => 'COP',
                'children' => $children,
                'events' => $events,
            ],
        ]);
    }
}
