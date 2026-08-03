<?php

declare(strict_types=1);

namespace App\Application\Dashboard\Actions;

use App\Models\Transaction;
use App\Models\User;
use App\Services\ChildGuardianService;
use App\Services\TaskVisibilityService;
use App\Support\FamilyRealtime;
use Illuminate\Support\Facades\Cache;

final class GetDashboardAnalyticsAction
{
    public function __construct(
        private readonly ChildGuardianService $guardians,
        private readonly TaskVisibilityService $taskVisibility,
    ) {}

    /**
     * @return array{
     *   period: string,
     *   tasks_total: int,
     *   tasks_done: int,
     *   tasks_overdue: int,
     *   completion_rate: float|int,
     *   total_expenses: float,
     *   total_income: float,
     *   completed_by_day: list<array{date: string, label: string, completed: int}>
     * }
     */
    public function execute(User $user, string $period): array
    {
        $familyId = (string) $user->family_id;
        $userId = (int) $user->id;
        $canFinance = $user->canManageFinances();
        $cacheKey = FamilyRealtime::analyticsCacheKey($familyId, $period, $userId, $canFinance).':v2';

        return Cache::remember($cacheKey, now()->addSeconds(45), function () use ($user, $period, $canFinance) {
            $days = match ($period) {
                'monthly' => 30,
                'quarterly' => 90,
                'yearly', 'annual' => 365,
                default => 7,
            };

            $from = now()->subDays($days)->startOfDay();

            $scoped = $this->taskVisibility->scopedQuery($user);

            $tasksTotal = (clone $scoped)->where('created_at', '>=', $from)->count();
            $tasksDone = (clone $scoped)->where('status', 'done')->where('completed_at', '>=', $from)->count();
            $tasksOverdue = (clone $scoped)
                ->where('status', '!=', 'done')
                ->where(function ($builder) {
                    $builder->where('status', 'overdue')
                        ->orWhere(function ($nested) {
                            $nested->whereNotNull('due_date')
                                ->whereDate('due_date', '<', now());
                        });
                })
                ->count();

            $expenses = 0.0;
            $income = 0.0;
            if ($canFinance) {
                $childIds = $this->guardians->childIdsFor($user);
                $ids = $childIds === [] ? [-1] : $childIds;
                $expenses = (float) Transaction::query()
                    ->where('type', 'expense')
                    ->where('transaction_date', '>=', $from)
                    ->whereIn('child_id', $ids)
                    ->sum('amount');
                $income = (float) Transaction::query()
                    ->where('type', 'income')
                    ->where('transaction_date', '>=', $from)
                    ->whereIn('child_id', $ids)
                    ->sum('amount');
            }

            $weekday = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
            $completedByDay = [];
            for ($i = 6; $i >= 0; $i--) {
                $day = now()->subDays($i)->startOfDay();
                $count = (clone $scoped)
                    ->where('status', 'done')
                    ->whereDate('completed_at', $day->toDateString())
                    ->count();
                $completedByDay[] = [
                    'date' => $day->toDateString(),
                    'label' => $weekday[(int) $day->format('w')],
                    'completed' => $count,
                ];
            }

            return [
                'period' => $period,
                'tasks_total' => $tasksTotal,
                'tasks_done' => $tasksDone,
                'tasks_overdue' => $tasksOverdue,
                'completion_rate' => $tasksTotal > 0 ? round($tasksDone / $tasksTotal * 100, 1) : 0,
                'total_expenses' => $expenses,
                'total_income' => $income,
                'completed_by_day' => $completedByDay,
            ];
        });
    }
}
