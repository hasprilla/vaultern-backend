<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\Transaction;
use App\Models\User;
use App\Services\ChildGuardianService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

trait ScopesByChildGuardianship
{
    /** @return list<int> */
    protected function myChildIds(User $user, ChildGuardianService $guardians): array
    {
        return $guardians->childIdsFor($user);
    }

    protected function assertCanAccessChild(User $user, ChildGuardianService $guardians, ?int $childId): void
    {
        if ($childId === null) {
            return;
        }

        if (! $guardians->isGuardianOf($user, $childId)) {
            throw ValidationException::withMessages([
                'child_id' => 'Solo puedes gestionar información de tus hijos vinculados.',
            ]);
        }
    }

    /**
     * Transacciones visibles: solo las de hijos vinculados al usuario.
     *
     * @return Builder<Transaction>
     */
    protected function transactionsForGuardian(User $user, ChildGuardianService $guardians): Builder
    {
        $childIds = $this->myChildIds($user, $guardians);

        return Transaction::query()->whereIn('child_id', $childIds === [] ? [-1] : $childIds);
    }
}
