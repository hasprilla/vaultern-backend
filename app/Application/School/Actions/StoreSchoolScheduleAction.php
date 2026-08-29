<?php

declare(strict_types=1);

namespace App\Application\School\Actions;

use App\Models\School;
use App\Models\SchoolSchedule;
use App\Models\User;
use Illuminate\Support\Str;

final class StoreSchoolScheduleAction
{
    /**
     * @param  array{
     *   title: string,
     *   slots: list<array<string, mixed>>,
     *   exceptions?: list<array<string, mixed>>|null,
     *   campus_id?: string|null,
     *   school_class_id?: string|null
     * }  $data
     */
    public function handle(School $school, User $user, array $data): SchoolSchedule
    {
        return SchoolSchedule::query()->create([
            'id' => (string) Str::uuid(),
            'school_id' => $school->id,
            'campus_id' => $data['campus_id'] ?? null,
            'school_class_id' => $data['school_class_id'] ?? null,
            'title' => $data['title'],
            'slots' => $data['slots'],
            'exceptions' => $data['exceptions'] ?? null,
            'created_by' => $user->id,
            'is_active' => true,
        ]);
    }
}
