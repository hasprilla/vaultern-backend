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

    /**
     * @param  array{child_id?: int|null, type?: string|null, q?: string|null}  $filters
     */
    public function execute(User $viewer, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = $this->transactionsForGuardian($viewer, $this->guardians)
            ->with(['child', 'attachments'])
            ->orderByDesc('transaction_date');

        $childId = $filters['child_id'] ?? null;
        if ($childId !== null) {
            $this->assertCanAccessChild($viewer, $this->guardians, $childId);
            $query->where('child_id', $childId);
        }

        $type = $filters['type'] ?? null;
        if (in_array($type, ['income', 'expense'], true)) {
            $query->where('type', $type);
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($builder) use ($like) {
                $builder->where('description', 'like', $like)
                    ->orWhere('category', 'like', $like);
            });
        }

        return $query->paginate($perPage);
    }
}
