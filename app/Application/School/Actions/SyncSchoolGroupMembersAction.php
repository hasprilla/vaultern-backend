<?php

declare(strict_types=1);

namespace App\Application\School\Actions;

use App\Models\ClassEnrollment;
use App\Models\SchoolGroup;
use Illuminate\Support\Facades\DB;

final class SyncSchoolGroupMembersAction
{
    public function __construct(
        private readonly ActivateSchoolGroupMemberAction $activate,
    ) {}

    /**
     * @param  list<int>  $memberIds
     */
    public function execute(
        SchoolGroup $group,
        array $memberIds,
        ?string $schoolClassId = null,
    ): SchoolGroup {
        DB::transaction(function () use ($group, $memberIds, $schoolClassId): void {
            $desired = array_values(array_unique(array_map('intval', $memberIds)));

            if ($schoolClassId !== null && $schoolClassId !== '') {
                $classUserIds = ClassEnrollment::query()
                    ->where('school_class_id', $schoolClassId)
                    ->where('status', 'active')
                    ->pluck('student_user_id')
                    ->map(static fn ($id) => (int) $id)
                    ->all();
                $desired = array_values(array_intersect($desired, $classUserIds));
                $this->activate->deactivateOthers($group, $desired, $classUserIds);
            } else {
                $this->activate->deactivateOthers($group, $desired, null);
            }

            foreach ($desired as $userId) {
                $this->activate->execute($group->id, $userId);
            }
        });

        $group->load(['members.user:id,name,email,role']);

        return $group;
    }
}
