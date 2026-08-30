<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SchoolAttendanceLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/** Asistencia reciente Sofía / Lucas. */
final class QaSchoolAttendanceSeeder extends Seeder
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

        $rows = [
            [$sofia, 0, 'present', 'Llegó a tiempo', $padre->id],
            [$sofia, 1, 'late', 'Llegó 10 min tarde', $padre->id],
            [$sofia, 2, 'absent', 'Cita médica', $padre->id],
            [$sofia, 3, 'sick', 'Gripe', $padre->id],
            [$sofia, 4, 'present', null, $docente?->id ?? $padre->id],
        ];
        if ($lucas !== null) {
            $rows[] = [$lucas, 0, 'present', null, $padre->id];
            $rows[] = [$lucas, 1, 'present', null, $padre->id];
            $rows[] = [$lucas, 2, 'late', 'Tráfico', $padre->id];
        }

        foreach ($rows as [$student, $daysAgo, $status, $note, $by]) {
            SchoolAttendanceLog::query()->updateOrCreate(
                [
                    'school_id' => $school->id,
                    'student_user_id' => $student->id,
                    'attendance_date' => now()->subDays($daysAgo)->toDateString(),
                ],
                [
                    'family_id' => $student->family_id,
                    'reported_by' => $by,
                    'status' => $status,
                    'note' => $note,
                ],
            );
        }
    }
}
