<?php

declare(strict_types=1);

namespace App\Application\Rewards;

use App\Models\ChildRewardBalance;
use App\Models\ChildRewardEvent;
use App\Models\Task;
use App\Models\User;
use App\Support\SchemaCompat;
use Illuminate\Support\Facades\DB;

/**
 * Al completar una tarea de un hijo: puntos y mesada virtual (settings o defaults).
 */
final class AwardTaskRewardAction
{
    public const POINTS_PER_TASK = 10;

    public const ALLOWANCE_PER_TASK = 500.0;

    public function __construct(private readonly ResolveFamilyRewardSettingsAction $resolve) {}

    public function execute(User $actor, Task $task): void
    {
        if (! SchemaCompat::hasTable('child_reward_balances')) {
            return;
        }

        $childId = $task->assigned_to !== null ? (int) $task->assigned_to : null;
        if ($childId === null) {
            return;
        }

        $child = User::query()->find($childId);
        if ($child === null || $child->role !== 'hijo') {
            return;
        }

        $familyId = (string) $actor->family_id;
        $sourceId = (string) $task->id;
        $settings = $this->resolve->execute($familyId);

        DB::transaction(function () use ($familyId, $childId, $sourceId, $task, $settings) {
            $exists = ChildRewardEvent::query()
                ->where('family_id', $familyId)
                ->where('source_type', 'task_completed')
                ->where('source_id', $sourceId)
                ->exists();
            if ($exists) {
                return;
            }

            $points = $settings['points_per_task'];
            $allowance = $settings['allowance_per_task'];

            ChildRewardEvent::query()->create([
                'family_id' => $familyId,
                'child_user_id' => $childId,
                'source_type' => 'task_completed',
                'source_id' => $sourceId,
                'points_delta' => $points,
                'allowance_delta' => $allowance,
                'note' => 'Tarea: '.$task->title,
            ]);

            $balance = ChildRewardBalance::query()->firstOrCreate(
                ['family_id' => $familyId, 'child_user_id' => $childId],
                ['points' => 0, 'allowance_balance' => 0, 'currency' => 'COP'],
            );

            $balance->update([
                'points' => (int) $balance->points + $points,
                'allowance_balance' => (float) $balance->allowance_balance + $allowance,
            ]);
        });
    }
}
