<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Finance;

use App\Application\Shared\Actions\StoreAttachmentsAction;
use App\Http\Controllers\Concerns\ReturnsForbidden;
use App\Http\Controllers\Concerns\ScopesByChildGuardianship;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ChildSupportAgreementResource;
use App\Http\Resources\Api\V1\ChildSupportPaymentResource;
use App\Models\Attachment;
use App\Models\ChildSupportAgreement;
use App\Models\ChildSupportAdjustment;
use App\Models\ChildSupportPayment;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ChildGuardianService;
use App\Services\FamilyNotificationService;
use App\Support\FamilyRealtime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ChildSupportController extends Controller
{
    use ReturnsForbidden;
    use ScopesByChildGuardianship;

    public function __construct(
        private readonly ChildGuardianService $guardians,
        private readonly FamilyNotificationService $notifications,
        private readonly StoreAttachmentsAction $storeAttachmentsAction,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if ($forbidden = $this->forbidUnlessAuthorized('viewAny', Transaction::class)) {
            return $forbidden;
        }

        $familyId = $request->user()->family_id;
        $agreements = ChildSupportAgreement::query()
            ->where('family_id', $familyId)
            ->with(['child', 'payer', 'beneficiary', 'adjustments', 'attachments', 'payments.attachments'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => ChildSupportAgreementResource::collection($agreements),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($forbidden = $this->forbidUnlessAuthorized('create', Transaction::class)) {
            return $forbidden;
        }

        $validated = $request->validate([
            'child_id' => ['required', 'integer', 'exists:users,id'],
            'payer_user_id' => ['required', 'integer', 'exists:users,id'],
            'beneficiary_user_id' => ['required', 'integer', 'exists:users,id', 'different:payer_user_id'],
            'initial_amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'default_annual_increase_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'starts_on' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:10240'],
        ]);

        $actor = $request->user();
        $this->assertCanAccessChild($actor, $this->guardians, (int) $validated['child_id']);

        $child = User::query()->findOrFail($validated['child_id']);
        $payer = User::query()->findOrFail($validated['payer_user_id']);
        $beneficiary = User::query()->findOrFail($validated['beneficiary_user_id']);

        foreach ([$child, $payer, $beneficiary] as $member) {
            if ((string) $member->family_id !== (string) $actor->family_id) {
                throw ValidationException::withMessages([
                    'child_id' => 'Los usuarios deben pertenecer a la misma familia.',
                ]);
            }
        }

        if ($child->role !== 'hijo') {
            throw ValidationException::withMessages(['child_id' => 'Debes seleccionar un hijo.']);
        }

        if (! in_array($payer->role, ['padre', 'madre', 'tutor'], true)
            || ! in_array($beneficiary->role, ['padre', 'madre', 'tutor'], true)) {
            throw ValidationException::withMessages([
                'payer_user_id' => 'Pagador y beneficiario deben ser cuidadores de la familia.',
            ]);
        }

        $exists = ChildSupportAgreement::query()
            ->where('family_id', $actor->family_id)
            ->where('child_id', $child->id)
            ->where('status', 'active')
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'child_id' => 'Ya existe un acuerdo activo para este hijo.',
            ]);
        }

        $files = $request->file('attachments', []);

        $agreement = DB::transaction(function () use ($actor, $validated, $child, $files) {
            $agreement = ChildSupportAgreement::query()->create([
                'id' => (string) Str::uuid(),
                'family_id' => $actor->family_id,
                'child_id' => $child->id,
                'payer_user_id' => $validated['payer_user_id'],
                'beneficiary_user_id' => $validated['beneficiary_user_id'],
                'created_by' => $actor->id,
                'initial_amount' => $validated['initial_amount'],
                'currency' => $validated['currency'] ?? 'COP',
                'default_annual_increase_pct' => $validated['default_annual_increase_pct'] ?? 0,
                'starts_on' => $validated['starts_on'],
                'status' => 'active',
                'notes' => $validated['notes'] ?? null,
            ]);

            if ($files !== []) {
                $this->storeAttachmentsAction->execute(
                    $actor,
                    $agreement,
                    is_array($files) ? $files : [$files],
                    'child-support',
                    'agreement',
                );
            }

            return $agreement;
        });

        $agreement->load(['child', 'payer', 'beneficiary', 'adjustments', 'attachments', 'payments.attachments']);

        $amount = number_format((float) $agreement->initial_amount, 0, ',', '.');
        $this->notifications->notifyChildGuardians(
            $actor,
            (int) $child->id,
            'finance_child_support',
            'Acuerdo de cuota alimentaria',
            "{$actor->name} registró la cuota de {$child->name} por \${$amount} {$agreement->currency}",
            ['entity_type' => 'child_support_agreement', 'entity_id' => $agreement->id],
        );

        FamilyRealtime::financeChanged(
            familyId: (string) $actor->family_id,
            entityType: 'child_support_agreement',
            entityId: (string) $agreement->id,
            action: 'created',
            actorId: (int) $actor->id,
            childId: (int) $child->id,
        );

        return response()->json(['data' => new ChildSupportAgreementResource($agreement)], 201);
    }

    public function storeAdjustment(Request $request, string $agreement): JsonResponse
    {
        if ($forbidden = $this->forbidUnlessAuthorized('create', Transaction::class)) {
            return $forbidden;
        }

        $model = ChildSupportAgreement::query()
            ->where('family_id', $request->user()->family_id)
            ->with('adjustments')
            ->findOrFail($agreement);

        if ($model->status !== 'active') {
            throw ValidationException::withMessages(['agreement' => 'El acuerdo no está activo.']);
        }

        $validated = $request->validate([
            'increase_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'effective_on' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $current = $model->currentAmount();
        $after = round($current * (1 + ((float) $validated['increase_pct'] / 100)), 2);

        $adjustment = ChildSupportAdjustment::query()->create([
            'id' => (string) Str::uuid(),
            'agreement_id' => $model->id,
            'recorded_by' => $request->user()->id,
            'increase_pct' => $validated['increase_pct'],
            'amount_after' => $after,
            'effective_on' => $validated['effective_on'],
            'notes' => $validated['notes'] ?? null,
        ]);

        $model->load(['child', 'payer', 'beneficiary', 'adjustments', 'attachments', 'payments.attachments']);

        $this->notifications->notifyChildGuardians(
            $request->user(),
            (int) $model->child_id,
            'finance_child_support',
            'Ajuste anual de cuota',
            "{$request->user()->name} aplicó un aumento del {$validated['increase_pct']}%: nuevo valor \$"
                .number_format($after, 0, ',', '.'),
            ['entity_type' => 'child_support_agreement', 'entity_id' => $model->id, 'adjustment_id' => $adjustment->id],
        );

        return response()->json(['data' => new ChildSupportAgreementResource($model)]);
    }

    public function storePayment(Request $request, string $agreement): JsonResponse
    {
        if ($forbidden = $this->forbidUnlessAuthorized('create', Transaction::class)) {
            return $forbidden;
        }

        $model = ChildSupportAgreement::query()
            ->where('family_id', $request->user()->family_id)
            ->with('adjustments')
            ->findOrFail($agreement);

        if ($model->status !== 'active') {
            throw ValidationException::withMessages(['agreement' => 'El acuerdo no está activo.']);
        }

        $validated = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0'],
            'period_month' => ['required', 'date'],
            'paid_on' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:10240'],
        ]);

        $period = Carbon::parse($validated['period_month'])->startOfMonth()->toDateString();
        $amount = isset($validated['amount'])
            ? (float) $validated['amount']
            : $model->currentAmount();

        if (ChildSupportPayment::query()
            ->where('agreement_id', $model->id)
            ->whereDate('period_month', $period)
            ->exists()) {
            throw ValidationException::withMessages([
                'period_month' => 'Ya hay un pago registrado para ese mes.',
            ]);
        }

        $files = $request->file('attachments', []);
        $actor = $request->user();

        $payment = DB::transaction(function () use ($model, $actor, $validated, $period, $amount, $files) {
            $tx = Transaction::query()->create([
                'id' => (string) Str::uuid(),
                'family_id' => $model->family_id,
                'user_id' => $actor->id,
                'child_id' => $model->child_id,
                'amount' => $amount,
                'currency' => $model->currency,
                'type' => 'expense',
                'category' => 'cuota_alimentaria',
                'description' => 'Cuota alimentaria '.$period,
                'transaction_date' => $validated['paid_on'],
            ]);

            $payment = ChildSupportPayment::query()->create([
                'id' => (string) Str::uuid(),
                'agreement_id' => $model->id,
                'family_id' => $model->family_id,
                'child_id' => $model->child_id,
                'paid_by' => $actor->id,
                'transaction_id' => $tx->id,
                'amount' => $amount,
                'currency' => $model->currency,
                'period_month' => $period,
                'paid_on' => $validated['paid_on'],
                'notes' => $validated['notes'] ?? null,
            ]);

            if ($files !== []) {
                $stored = $this->storeAttachmentsAction->execute(
                    $actor,
                    $payment,
                    is_array($files) ? $files : [$files],
                    'child-support-payments',
                    'receipt',
                );
                foreach ($stored as $file) {
                    Attachment::query()->create([
                        'id' => (string) Str::uuid(),
                        'family_id' => $file->family_id,
                        'user_id' => $file->user_id,
                        'attachable_type' => $tx->getMorphClass(),
                        'attachable_id' => $tx->getKey(),
                        'disk' => $file->disk,
                        'path' => $file->path,
                        'original_name' => $file->original_name,
                        'mime_type' => $file->mime_type,
                        'size' => $file->size,
                        'kind' => 'receipt',
                    ]);
                }
            }

            return $payment;
        });

        $payment->load('attachments');
        $child = User::query()->find($model->child_id);
        $label = number_format($amount, 0, ',', '.');

        $this->notifications->notifyChildGuardians(
            $actor,
            (int) $model->child_id,
            'finance_child_support',
            'Pago de cuota alimentaria',
            "{$actor->name} registró el pago de cuota"
                .($child ? " de {$child->name}" : '')
                ." por \${$label} ({$period})",
            [
                'entity_type' => 'child_support_payment',
                'entity_id' => $payment->id,
                'agreement_id' => $model->id,
            ],
        );

        FamilyRealtime::financeChanged(
            familyId: (string) $model->family_id,
            entityType: 'child_support_payment',
            entityId: (string) $payment->id,
            action: 'created',
            actorId: (int) $actor->id,
            childId: (int) $model->child_id,
        );

        return response()->json(['data' => new ChildSupportPaymentResource($payment)], 201);
    }

    public function end(Request $request, string $agreement): JsonResponse
    {
        if ($forbidden = $this->forbidUnlessAuthorized('create', Transaction::class)) {
            return $forbidden;
        }

        $model = ChildSupportAgreement::query()
            ->where('family_id', $request->user()->family_id)
            ->findOrFail($agreement);

        $model->update(['status' => 'ended']);
        $model->load(['child', 'payer', 'beneficiary', 'adjustments', 'attachments', 'payments.attachments']);

        return response()->json(['data' => new ChildSupportAgreementResource($model)]);
    }
}
