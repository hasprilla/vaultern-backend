<?php

declare(strict_types=1);

namespace App\Application\Family\Actions;

use App\Models\Family;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;

final class ExportFamilyActivityPdfAction
{
    /**
     * @return array{ok: true, pdf: string, filename: string}|array{ok: false, status: int, message: string}
     */
    public function execute(User $actor, string $period = 'weekly'): array
    {
        if ($actor->family_id === null) {
            return ['ok' => false, 'status' => 403, 'message' => 'Sin familia activa.'];
        }

        $family = Family::query()->find($actor->family_id);
        if ($family === null) {
            return ['ok' => false, 'status' => 404, 'message' => 'Familia no encontrada.'];
        }

        $end = Carbon::now()->endOfDay();
        $start = match ($period) {
            'monthly' => Carbon::now()->startOfMonth(),
            default => Carbon::now()->subDays(7)->startOfDay(),
        };

        $tasksDone = Task::query()
            ->where('family_id', $family->id)
            ->where('status', 'done')
            ->whereBetween('completed_at', [$start, $end])
            ->count();

        $tasksPending = Task::query()
            ->where('family_id', $family->id)
            ->where('status', '!=', 'done')
            ->count();

        $expenses = (float) Transaction::query()
            ->where('family_id', $family->id)
            ->where('type', 'expense')
            ->whereBetween('transaction_date', [$start, $end])
            ->sum('amount');

        $schoolTasks = Task::query()
            ->where('family_id', $family->id)
            ->where('is_school', true)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $recentCompletedTasks = Task::query()
            ->where('family_id', $family->id)
            ->where('status', 'done')
            ->whereBetween('completed_at', [$start, $end])
            ->orderByDesc('completed_at')
            ->limit(15)
            ->get(['title', 'completed_at']);

        $recentExpenses = Transaction::query()
            ->where('family_id', $family->id)
            ->where('type', 'expense')
            ->whereBetween('transaction_date', [$start, $end])
            ->orderByDesc('transaction_date')
            ->limit(15)
            ->get(['description', 'category', 'amount', 'transaction_date']);

        try {
            $pdf = Pdf::loadView('exports.family_activity', [
                'family' => $family,
                'actor' => $actor,
                'periodLabel' => $period === 'monthly' ? 'Mes actual' : 'Últimos 7 días',
                'start' => $start,
                'end' => $end,
                'tasksDone' => $tasksDone,
                'tasksPending' => $tasksPending,
                'expenses' => $expenses,
                'schoolTasks' => $schoolTasks,
                'recentCompletedTasks' => $recentCompletedTasks,
                'recentExpenses' => $recentExpenses,
                'generatedAt' => now(),
            ])->output();
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'status' => 500,
                'message' => 'No se pudo generar el PDF: '.$e->getMessage(),
            ];
        }

        $filename = 'zumifly-actividad-'.now()->format('Ymd').'.pdf';

        return ['ok' => true, 'pdf' => $pdf, 'filename' => $filename];
    }
}
