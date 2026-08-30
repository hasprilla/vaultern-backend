<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SchoolPsychCase;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/** ~100 casos psicológicos QA (Sofía/Lucas alternados). */
final class QaVolumePsychSeeder extends Seeder
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

        QaBulkSupport::each(function (int $i) use ($school, $admin, $sofia, $lucas): void {
            $student = ($i % 2 === 1) ? $sofia : ($lucas ?? $sofia);
            SchoolPsychCase::query()->updateOrCreate(
                ['school_id' => $school->id, 'title' => "QA Psych #{$i}"],
                [
                    'student_user_id' => $student->id,
                    'created_by' => $admin->id,
                    'summary' => "Caso psicológico QA volumen #{$i}.",
                    'status' => $i % 3 === 0 ? 'closed' : 'open',
                    'visibility' => 'staff',
                ],
            );
        });
    }
}
