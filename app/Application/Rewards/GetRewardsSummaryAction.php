<?php

declare(strict_types=1);

namespace App\Application\Rewards;

use App\Models\ChildRewardBalance;
use App\Models\User;
use App\Support\SchemaCompat;

final class GetRewardsSummaryAction
{
    public function __construct(
        private readonly MapRewardEventsAction $mapEvents,
        private readonly ResolveFamilyRewardSettingsAction $resolve,
    ) {}

    /** @return array<string, mixed> */
    public function execute(User $actor): array
    {
        $familyId = (string) ($actor->family_id ?? '');
        $settings = $this->resolve->execute($familyId);
        $items = $this->resolve->activeItems($familyId);

        if (! SchemaCompat::hasTable('child_reward_balances') || $familyId === '') {
            return $this->empty($settings, $items);
        }

        $balances = ChildRewardBalance::query()
            ->where('family_id', $familyId)
            ->orderByDesc('points')
            ->get();
        $names = User::query()->whereIn('id', $balances->pluck('child_user_id'))->pluck('name', 'id');

        return [
            'points_per_task' => $settings['points_per_task'],
            'allowance_per_task' => $settings['allowance_per_task'],
            'currency' => 'COP',
            'children' => $balances->map(fn (ChildRewardBalance $b) => [
                'child_user_id' => (string) $b->child_user_id,
                'name' => $names[$b->child_user_id] ?? 'Hijo',
                'points' => (int) $b->points,
                'allowance_balance' => (float) $b->allowance_balance,
                'currency' => $b->currency,
            ])->values()->all(),
            'events' => $this->mapEvents->execute($familyId, $names),
            'items' => $items,
        ];
    }

    /**
     * @param  array{points_per_task: int, allowance_per_task: float}  $settings
     * @param  list<array{id: string, title: string, cost_points: int}>  $items
     * @return array<string, mixed>
     */
    private function empty(array $settings, array $items): array
    {
        return [
            'points_per_task' => $settings['points_per_task'],
            'allowance_per_task' => $settings['allowance_per_task'],
            'currency' => 'COP',
            'children' => [],
            'events' => [],
            'items' => $items,
        ];
    }
}
