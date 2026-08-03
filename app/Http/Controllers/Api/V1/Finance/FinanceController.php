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
use App\Application\Shared\Actions\DeleteAttachmentAction;
use App\Application\Shared\Actions\StoreAttachmentsAction;
use App\Domains\Finance\Entities\FinanceReportPeriod;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesPagination;
use App\Http\Controllers\Concerns\ReturnsForbidden;
use App\Http\Controllers\Concerns\ScopesByChildGuardianship;
use App\Http\Resources\Api\V1\BudgetResource;
use App\Http\Resources\Api\V1\TransactionResource;
use App\Models\Attachment;
use App\Models\Budget;
use App\Models\OcrJob;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ChildGuardianService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        private readonly StoreAttachmentsAction $storeAttachmentsAction,
        private readonly DeleteAttachmentAction $deleteAttachmentAction,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if ($forbidden = $this->forbidUnlessAuthorized('viewAny', Transaction::class)) {
            return $forbidden;
        }

        $transactions = $this->listTransactions->execute(
            $request->user(),
            [
                'child_id' => $request->filled('child_id') ? $request->integer('child_id') : null,
                'type' => $request->input('type'),
                'q' => $request->input('q'),
            ],
            $this->perPage($request),
        );

        return TransactionResource::collection($transactions)->response();
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
            'ocr_job_id'       => ['nullable', 'uuid', 'exists:ocr_jobs,id'],
            'attachments'      => ['nullable', 'array', 'max:5'],
            'attachments.*'    => ['file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:10240'],
        ]);

        $child = User::query()->findOrFail($validated['child_id']);
        $this->assertCanAccessChild($request->user(), $this->guardians, (int) $child->id);

        $files = $request->file('attachments', []);
        unset($validated['attachments']);

        $result = $this->createTransaction->execute($request->user(), $child, $validated);

        if (($result['ok'] ?? false) !== true) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        /** @var Transaction $transaction */
        $transaction = $result['transaction'];
        if ($files !== []) {
            $this->storeAttachmentsAction->execute(
                $request->user(),
                $transaction,
                is_array($files) ? $files : [$files],
                'transactions',
                'receipt',
            );
        } elseif (! empty($validated['ocr_job_id'])) {
            $this->attachOcrFile($request->user(), $transaction, (string) $validated['ocr_job_id']);
        }
        $transaction->load(['child', 'attachments']);

        return response()->json(['data' => new TransactionResource($transaction)], 201);
    }

    private function attachOcrFile(User $actor, Transaction $transaction, string $ocrJobId): void
    {
        $job = OcrJob::query()
            ->where('family_id', $actor->family_id)
            ->whereKey($ocrJobId)
            ->first();

        if ($job === null || blank($job->file_path) || ! Storage::disk('public')->exists($job->file_path)) {
            return;
        }

        $ext = pathinfo((string) $job->file_path, PATHINFO_EXTENSION) ?: 'jpg';
        $dest = 'attachments/'.$actor->family_id.'/transactions/'.$transaction->id.'/ocr-'.Str::uuid().'.'.$ext;
        Storage::disk('public')->copy($job->file_path, $dest);

        Attachment::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $actor->family_id,
            'user_id' => $actor->id,
            'attachable_type' => $transaction->getMorphClass(),
            'attachable_id' => $transaction->getKey(),
            'disk' => 'public',
            'path' => $dest,
            'original_name' => basename((string) $job->file_path),
            'mime_type' => $job->mime_type ?? 'image/jpeg',
            'size' => (int) (Storage::disk('public')->size($dest) ?: 0),
            'kind' => 'receipt',
        ]);
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
        $model->load(['child', 'attachments']);

        return response()->json(['data' => new TransactionResource($model)]);
    }

    public function storeAttachments(Request $request, string $transaction): JsonResponse
    {
        $model = $this->transactionsForGuardian($request->user(), $this->guardians)->findOrFail($transaction);

        if ($forbidden = $this->forbidUnlessAuthorized('update', $model)) {
            return $forbidden;
        }

        $request->validate([
            'attachments'   => ['required', 'array', 'min:1', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:10240'],
        ]);

        $files = $request->file('attachments', []);
        $this->storeAttachmentsAction->execute(
            $request->user(),
            $model,
            is_array($files) ? $files : [$files],
            'transactions',
            'receipt',
        );
        $model->load(['child', 'attachments']);

        return response()->json(['data' => new TransactionResource($model)]);
    }

    public function destroyAttachment(Request $request, string $transaction, string $attachment): JsonResponse
    {
        $model = $this->transactionsForGuardian($request->user(), $this->guardians)->findOrFail($transaction);

        if ($forbidden = $this->forbidUnlessAuthorized('update', $model)) {
            return $forbidden;
        }

        $file = $model->attachments()->whereKey($attachment)->firstOrFail();
        $this->deleteAttachmentAction->execute($request->user(), $file);

        return response()->json(['message' => 'Archivo eliminado']);
    }

    public function show(Request $request, string $transaction): JsonResponse
    {
        $model = $this->transactionsForGuardian($request->user(), $this->guardians)
            ->with(['child', 'attachments'])
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

        return BudgetResource::collection(Budget::query()->get())->response();
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
