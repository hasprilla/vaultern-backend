<?php

declare(strict_types=1);

namespace App\Application\Finance\Actions;

use App\Models\Transaction;
use App\Models\User;
use App\Services\FamilyNotificationService;
use App\Support\FamilyRealtime;
use Illuminate\Support\Str;

/**
 * @phpstan-type CreateTxSuccess array{ok: true, transaction: Transaction}
 * @phpstan-type CreateTxFailure array{ok: false, status: int, message: string}
 */
final class CreateTransactionAction
{
    public function __construct(
        private readonly FamilyNotificationService $notifications,
    ) {}

    /**
     * @param  array{
     *   amount: float|int|string,
     *   currency?: string|null,
     *   type: string,
     *   category?: string|null,
     *   description?: string|null,
     *   transaction_date: string,
     *   child_id: int,
     *   ocr_job_id?: string|null
     * }  $validated
     * @return CreateTxSuccess|CreateTxFailure
     */
    public function execute(User $actor, User $child, array $validated): array
    {
        if ((string) $child->family_id !== (string) $actor->family_id || $child->role !== 'hijo') {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Hijo no válido para esta familia',
            ];
        }

        $transaction = Transaction::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $actor->family_id,
            'user_id' => $actor->id,
            'child_id' => $validated['child_id'],
            'amount' => $validated['amount'],
            'currency' => $validated['currency'] ?? 'COP',
            'type' => $validated['type'],
            'category' => $validated['category'] ?? null,
            'description' => $validated['description'] ?? null,
            'transaction_date' => $validated['transaction_date'],
            'ocr_job_id' => $validated['ocr_job_id'] ?? null,
        ]);

        $transaction->load('child');

        $label = $validated['type'] === 'income' ? 'Ingreso' : 'Gasto';
        $amount = number_format((float) $validated['amount'], 0, ',', '.');
        $this->notifications->notifyChildGuardians(
            $actor,
            (int) $child->id,
            'finance_transaction',
            "$label registrado",
            "{$actor->name} registró $label de {$child->name} por \$$amount COP",
            ['entity_type' => 'transaction', 'entity_id' => $transaction->id],
        );

        FamilyRealtime::financeChanged(
            familyId: (string) $actor->family_id,
            entityType: 'transaction',
            entityId: (string) $transaction->id,
            action: 'created',
            actorId: (int) $actor->id,
            childId: (int) $child->id,
        );

        return ['ok' => true, 'transaction' => $transaction];
    }
}
