<?php

declare(strict_types=1);

namespace App\Application\School\Queries;

use App\Models\CurriculumProfile;

final class ListCurriculumProfilesQuery
{
    /** @return list<array<string, mixed>> */
    public function handle(string $country): array
    {
        return CurriculumProfile::query()
            ->where('country_code', strtoupper($country))
            ->where('is_active', true)
            ->orderBy('level')
            ->orderBy('shift')
            ->get(['id', 'country_code', 'level', 'shift', 'label', 'weekly_hours'])
            ->map(static fn ($p) => [
                'id' => $p->id,
                'country_code' => $p->country_code,
                'level' => $p->level,
                'shift' => $p->shift,
                'label' => $p->label,
                'weekly_hours' => $p->weekly_hours,
            ])
            ->values()
            ->all();
    }
}
