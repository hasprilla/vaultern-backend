<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\School;
use App\Models\SchoolCampus;
use App\Models\SchoolSubscription;
use App\Models\TeacherMembership;
use Illuminate\Support\Facades\Schema;

/** Helpers de seed para colegios QA multi-sede. */
final class QaSchoolSeedSupport
{
    /** @param  list<array{0:string,1:string,2:string,3:bool}>  $campuses */
    public function upsertCampuses(string $schoolCode, array $campuses): void
    {
        $school = School::query()->where('code', $schoolCode)->first();
        if ($school === null || ! Schema::hasTable('school_campuses')) {
            return;
        }
        foreach ($campuses as [$name, $code, $city, $main]) {
            SchoolCampus::query()->updateOrCreate(
                ['school_id' => $school->id, 'code' => $code],
                ['name' => $name, 'city' => $city, 'is_main' => $main, 'is_active' => true],
            );
        }
        $main = SchoolCampus::query()
            ->where('school_id', $school->id)
            ->where('is_main', true)
            ->first();
        if ($main !== null) {
            $school->update(['main_campus_id' => $main->id]);
        }
    }

    /** @param  list<array{0:string,1:string,2:string,3:bool}>  $campuses */
    public function seedSchool(
        string $code,
        string $name,
        string $city,
        int $adminId,
        ?int $docenteId,
        array $campuses,
    ): void {
        School::query()->updateOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'city' => $city,
                'plan' => 'school',
                'is_active' => true,
                'created_by' => $adminId,
            ],
        );
        $this->upsertCampuses($code, $campuses);
        $school = School::query()->where('code', $code)->firstOrFail();
        foreach (array_filter([
            [$adminId, 'admin'],
            $docenteId !== null ? [$docenteId, 'teacher'] : null,
        ]) as [$uid, $role]) {
            TeacherMembership::query()->updateOrCreate(
                ['school_id' => $school->id, 'user_id' => $uid],
                ['role' => $role, 'status' => 'active'],
            );
        }
        if (Schema::hasTable('school_subscriptions')) {
            SchoolSubscription::query()->updateOrCreate(
                ['school_id' => $school->id],
                [
                    'plan_code' => 'school',
                    'status' => 'active',
                    'billing' => 'monthly',
                    'current_period_end' => now()->addMonth(),
                ],
            );
        }
    }
}
