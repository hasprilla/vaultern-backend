<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Budget;
use App\Models\User;

class BudgetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManageFinances();
    }

    public function create(User $user): bool
    {
        return $user->canManageFinances();
    }

    public function update(User $user, Budget $budget): bool
    {
        return $user->canManageFinances() && $this->sameFamily($user, $budget);
    }

    public function delete(User $user, Budget $budget): bool
    {
        return $user->canManageFinances() && $this->sameFamily($user, $budget);
    }

    private function sameFamily(User $user, Budget $budget): bool
    {
        return $user->family_id !== null
            && (string) $user->family_id === (string) $budget->family_id;
    }
}
