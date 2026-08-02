<?php

declare(strict_types=1);

namespace App\Application\Finance\Actions;

use App\Models\Transaction;
use App\Models\User;
use App\Services\FamilyNotificationService;
use App\Support\FamilyRealtime;

final class DeleteTransactionAction
{
    public function __construct(
        private readonly FamilyNotificationService $notifications,
    ) {}

    public function execute(User $actor, Transaction $transaction): void
    {
        $transactionId = (string) $transaction->id;
        $description = $transaction->description ?? 'Transacción';
        $childId = $transaction->child_id !== null ? (int) $transaction->child_id : null;
        $childName = $transaction->child?->name;

        $transaction->delete();

        if ($childId !== null) {
            $this->notifications->notifyChildGuardians(
                $actor,
                $childId,
                'finance_transaction',
                'Transacción eliminada',
                "{$actor->name} eliminó «{$description}»".($childName ? " de {$childName}" : ''),
                ['entity_type' => 'transaction', 'entity_id' => $transactionId],
            );
        }

        FamilyRealtime::financeChanged(
            familyId: (string) $actor->family_id,
            entityType: 'transaction',
            entityId: $transactionId,
            action: 'deleted',
            actorId: (int) $actor->id,
            childId: $childId,
        );
    }
}
