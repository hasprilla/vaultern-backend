<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ClassEnrollment;
use App\Models\School;
use App\Models\SchoolClass;
use Illuminate\Database\Seeder;

/** Clases y enrollments en colegios multi-sede. */
final class QaMultiSchoolsClassesSeeder extends Seeder
{
    public function run(): void
    {
        $admin = QaSchoolContext::admin();
        $docente = QaSchoolContext::docente();
        $sofia = QaSchoolContext::hijo();
        if ($admin === null) {
            return;
        }

        foreach ([
            ['QAOCCTE', '4°A Occidente', '4', 'A'],
            ['QAORIENTE', '6°B Oriente', '6', 'B'],
            [QaUsersSeeder::SCHOOL_CODE, '5°A QA', '5', 'A'],
        ] as [$code, $name, $grade, $section]) {
            $school = School::query()->where('code', $code)->first();
            if ($school === null) {
                continue;
            }
            $class = SchoolClass::query()->updateOrCreate(
                ['school_id' => $school->id, 'name' => $name],
                [
                    'grade' => $grade,
                    'section' => $section,
                    'school_year' => (string) now()->year,
                    'teacher_user_id' => $docente?->id,
                    'campus_id' => $school->main_campus_id,
                ],
            );
            if ($sofia?->family_id === null || $code !== 'QAOCCTE') {
                continue;
            }
            ClassEnrollment::query()->updateOrCreate(
                ['school_class_id' => $class->id, 'student_user_id' => $sofia->id],
                [
                    'family_id' => $sofia->family_id,
                    'enrolled_by' => $admin->id,
                    'status' => 'active',
                ],
            );
        }
    }
}
