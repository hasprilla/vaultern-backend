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
 * Al completar una tarea de un hijo: +10 puntos y +500 COP de mesada virtual.
 */
final class AwardTaskRewardAction
{
    public const POINTS_PER_TASK = 10;

    public const ALLOWANCE_PER_TASK = 500.0;

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

        DB::transaction(function () use ($familyId, $childId, $sourceId, $task) {
            $exists = ChildRewardEvent::query()
                ->where('family_id', $familyId)
                ->where('source_type', 'task_completed')
                ->where('source_id', $sourceId)
                ->exists();
            if ($exists) {
                return;
            }

            ChildRewardEvent::query()->create([
                'family_id' => $familyId,
                'child_user_id' => $childId,
                'source_type' => 'task_completed',
                'source_id' => $sourceId,
                'points_delta' => self::POINTS_PER_TASK,
                'allowance_delta' => self::ALLOWANCE_PER_TASK,
                'note' => 'Tarea: '.$task->title,
            ]);

            $balance = ChildRewardBalance::query()->firstOrCreate(
                ['family_id' => $familyId, 'child_user_id' => $childId],
                ['points' => 0, 'allowance_balance' => 0, 'currency' => 'COP'],
            );

            $balance->update([
                'points' => (int) $balance->points + self::POINTS_PER_TASK,
                'allowance_balance' => (float) $balance->allowance_balance + self::ALLOWANCE_PER_TASK,
            ]);
        });
    }
}
