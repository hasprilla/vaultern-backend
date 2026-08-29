<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Rewards;

use App\Application\Rewards\CreateRewardItemAction;
use App\Application\Rewards\ListRewardItemsQuery;
use App\Application\Rewards\UpdateRewardItemAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Rewards\FamilyRewardItemResource;
use App\Models\FamilyRewardItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RewardItemController extends Controller
{
    public function __construct(
        private readonly ListRewardItemsQuery $list,
        private readonly CreateRewardItemAction $create,
        private readonly UpdateRewardItemAction $update,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $items = $this->list->execute(
            $request->user(),
            $request->boolean('active_only', true),
        );

        return FamilyRewardItemResource::collection($items)->response();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'cost_points' => ['required', 'integer', 'min:1'],
        ]);

        return response()->json([
            'data' => new FamilyRewardItemResource(
                $this->create->execute($request->user(), $data),
            ),
        ], 201);
    }

    public function update(Request $request, string $item): JsonResponse
    {
        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:120'],
            'cost_points' => ['sometimes', 'integer', 'min:1'],
            'active' => ['sometimes', 'boolean'],
        ]);
        $model = FamilyRewardItem::query()->findOrFail($item);

        return response()->json([
            'data' => new FamilyRewardItemResource(
                $this->update->execute($request->user(), $model, $data),
            ),
        ]);
    }
}
