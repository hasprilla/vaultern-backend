<?php

declare(strict_types=1);

namespace App\Application\School\Queries;

use App\Models\SchoolHealthAlert;
use Illuminate\Support\Collection;

final class ListSchoolHealthAlertsQuery
{
    /**
     * @return Collection<int, SchoolHealthAlert>
     */
    public function handle(
        string $schoolId,
        ?int $studentUserId = null,
        ?string $type = null,
    ): Collection {
        $q = SchoolHealthAlert::query()
            ->with(['student:id,name'])
            ->where('school_id', $schoolId)
            ->orderByDesc('created_at');

        if ($studentUserId !== null) {
            $q->where('student_user_id', $studentUserId);
        }
        if ($type !== null && $type !== '') {
            $q->where('type', $type);
        }

        return $q->get();
    }
}
