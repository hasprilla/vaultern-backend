<?php

declare(strict_types=1);

namespace App\Application\Finance\Queries;

use App\Http\Controllers\Concerns\ScopesByChildGuardianship;
use App\Models\User;
use App\Services\ChildGuardianService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListTransactionsQuery
{
    use ScopesByChildGuardianship;

    public function __construct(
        private readonly ChildGuardianService $guardians,
    ) {}

    public function execute(User $viewer, ?int $childId, int $perPage): LengthAwarePaginator
    {
        $query = $this->transactionsForGuardian($viewer, $this->guardians)
            ->with(['child', 'attachments'])
            ->orderByDesc('transaction_date');

        if ($childId !== null) {
            $this->assertCanAccessChild($viewer, $this->guardians, $childId);
            $query->where('child_id', $childId);
        }

        return $query->paginate($perPage);
    }
}
