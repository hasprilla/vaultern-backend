<?php

declare(strict_types=1);

namespace App\Application\School\Queries;

use App\Models\SchoolAnnouncement;
use Illuminate\Support\Collection;

final class ListSchoolAnnouncementsQuery
{
    /**
     * @param  list<string>|null  $types
     * @return Collection<int, SchoolAnnouncement>
     */
    public function handle(string $schoolId, ?array $types = null): Collection
    {
        $q = SchoolAnnouncement::query()
            ->where('school_id', $schoolId)
            ->orderByDesc('created_at')
            ->limit(200);

        if ($types !== null && $types !== []) {
            $q->whereIn('type', $types);
        }

        return $q->get();
    }
}
