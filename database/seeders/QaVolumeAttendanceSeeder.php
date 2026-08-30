<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SchoolAttendanceLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/** ~100 registros de asistencia QA (present/absent). */
final class QaVolumeAttendanceSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('school_attendance_logs')) {
            return;
        }
        $school = QaSchoolContext::school();
        $padre = QaSchoolContext::padre();
        $docente = QaSchoolContext::docente();
        $sofia = QaSchoolContext::hijo();
        $lucas = QaSchoolContext::hijo2();
        if ($school === null || $padre === null || $sofia === null) {
            return;
        }

        QaBulkSupport::each(function (int $i) use ($school, $padre, $docente, $sofia, $lucas): void {
            $student = ($i % 2 === 1) ? $sofia : ($lucas ?? $sofia);
            $day = now()->subDays($i + 14)->toDateString();
            SchoolAttendanceLog::query()->updateOrCreate(
                [
                    'student_user_id' => $student->id,
                    'attendance_date' => $day,
                ],
                [
                    'school_id' => $school->id,
                    'family_id' => $student->family_id,
                    'reported_by' => $docente?->id ?? $padre->id,
                    'status' => $i % 2 === 1 ? 'present' : 'absent',
                    'note' => "QA asistencia volumen #{$i}.",
                ],
            );
        });
    }
}
