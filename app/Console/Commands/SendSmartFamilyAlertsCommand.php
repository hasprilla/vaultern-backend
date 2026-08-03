<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\Budget;
use App\Models\Family;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use App\Services\FamilyNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Alertas inteligentes: tareas vencidas y presupuestos al límite.
 * Idempotente por día vía notifications.data.alert_key.
 */
final class SendSmartFamilyAlertsCommand extends Command
{
    protected $signature = 'family:smart-alerts';

    protected $description = 'Envía alertas de tareas vencidas y presupuestos al 80%+';

    public function handle(FamilyNotificationService $notifications): int
    {
        $today = now()->toDateString();
        $sent = 0;

        Family::query()->select('id')->orderBy('id')->chunkById(50, function ($families) use ($notifications, $today, &$sent) {
            foreach ($families as $family) {
                $sent += $this->alertOverdueTasks($notifications, (string) $family->id, $today);
                $sent += $this->alertBudgets($notifications, (string) $family->id, $today);
            }
        });

        $this->info("Alertas enviadas: {$sent}");

        return self::SUCCESS;
    }

    private function alertOverdueTasks(FamilyNotificationService $notifications, string $familyId, string $today): int
    {
        $count = Task::query()
            ->where('family_id', $familyId)
            ->where('status', '!=', 'done')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->count();

        if ($count === 0) {
            return 0;
        }

        $key = "overdue_tasks:{$familyId}:{$today}";
        if ($this->alreadySent($familyId, $key)) {
            return 0;
        }

        $actor = $this->familyActor($familyId);
        if ($actor === null) {
            return 0;
        }

        $notifications->notifyFamilyById(
            $familyId,
            0,
            'reminder_overdue_tasks',
            'Tareas atrasadas',
            $count === 1
                ? 'Hay 1 tarea vencida en tu familia.'
                : "Hay {$count} tareas vencidas en tu familia.",
            [
                'entity_type' => 'tasks',
                'alert_key' => $key,
                'count' => $count,
            ],
        );

        return 1;
    }

    private function alertBudgets(FamilyNotificationService $notifications, string $familyId, string $today): int
    {
        $budgets = Budget::query()
            ->where('family_id', $familyId)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->get();

        $sent = 0;
        foreach ($budgets as $budget) {
            $spent = (float) Transaction::query()
                ->where('family_id', $familyId)
                ->where('type', 'expense')
                ->whereDate('occurred_at', '>=', $budget->start_date)
                ->whereDate('occurred_at', '<=', $budget->end_date)
                ->sum('amount');

            $limit = (float) $budget->amount;
            if ($limit <= 0) {
                continue;
            }
            $ratio = $spent / $limit;
            if ($ratio < 0.8) {
                continue;
            }

            $pct = (int) round(min($ratio, 1) * 100);
            $key = "budget_{$pct}:{$budget->id}:{$today}";
            if ($this->alreadySent($familyId, $key)) {
                continue;
            }

            $actor = $this->familyActor($familyId);
            if ($actor === null) {
                continue;
            }

            $notifications->notifyFamilyById(
                $familyId,
                0,
                'reminder_budget',
                'Presupuesto al límite',
                "«{$budget->name}» lleva el {$pct}% usado.",
                [
                    'entity_type' => 'budget',
                    'entity_id' => (string) $budget->id,
                    'alert_key' => $key,
                    'percent' => $pct,
                ],
            );
            $sent++;
        }

        return $sent;
    }

    private function alreadySent(string $familyId, string $alertKey): bool
    {
        return AppNotification::query()
            ->where('family_id', $familyId)
            ->where('created_at', '>=', Carbon::today())
            ->whereIn('type', ['reminder_overdue_tasks', 'reminder_budget'])
            ->get()
            ->contains(fn (AppNotification $n) => ($n->data['alert_key'] ?? null) === $alertKey);
    }

    private function familyActor(string $familyId): ?User
    {
        return User::query()
            ->where('family_id', $familyId)
            ->whereIn('role', ['padre', 'madre'])
            ->orderBy('id')
            ->first();
    }
}
