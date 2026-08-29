<?php

declare(strict_types=1);

namespace App\Application\School\Queries;

use App\Application\School\Support\BuildWeekSlotsFromBlocks;
use App\Models\CurriculumProfile;
use App\Models\CurriculumSubject;

final class GetCurriculumTemplateQuery
{
    public function __construct(
        private readonly BuildWeekSlotsFromBlocks $weekSlots,
    ) {}

    /** @return array<string, mixed>|null */
    public function handle(string $country, string $level, string $shift): ?array
    {
        $profile = CurriculumProfile::query()
            ->with('blocks')
            ->where('country_code', strtoupper($country))
            ->where('level', $level)
            ->where('shift', $shift)
            ->where('is_active', true)
            ->first();

        if ($profile === null) {
            return null;
        }

        $subjects = CurriculumSubject::query()
            ->where('country_code', strtoupper($country))
            ->where('level', $level)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'sort_order']);

        $blocks = $profile->blocks->map(static fn ($b) => [
            'start' => $b->start_time,
            'end' => $b->end_time,
            'kind' => $b->kind,
            'label' => $b->label,
        ])->values()->all();

        return [
            'profile' => [
                'id' => $profile->id,
                'country_code' => $profile->country_code,
                'level' => $profile->level,
                'shift' => $profile->shift,
                'label' => $profile->label,
                'weekly_hours' => $profile->weekly_hours,
            ],
            'blocks' => $blocks,
            'subjects' => $subjects->map(static fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
            ])->values()->all(),
            'slots' => $this->weekSlots->handle($blocks, $subjects->pluck('name')->all()),
        ];
    }
}
