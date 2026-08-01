<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Finance;

use App\Domains\Finance\Entities\FinanceReportPeriod;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesPagination;
use App\Http\Controllers\Concerns\ReturnsForbidden;
use App\Http\Controllers\Concerns\ScopesByChildGuardianship;
use App\Models\Budget;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ChildGuardianService;
use App\Services\FamilyNotificationService;
use App\Support\FamilyRealtime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FinanceController extends Controller
{
    use ResolvesPagination;
    use ReturnsForbidden;
    use ScopesByChildGuardianship;

    public function __construct(
        private readonly FamilyNotificationService $notifications,
        private readonly ChildGuardianService $guardians,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if ($forbidden = $this->forbidUnlessAuthorized('viewAny', Transaction::class)) {
            return $forbidden;
        }

        $query = $this->transactionsForGuardian($request->user(), $this->guardians)
            ->with('child')
            ->orderByDesc('transaction_date');

        if ($request->filled('child_id')) {
            $childId = $request->integer('child_id');
            $this->assertCanAccessChild($request->user(), $this->guardians, $childId);
            $query->where('child_id', $childId);
        }

        $transactions = $query->paginate($this->perPage($request));

        return response()->json($transactions);
    }

    public function store(Request $request): JsonResponse
    {
        if ($forbidden = $this->forbidUnlessAuthorized('create', Transaction::class)) {
            return $forbidden;
        }

        $validated = $request->validate([
            'amount'           => ['required', 'numeric', 'min:0'],
            'currency'         => ['nullable', 'string', 'size:3'],
            'type'             => ['required', 'in:income,expense'],
            'category'         => ['nullable', 'string', 'max:50'],
            'description'      => ['nullable', 'string', 'max:255'],
            'transaction_date' => ['required', 'date'],
            'child_id'         => ['required', 'integer', 'exists:users,id'],
        ]);

        $child = User::query()->findOrFail($validated['child_id']);
        if ($child->family_id !== $request->user()->family_id || $child->role !== 'hijo') {
            return response()->json(['message' => 'Hijo no válido para esta familia'], 422);
        }
        $this->assertCanAccessChild($request->user(), $this->guardians, (int) $child->id);

        $transaction = Transaction::query()->create([
            'id'               => (string) Str::uuid(),
            'family_id'        => $request->user()->family_id,
            'user_id'          => $request->user()->id,
            'child_id'         => $validated['child_id'],
            ...$validated,
            'currency'         => $validated['currency'] ?? 'COP',
        ]);

        $transaction->load('child');

        $label = $validated['type'] === 'income' ? 'Ingreso' : 'Gasto';
        $amount = number_format((float) $validated['amount'], 0, ',', '.');
        $this->notifications->notifyChildGuardians(
            $request->user(),
            (int) $child->id,
            'finance_transaction',
            "$label registrado",
            "{$request->user()->name} registró $label de {$child->name} por \$$amount COP",
            ['entity_type' => 'transaction', 'entity_id' => $transaction->id],
        );

        FamilyRealtime::financeChanged(
            familyId: (string) $request->user()->family_id,
            entityType: 'transaction',
            entityId: (string) $transaction->id,
            action: 'created',
            actorId: (int) $request->user()->id,
            childId: (int) $child->id,
        );

        return response()->json(['data' => $transaction], 201);
    }

    public function update(Request $request, string $transaction): JsonResponse
    {
        $model = $this->transactionsForGuardian($request->user(), $this->guardians)->findOrFail($transaction);

        if ($forbidden = $this->forbidUnlessAuthorized('update', $model)) {
            return $forbidden;
        }

        $validated = $request->validate([
            'amount'           => ['sometimes', 'numeric', 'min:0'],
            'currency'         => ['sometimes', 'string', 'size:3'],
            'type'             => ['sometimes', 'in:income,expense'],
            'category'         => ['nullable', 'string', 'max:50'],
            'description'      => ['nullable', 'string', 'max:255'],
            'transaction_date' => ['sometimes', 'date'],
            'child_id'         => ['sometimes', 'integer', 'exists:users,id'],
        ]);

        if (isset($validated['child_id'])) {
            $this->assertCanAccessChild($request->user(), $this->guardians, (int) $validated['child_id']);
        }

        $model->update($validated);
        $model->load('child');

        if ($model->child_id !== null) {
            $this->notifications->notifyChildGuardians(
                $request->user(),
                (int) $model->child_id,
                'finance_transaction',
                'Transacción actualizada',
                "{$request->user()->name} actualizó una transacción de {$model->child?->name}",
                ['entity_type' => 'transaction', 'entity_id' => $model->id],
            );
        }

        FamilyRealtime::financeChanged(
            familyId: (string) $request->user()->family_id,
            entityType: 'transaction',
            entityId: (string) $model->id,
            action: 'updated',
            actorId: (int) $request->user()->id,
            childId: $model->child_id !== null ? (int) $model->child_id : null,
        );

        return response()->json(['data' => $model]);
    }

    public function show(Request $request, string $transaction): JsonResponse
    {
        $model = $this->transactionsForGuardian($request->user(), $this->guardians)->findOrFail($transaction);

        if ($forbidden = $this->forbidUnlessAuthorized('view', $model)) {
            return $forbidden;
        }

        return response()->json(['data' => $model]);
    }

    public function destroy(Request $request, string $transaction): JsonResponse
    {
        $model = $this->transactionsForGuardian($request->user(), $this->guardians)
            ->with('child')
            ->findOrFail($transaction);

        if ($forbidden = $this->forbidUnlessAuthorized('delete', $model)) {
            return $forbidden;
        }
        $description = $model->description ?? 'Transacción';
        $childId = $model->child_id !== null ? (int) $model->child_id : null;
        $childName = $model->child?->name;
        $model->delete();

        if ($childId !== null) {
            $this->notifications->notifyChildGuardians(
                $request->user(),
                $childId,
                'finance_transaction',
                'Transacción eliminada',
                "{$request->user()->name} eliminó «{$description}»".($childName ? " de {$childName}" : ''),
                ['entity_type' => 'transaction', 'entity_id' => $transaction],
            );
        }

        FamilyRealtime::financeChanged(
            familyId: (string) $request->user()->family_id,
            entityType: 'transaction',
            entityId: (string) $transaction,
            action: 'deleted',
            actorId: (int) $request->user()->id,
            childId: $childId,
        );

        return response()->json(['message' => 'Transacción eliminada.']);
    }

    public function budgetsIndex(Request $request): JsonResponse
    {
        if ($forbidden = $this->forbidUnlessAuthorized('viewAny', Budget::class)) {
            return $forbidden;
        }

        return response()->json(['data' => Budget::query()->get()]);
    }

    public function budgetsStore(Request $request): JsonResponse
    {
        if ($forbidden = $this->forbidUnlessAuthorized('create', Budget::class)) {
            return $forbidden;
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

        $this->notifications->notifyFamily(
            $request->user(),
            'finance_budget',
            'Presupuesto creado',
            "{$request->user()->name} creó el presupuesto «{$budget->name}»",
            ['entity_type' => 'budget', 'entity_id' => $budget->id],
        );

        FamilyRealtime::financeChanged(
            familyId: (string) $request->user()->family_id,
            entityType: 'budget',
            entityId: (string) $budget->id,
            action: 'created',
            actorId: (int) $request->user()->id,
        );

        return response()->json(['data' => $budget], 201);
    }

    public function budgetsUpdate(Request $request, string $budget): JsonResponse
    {
        $model = Budget::query()->findOrFail($budget);

        if ($forbidden = $this->forbidUnlessAuthorized('update', $model)) {
            return $forbidden;
        }

        $validated = $request->validate([
            'name'   => ['sometimes', 'string', 'max:120'],
            'amount' => ['sometimes', 'numeric', 'min:0'],
        ]);

        $model->update($validated);

        $this->notifications->notifyFamily(
            $request->user(),
            'finance_budget',
            'Presupuesto actualizado',
            "{$request->user()->name} actualizó el presupuesto «{$model->name}»",
            ['entity_type' => 'budget', 'entity_id' => $model->id],
        );

        FamilyRealtime::financeChanged(
            familyId: (string) $request->user()->family_id,
            entityType: 'budget',
            entityId: (string) $model->id,
            action: 'updated',
            actorId: (int) $request->user()->id,
        );

        return response()->json(['data' => $model->fresh()]);
    }

    public function budgetsDestroy(Request $request, string $budget): JsonResponse
    {
        $model = Budget::query()->findOrFail($budget);

        if ($forbidden = $this->forbidUnlessAuthorized('delete', $model)) {
            return $forbidden;
        }

        $name = $model->name;
        $model->delete();

        $this->notifications->notifyFamily(
            $request->user(),
            'finance_budget',
            'Presupuesto eliminado',
            "{$request->user()->name} eliminó el presupuesto «{$name}»",
            ['entity_type' => 'budget', 'entity_id' => $budget],
        );

        FamilyRealtime::financeChanged(
            familyId: (string) $request->user()->family_id,
            entityType: 'budget',
            entityId: (string) $budget,
            action: 'deleted',
            actorId: (int) $request->user()->id,
        );

        return response()->json(['message' => 'Presupuesto eliminado.']);
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
        if ($forbidden = $this->forbidUnlessAuthorized('viewAny', Transaction::class)) {
            return $forbidden;
        }

        $from = now()->subDays($period->days())->startOfDay();
        $base = $this->transactionsForGuardian($request->user(), $this->guardians)
            ->where('transaction_date', '>=', $from);

        if ($request->filled('child_id')) {
            $childId = $request->integer('child_id');
            $this->assertCanAccessChild($request->user(), $this->guardians, $childId);
            $base->where('child_id', $childId);
        }

        $income = (clone $base)->where('type', 'income')->sum('amount');
        $expense = (clone $base)->where('type', 'expense')->sum('amount');

        return response()->json([
            'data' => [
                'period'   => $period->value,
                'label'    => $period->label(),
                'income'   => (float) $income,
                'expense'  => (float) $expense,
                'balance'  => (float) $income - (float) $expense,
                'from'     => $from->toDateString(),
                'to'       => now()->toDateString(),
                'child_id' => $request->filled('child_id') ? (string) $request->integer('child_id') : null,
            ],
        ]);
    }
}
