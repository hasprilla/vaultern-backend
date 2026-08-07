<?php

declare(strict_types=1);

namespace App\Application\Rewards;

use App\Models\ChildRewardBalance;
use App\Models\User;
use App\Support\SchemaCompat;

final class GetRewardsSummaryAction
{
    public function __construct(private readonly MapRewardEventsAction $mapEvents) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(User $actor): array
    {
        if (! SchemaCompat::hasTable('child_reward_balances')) {
            return $this->empty();
        }

        $balances = ChildRewardBalance::query()
            ->where('family_id', $actor->family_id)
            ->orderByDesc('points')
            ->get();
        $names = User::query()->whereIn('id', $balances->pluck('child_user_id'))->pluck('name', 'id');

        $children = $balances->map(fn (ChildRewardBalance $b) => [
            'child_user_id' => (string) $b->child_user_id,
            'name' => $names[$b->child_user_id] ?? 'Hijo',
            'points' => (int) $b->points,
            'allowance_balance' => (float) $b->allowance_balance,
            'currency' => $b->currency,
        ])->values()->all();

        return [
            'points_per_task' => AwardTaskRewardAction::POINTS_PER_TASK,
            'allowance_per_task' => AwardTaskRewardAction::ALLOWANCE_PER_TASK,
            'currency' => 'COP',
            'children' => $children,
            'events' => $this->mapEvents->execute((string) $actor->family_id, $names),
        ];
    }

    /** @return array<string, mixed> */
    private function empty(): array
    {
        return [
            'points_per_task' => AwardTaskRewardAction::POINTS_PER_TASK,
            'allowance_per_task' => AwardTaskRewardAction::ALLOWANCE_PER_TASK,
            'currency' => 'COP',
            'children' => [],
            'events' => [],
        ];
    }
}
