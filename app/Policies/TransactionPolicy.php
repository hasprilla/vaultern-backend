<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;
use App\Services\ChildGuardianService;

class TransactionPolicy
{
    public function __construct(
        private readonly ChildGuardianService $guardians,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->canManageFinances();
    }

    public function view(User $user, Transaction $transaction): bool
    {
        return $user->canManageFinances()
            && $this->sameFamily($user, $transaction)
            && $this->guardianOfChild($user, $transaction);
    }

    public function create(User $user): bool
    {
        return $user->canManageFinances();
    }

    public function update(User $user, Transaction $transaction): bool
    {
        return $user->canManageFinances()
            && $this->sameFamily($user, $transaction)
            && $this->guardianOfChild($user, $transaction);
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        return $user->canManageFinances()
            && $this->sameFamily($user, $transaction)
            && $this->guardianOfChild($user, $transaction);
    }

    private function sameFamily(User $user, Transaction $transaction): bool
    {
        return $user->family_id !== null
            && (string) $user->family_id === (string) $transaction->family_id;
    }

    private function guardianOfChild(User $user, Transaction $transaction): bool
    {
        if ($transaction->child_id === null) {
            return false;
        }

        return $this->guardians->isGuardianOf($user, (int) $transaction->child_id);
    }
}
