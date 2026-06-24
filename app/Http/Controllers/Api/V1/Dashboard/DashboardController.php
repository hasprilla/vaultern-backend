<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function analytics(Request $request): JsonResponse
    {
        $period = $request->query('period', 'weekly');
        $days = match ($period) {
            'monthly'   => 30,
            'quarterly' => 90,
            'yearly', 'annual' => 365,
            default     => 7,
        };

        $from = now()->subDays($days)->startOfDay();

        $tasksTotal = Task::query()->where('created_at', '>=', $from)->count();
        $tasksDone  = Task::query()->where('status', 'done')->where('completed_at', '>=', $from)->count();
        $tasksOverdue = Task::query()
            ->where('status', '!=', 'done')
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->count();

        $expenses = 0.0;
        if ($request->user()->canManageFinances()) {
            $expenses = (float) Transaction::query()
                ->where('type', 'expense')
                ->where('transaction_date', '>=', $from)
                ->sum('amount');
        }

        return response()->json([
            'data' => [
                'period'         => $period,
                'tasks_total'    => $tasksTotal,
                'tasks_done'     => $tasksDone,
                'tasks_overdue'  => $tasksOverdue,
                'completion_rate'=> $tasksTotal > 0 ? round($tasksDone / $tasksTotal * 100, 1) : 0,
                'total_expenses' => $expenses,
            ],
        ]);
    }
}
