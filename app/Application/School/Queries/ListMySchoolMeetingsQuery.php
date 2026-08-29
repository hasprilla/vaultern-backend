<?php

declare(strict_types=1);

namespace App\Application\School\Queries;

use App\Models\SchoolMeeting;
use App\Models\User;
use Illuminate\Support\Collection;

final class ListMySchoolMeetingsQuery
{
    /** @return Collection<int, SchoolMeeting> */
    public function handle(User $user): Collection
    {
        return SchoolMeeting::query()
            ->with([
                'school:id,name',
                'creator:id,name',
                'rsvps' => fn ($q) => $q->where('user_id', $user->id),
            ])
            ->whereHas('rsvps', fn ($q) => $q->where('user_id', $user->id))
            ->orderByDesc('starts_at')
            ->get();
    }
}
