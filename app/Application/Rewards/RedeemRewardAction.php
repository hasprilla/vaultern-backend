<?php

declare(strict_types=1);

namespace App\Application\Rewards;

use App\Models\ChildRewardBalance;
use App\Models\ChildRewardEvent;
use App\Models\FamilyRewardItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class RedeemRewardAction
{
    /**
     * @param  array{child_user_id: int, item_id: string}  $data
     * @return array{event: ChildRewardEvent, balance: ChildRewardBalance, item: FamilyRewardItem}
     */
    public function execute(User $actor, array $data): array
    {
        abort_if($actor->family_id === null, 403);
        abort_if(! $actor->canManageTasks(), 403);

        $familyId = (string) $actor->family_id;
        $childId = (int) $data['child_user_id'];
        $child = User::query()->find($childId);
        abort_if($child === null || (string) $child->family_id !== $familyId || $child->role !== 'hijo', 404);

        $item = FamilyRewardItem::query()->find($data['item_id']);
        abort_if($item === null || (string) $item->family_id !== $familyId || ! $item->active, 404);

        return DB::transaction(function () use ($familyId, $childId, $item) {
            $balance = ChildRewardBalance::query()->firstOrCreate(
                ['family_id' => $familyId, 'child_user_id' => $childId],
                ['points' => 0, 'allowance_balance' => 0, 'currency' => 'COP'],
            );
            abort_if((int) $balance->points < (int) $item->cost_points, 422, 'Puntos insuficientes');

            $cost = (int) $item->cost_points;
            $event = ChildRewardEvent::query()->create([
                'family_id' => $familyId,
                'child_user_id' => $childId,
                'source_type' => 'redeem',
                'source_id' => (string) Str::uuid(),
                'points_delta' => -$cost,
                'allowance_delta' => 0,
                'note' => 'Canje: '.$item->title,
            ]);
            $balance->update(['points' => (int) $balance->points - $cost]);

            return ['event' => $event, 'balance' => $balance->refresh(), 'item' => $item];
        });
    }
}
