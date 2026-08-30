<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SchoolPsychCase;
use App\Models\SchoolPsychNote;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/** Casos psicológicos QA. */
final class QaSchoolPsychSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('school_psych_cases')) {
            return;
        }
        $school = QaSchoolContext::school();
        $admin = QaSchoolContext::admin();
        $sofia = QaSchoolContext::hijo();
        $lucas = QaSchoolContext::hijo2();
        if ($school === null || $admin === null || $sofia === null) {
            return;
        }

        $open = SchoolPsychCase::query()->updateOrCreate(
            ['school_id' => $school->id, 'title' => 'Seguimiento adaptación Sofía'],
            [
                'student_user_id' => $sofia->id,
                'created_by' => $admin->id,
                'summary' => 'Observación de integración en aula 3°B.',
                'status' => 'open',
                'visibility' => 'staff',
            ],
        );
        if (Schema::hasTable('school_psych_notes')) {
            SchoolPsychNote::query()->updateOrCreate(
                ['school_psych_case_id' => $open->id, 'body' => 'Primera entrevista con orientadora.'],
                ['created_by' => $admin->id, 'notify_guardians' => false],
            );
        }
        if ($lucas === null) {
            return;
        }
        SchoolPsychCase::query()->updateOrCreate(
            ['school_id' => $school->id, 'title' => 'Cierre seguimiento Lucas'],
            [
                'student_user_id' => $lucas->id,
                'created_by' => $admin->id,
                'summary' => 'Caso cerrado — progreso positivo.',
                'status' => 'closed',
                'visibility' => 'guardians',
            ],
        );
    }
}
