<?php

declare(strict_types=1);

namespace App\Application\Family\Queries;

use App\Models\FamilyMedication;
use App\Models\User;
use Illuminate\Support\Collection;

final class ListFamilyMedicationsQuery
{
    /** @return Collection<int, FamilyMedication> */
    public function execute(User $actor, bool $activeOnly = true): Collection
    {
        abort_if($actor->family_id === null, 403);

        return FamilyMedication::query()
            ->where('family_id', $actor->family_id)
            ->when($activeOnly, fn ($q) => $q->where('active', true))
            ->with([
                'patient:id,name',
                'logs' => fn ($q) => $q->latest('taken_at')->limit(1),
            ])
            ->orderBy('name')
            ->get();
    }
}
