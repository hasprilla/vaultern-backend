<?php

declare(strict_types=1);

namespace App\Application\Dashboard\Actions;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Estadísticas de tareas orientadas a padres: logros diarios (7 días) + resumen.
 *
 * @phpstan-type DailyBucket array{date: string, label: string, completed: int, created: int}
 * @phpstan-type TaskDailyStats array{
 *   days: list<DailyBucket>,
 *   completed_today: int,
 *   completed_week: int,
 *   created_week: int,
 *   overdue: int,
 *   pending: int,
 *   best_day: array{date: string, label: string, completed: int}|null,
 *   by_assignee: list<array{user_id: string, name: string, completed: int}>
 * }
 */
final class GetTaskDailyStatsAction
{
    /**
     * @return TaskDailyStats
     */
    public function execute(User $viewer): array
    {
        $familyId = $viewer->family_id;
        if ($familyId === null) {
            return $this->empty();
        }

        $today = Carbon::today();
        $start = $today->copy()->subDays(6)->startOfDay();
        $end = $today->copy()->endOfDay();

        $completedRows = Task::query()
            ->where('family_id', $familyId)
            ->where('status', 'done')
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$start, $end])
            ->get(['id', 'completed_at', 'assigned_to']);

        $createdRows = Task::query()
            ->where('family_id', $familyId)
            ->whereBetween('created_at', [$start, $end])
            ->get(['id', 'created_at']);

        $completedByDay = [];
        $createdByDay = [];
        for ($i = 0; $i < 7; $i++) {
            $d = $start->copy()->addDays($i)->toDateString();
            $completedByDay[$d] = 0;
            $createdByDay[$d] = 0;
        }

        foreach ($completedRows as $task) {
            $d = Carbon::parse($task->completed_at)->toDateString();
            if (isset($completedByDay[$d])) {
                $completedByDay[$d]++;
            }
        }

        foreach ($createdRows as $task) {
            $d = Carbon::parse($task->created_at)->toDateString();
            if (isset($createdByDay[$d])) {
                $createdByDay[$d]++;
            }
        }

        $weekday = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
        $days = [];
        $best = null;
        foreach ($completedByDay as $date => $count) {
            $carbon = Carbon::parse($date);
            $label = $weekday[(int) $carbon->format('w')];
            $bucket = [
                'date' => $date,
                'label' => $label,
                'completed' => $count,
                'created' => $createdByDay[$date] ?? 0,
            ];
            $days[] = $bucket;
            if ($best === null || $count > $best['completed']) {
                $best = [
                    'date' => $date,
                    'label' => $label,
                    'completed' => $count,
                ];
            }
        }

        if ($best !== null && $best['completed'] === 0) {
            $best = null;
        }

        $assigneeCounts = [];
        foreach ($completedRows as $task) {
            if ($task->assigned_to === null) {
                continue;
            }
            $id = (string) $task->assigned_to;
            $assigneeCounts[$id] = ($assigneeCounts[$id] ?? 0) + 1;
        }
        arsort($assigneeCounts);
        $topIds = array_slice(array_keys($assigneeCounts), 0, 5);
        $names = $topIds === []
            ? collect()
            : User::query()->whereIn('id', $topIds)->pluck('name', 'id');

        $byAssignee = [];
        foreach ($topIds as $id) {
            $byAssignee[] = [
                'user_id' => $id,
                'name' => $names[$id] ?? 'Miembro',
                'completed' => (int) $assigneeCounts[$id],
            ];
        }

        $completedToday = $completedByDay[$today->toDateString()] ?? 0;
        $completedWeek = array_sum($completedByDay);

        $overdue = Task::query()
            ->where('family_id', $familyId)
            ->where('status', '!=', 'done')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->count();

        $pending = Task::query()
            ->where('family_id', $familyId)
            ->where('status', 'pending')
            ->count();

        return [
            'days' => $days,
            'completed_today' => $completedToday,
            'completed_week' => $completedWeek,
            'created_week' => array_sum($createdByDay),
            'overdue' => $overdue,
            'pending' => $pending,
            'best_day' => $best,
            'by_assignee' => $byAssignee,
        ];
    }

    /**
     * @return TaskDailyStats
     */
    private function empty(): array
    {
        return [
            'days' => [],
            'completed_today' => 0,
            'completed_week' => 0,
            'created_week' => 0,
            'overdue' => 0,
            'pending' => 0,
            'best_day' => null,
            'by_assignee' => [],
        ];
    }
}
