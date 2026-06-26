<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Finance;

use App\Domains\Finance\Entities\FinanceReportPeriod;
use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\Transaction;
use App\Services\FamilyNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FinanceController extends Controller
{
    public function __construct(private readonly FamilyNotificationService $notifications) {}

    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->canManageFinances()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $query = Transaction::query()->with('child')->orderByDesc('transaction_date');

        if ($request->filled('child_id')) {
            $query->where('child_id', $request->integer('child_id'));
        }

        $transactions = $query->paginate(20);

        return response()->json($transactions);
    }

    public function store(Request $request): JsonResponse
    {
        if (! $request->user()->canManageFinances()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'amount'           => ['required', 'numeric', 'min:0'],
            'currency'         => ['nullable', 'string', 'size:3'],
            'type'             => ['required', 'in:income,expense'],
            'category'         => ['nullable', 'string', 'max:50'],
            'description'      => ['nullable', 'string', 'max:255'],
            'transaction_date' => ['required', 'date'],
            'child_id'         => ['nullable', 'integer', 'exists:users,id'],
        ]);

        if (isset($validated['child_id'])) {
            $child = \App\Models\User::query()->findOrFail($validated['child_id']);
            if ($child->family_id !== $request->user()->family_id || $child->role !== 'hijo') {
                return response()->json(['message' => 'Hijo no válido para esta familia'], 422);
            }
        }

        $transaction = Transaction::query()->create([
            'id'               => (string) Str::uuid(),
            'family_id'        => $request->user()->family_id,
            'user_id'          => $request->user()->id,
            'child_id'         => $validated['child_id'] ?? null,
            ...$validated,
            'currency'         => $validated['currency'] ?? 'COP',
        ]);

        $transaction->load('child');

        $label = $validated['type'] === 'income' ? 'Ingreso' : 'Gasto';
        $amount = number_format((float) $validated['amount'], 0, ',', '.');
        $this->notifications->notifyPartnerParents(
            $request->user(),
            'finance_transaction',
            "$label registrado",
            "{$request->user()->name} registró $label por \$$amount COP",
            ['entity_type' => 'transaction', 'entity_id' => $transaction->id],
        );

        return response()->json(['data' => $transaction], 201);
    }

    public function show(Request $request, string $transaction): JsonResponse
    {
        if (! $request->user()->canManageFinances()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json([
            'data' => Transaction::query()->findOrFail($transaction),
        ]);
    }

    public function budgetsIndex(Request $request): JsonResponse
    {
        if (! $request->user()->canManageFinances()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json(['data' => Budget::query()->get()]);
    }

    public function budgetsStore(Request $request): JsonResponse
    {
        if (! $request->user()->canManageFinances()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:120'],
            'amount'     => ['required', 'numeric', 'min:0'],
            'currency'   => ['nullable', 'string', 'size:3'],
            'period'     => ['nullable', 'in:weekly,monthly,quarterly,yearly'],
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $budget = Budget::query()->create([
            'id'        => (string) Str::uuid(),
            'family_id' => $request->user()->family_id,
            ...$validated,
            'currency'  => $validated['currency'] ?? 'COP',
            'period'    => $validated['period'] ?? 'monthly',
        ]);

        $this->notifications->notifyPartnerParents(
            $request->user(),
            'finance_budget',
            'Presupuesto creado',
            "{$request->user()->name} creó el presupuesto «{$budget->name}»",
            ['entity_type' => 'budget', 'entity_id' => $budget->id],
        );

        return response()->json(['data' => $budget], 201);
    }

    public function budgetsUpdate(Request $request, string $budget): JsonResponse
    {
        if (! $request->user()->canManageFinances()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $model = Budget::query()->findOrFail($budget);

        $validated = $request->validate([
            'name'   => ['sometimes', 'string', 'max:120'],
            'amount' => ['sometimes', 'numeric', 'min:0'],
        ]);

        $model->update($validated);

        $this->notifications->notifyPartnerParents(
            $request->user(),
            'finance_budget',
            'Presupuesto actualizado',
            "{$request->user()->name} actualizó el presupuesto «{$model->name}»",
            ['entity_type' => 'budget', 'entity_id' => $model->id],
        );

        return response()->json(['data' => $model->fresh()]);
    }

    public function weeklyReport(Request $request): JsonResponse
    {
        return $this->report($request, FinanceReportPeriod::Weekly);
    }

    public function monthlyReport(Request $request): JsonResponse
    {
        return $this->report($request, FinanceReportPeriod::Monthly);
    }

    public function quarterlyReport(Request $request): JsonResponse
    {
        return $this->report($request, FinanceReportPeriod::Quarterly);
    }

    public function annualReport(Request $request): JsonResponse
    {
        return $this->report($request, FinanceReportPeriod::Annual);
    }

    private function report(Request $request, FinanceReportPeriod $period): JsonResponse
    {
        if (! $request->user()->canManageFinances()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $from = now()->subDays($period->days())->startOfDay();

        $income = Transaction::query()
            ->where('type', 'income')
            ->where('transaction_date', '>=', $from)
            ->sum('amount');

        $expense = Transaction::query()
            ->where('type', 'expense')
            ->where('transaction_date', '>=', $from)
            ->sum('amount');

        return response()->json([
            'data' => [
                'period'  => $period->value,
                'label'   => $period->label(),
                'income'  => (float) $income,
                'expense' => (float) $expense,
                'balance' => (float) $income - (float) $expense,
                'from'    => $from->toDateString(),
                'to'      => now()->toDateString(),
            ],
        ]);
    }
}
