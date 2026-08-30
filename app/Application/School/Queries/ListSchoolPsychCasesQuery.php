<?php

declare(strict_types=1);

namespace App\Application\School\Queries;

use App\Models\SchoolPsychCase;
use Illuminate\Support\Collection;

final class ListSchoolPsychCasesQuery
{
    /**
     * @return Collection<int, SchoolPsychCase>
     */
    public function handle(string $schoolId, ?int $studentUserId = null): Collection
    {
        $q = SchoolPsychCase::query()
            ->with(['student:id,name', 'notes'])
            ->where('school_id', $schoolId)
            ->orderByDesc('created_at');

        if ($studentUserId !== null) {
            $q->where('student_user_id', $studentUserId);
        }

        return $q->get();
    }
}
