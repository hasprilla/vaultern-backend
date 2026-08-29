<?php

declare(strict_types=1);

namespace App\Application\School\Actions;

use App\Models\SchoolGroup;
use App\Models\SchoolGroupMember;
use Illuminate\Support\Str;

final class ActivateSchoolGroupMemberAction
{
    public function execute(string $groupId, int $userId): void
    {
        $row = SchoolGroupMember::query()->firstOrNew([
            'school_group_id' => $groupId,
            'user_id' => $userId,
        ]);

        if (! $row->exists) {
            $row->id = (string) Str::uuid();
            $row->member_role = 'member';
        }

        $row->status = 'active';
        $row->save();
    }

    /** @param  list<int>  $desired */
    public function deactivateOthers(SchoolGroup $group, array $desired, ?array $scopeUserIds): void
    {
        $q = SchoolGroupMember::query()->where('school_group_id', $group->id);

        if ($scopeUserIds !== null) {
            $q->whereIn('user_id', $scopeUserIds);
        }

        if ($desired !== []) {
            $q->whereNotIn('user_id', $desired);
        }

        $q->update(['status' => 'inactive']);
    }
}
