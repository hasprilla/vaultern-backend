<?php

declare(strict_types=1);

namespace App\Application\School\Support;

use App\Models\School;
use Illuminate\Support\Collection;

final class MapSchoolListItem
{
    /**
     * @param  array<string, mixed>|null  $membership
     * @param  array<string, mixed>|null  $subscription
     * @return array<string, mixed>
     */
    public function handle(
        School $school,
        ?array $membership = null,
        ?array $subscription = null,
    ): array {
        return [
            'id' => $school->id,
            'name' => $school->name,
            'code' => $school->code,
            'city' => $school->city,
            'plan' => $school->plan,
            'is_active' => $school->is_active,
            'classes_count' => $school->classes_count ?? 0,
            'membership' => $membership,
            'subscription' => $subscription,
            'campuses' => $this->campuses($school),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function campuses(School $school): array
    {
        /** @var Collection<int, mixed> $rows */
        $rows = $school->relationLoaded('campuses')
            ? $school->campuses
            : $school->campuses()->orderByDesc('is_main')->orderBy('name')->get();

        return $rows->map(static fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'code' => $c->code,
            'city' => $c->city,
            'is_main' => (bool) $c->is_main,
            'is_active' => (bool) $c->is_active,
        ])->values()->all();
    }
}
