<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\Transaction;
use App\Services\ChildGuardianService;
use App\Support\FamilyRealtime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function __construct(private readonly ChildGuardianService $guardians) {}

    public function analytics(Request $request): JsonResponse
    {
        $period = (string) $request->query('period', 'weekly');
        $familyId = (string) $request->user()->family_id;
        $userId = (int) $request->user()->id;
        $canFinance = $request->user()->canManageFinances();

        $cacheKey = FamilyRealtime::analyticsCacheKey($familyId, $period, $userId, $canFinance);

        $payload = Cache::remember($cacheKey, now()->addSeconds(45), function () use ($request, $period, $canFinance) {
            $days = match ($period) {
                'monthly'   => 30,
                'quarterly' => 90,
                'yearly', 'annual' => 365,
                default     => 7,
            };

            $from = now()->subDays($days)->startOfDay();

            $tasksTotal = Task::query()->where('created_at', '>=', $from)->count();
            $tasksDone = Task::query()->where('status', 'done')->where('completed_at', '>=', $from)->count();
            $tasksOverdue = Task::query()
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
            if ($canFinance) {
                $childIds = $this->guardians->childIdsFor($request->user());
                $expenses = (float) Transaction::query()
                    ->where('type', 'expense')
                    ->where('transaction_date', '>=', $from)
                    ->whereIn('child_id', $childIds === [] ? [-1] : $childIds)
                    ->sum('amount');
            }

            return [
                'period'          => $period,
                'tasks_total'     => $tasksTotal,
                'tasks_done'      => $tasksDone,
                'tasks_overdue'   => $tasksOverdue,
                'completion_rate' => $tasksTotal > 0 ? round($tasksDone / $tasksTotal * 100, 1) : 0,
                'total_expenses'  => $expenses,
            ];
        });

        return response()->json(['data' => $payload]);
    }
}
