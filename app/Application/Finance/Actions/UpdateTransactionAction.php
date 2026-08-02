<?php

declare(strict_types=1);

namespace App\Application\Finance\Actions;

use App\Models\Transaction;
use App\Models\User;
use App\Services\FamilyNotificationService;
use App\Support\FamilyRealtime;

final class UpdateTransactionAction
{
    public function __construct(
        private readonly FamilyNotificationService $notifications,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(User $actor, Transaction $transaction, array $validated): Transaction
    {
        $transaction->update($validated);
        $transaction->load('child');

        if ($transaction->child_id !== null) {
            $this->notifications->notifyChildGuardians(
                $actor,
                (int) $transaction->child_id,
                'finance_transaction',
                'Transacción actualizada',
                "{$actor->name} actualizó una transacción de {$transaction->child?->name}",
                ['entity_type' => 'transaction', 'entity_id' => $transaction->id],
            );
        }

        FamilyRealtime::financeChanged(
            familyId: (string) $actor->family_id,
            entityType: 'transaction',
            entityId: (string) $transaction->id,
            action: 'updated',
            actorId: (int) $actor->id,
            childId: $transaction->child_id !== null ? (int) $transaction->child_id : null,
        );

        return $transaction;
    }
}
