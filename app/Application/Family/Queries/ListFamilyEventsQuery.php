<?php

declare(strict_types=1);

namespace App\Application\Family\Queries;

use App\Models\FamilyEvent;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListFamilyEventsQuery
{
    public function execute(User $actor, int $perPage = 20, ?string $kind = null): LengthAwarePaginator
    {
        abort_if($actor->family_id === null, 403, 'Sin familia');

        $q = FamilyEvent::query()
            ->where('family_id', $actor->family_id)
            ->with(['creator:id,name', 'guests', 'child:id,name', 'expenses'])
            ->orderByDesc('starts_at');

        if ($kind !== null && $kind !== '') {
            $q->where('kind', $kind);
        }

        return $q->paginate($perPage);
    }
}
