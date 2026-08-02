<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Finance;

use App\Application\Finance\Actions\CreateBudgetAction;
use App\Application\Finance\Actions\CreateTransactionAction;
use App\Application\Finance\Actions\DeleteBudgetAction;
use App\Application\Finance\Actions\DeleteTransactionAction;
use App\Application\Finance\Actions\UpdateBudgetAction;
use App\Application\Finance\Actions\UpdateTransactionAction;
use App\Application\Finance\Queries\GetFinanceReportQuery;
use App\Application\Finance\Queries\ListTransactionsQuery;
use App\Domains\Finance\Entities\FinanceReportPeriod;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesPagination;
use App\Http\Controllers\Concerns\ReturnsForbidden;
use App\Http\Controllers\Concerns\ScopesByChildGuardianship;
use App\Http\Resources\Api\V1\BudgetResource;
use App\Http\Resources\Api\V1\TransactionResource;
use App\Models\Budget;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ChildGuardianService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    use ResolvesPagination;
    use ReturnsForbidden;
    use ScopesByChildGuardianship;

    public function __construct(
        private readonly ChildGuardianService $guardians,
        private readonly ListTransactionsQuery $listTransactions,
        private readonly GetFinanceReportQuery $getFinanceReport,
        private readonly CreateTransactionAction $createTransaction,
        private readonly UpdateTransactionAction $updateTransaction,
        private readonly DeleteTransactionAction $deleteTransaction,
        private readonly CreateBudgetAction $createBudget,
        private readonly UpdateBudgetAction $updateBudget,
        private readonly DeleteBudgetAction $deleteBudget,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if ($forbidden = $this->forbidUnlessAuthorized('viewAny', Transaction::class)) {
            return $forbidden;
        }

        $childId = $request->filled('child_id') ? $request->integer('child_id') : null;
        $transactions = $this->listTransactions->execute(
            $request->user(),
            $childId,
            $this->perPage($request),
        );

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
        $this->assertCanAccessChild($request->user(), $this->guardians, (int) $child->id);

        $result = $this->createTransaction->execute($request->user(), $child, $validated);

        if (($result['ok'] ?? false) !== true) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        return response()->json(['data' => new TransactionResource($result['transaction'])], 201);
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

        $model = $this->updateTransaction->execute($request->user(), $model, $validated);

        return response()->json(['data' => new TransactionResource($model)]);
    }

    public function show(Request $request, string $transaction): JsonResponse
    {
        $model = $this->transactionsForGuardian($request->user(), $this->guardians)
            ->with('child')
            ->findOrFail($transaction);

        if ($forbidden = $this->forbidUnlessAuthorized('view', $model)) {
            return $forbidden;
        }

        return response()->json(['data' => new TransactionResource($model)]);
    }

    public function destroy(Request $request, string $transaction): JsonResponse
    {
        $model = $this->transactionsForGuardian($request->user(), $this->guardians)
            ->with('child')
            ->findOrFail($transaction);

        if ($forbidden = $this->forbidUnlessAuthorized('delete', $model)) {
            return $forbidden;
        }

        $this->deleteTransaction->execute($request->user(), $model);

        return response()->json(['message' => 'Transacción eliminada.']);
    }

    public function budgetsIndex(Request $request): JsonResponse
    {
        if ($forbidden = $this->forbidUnlessAuthorized('viewAny', Budget::class)) {
            return $forbidden;
        }

        return BudgetResource::collection(Budget::query()->get());
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

        $budget = $this->createBudget->execute($request->user(), $validated);

        return response()->json(['data' => new BudgetResource($budget)], 201);
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

        $model = $this->updateBudget->execute($request->user(), $model, $validated);

        return response()->json(['data' => new BudgetResource($model)]);
    }

    public function budgetsDestroy(Request $request, string $budget): JsonResponse
    {
        $model = Budget::query()->findOrFail($budget);

        if ($forbidden = $this->forbidUnlessAuthorized('delete', $model)) {
            return $forbidden;
        }

        $this->deleteBudget->execute($request->user(), $model);

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

        $childId = $request->filled('child_id') ? $request->integer('child_id') : null;

        return response()->json([
            'data' => $this->getFinanceReport->execute($request->user(), $period, $childId),
        ]);
    }
}
