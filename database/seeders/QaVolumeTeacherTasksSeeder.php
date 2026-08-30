<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SchoolTeacherTask;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/** ~100 tareas staff QA. */
final class QaVolumeTeacherTasksSeeder extends Seeder
{
    /** @var list<string> */
    private const STATUSES = ['pending', 'in_progress', 'done'];

    public function run(): void
    {
        if (! Schema::hasTable('school_teacher_tasks')) {
            return;
        }
        $school = QaSchoolContext::school();
        $admin = QaSchoolContext::admin();
        $docente = QaSchoolContext::docente();
        if ($school === null || $admin === null || $docente === null) {
            return;
        }

        QaBulkSupport::each(function (int $i) use ($school, $admin, $docente): void {
            SchoolTeacherTask::query()->updateOrCreate(
                ['school_id' => $school->id, 'title' => "QA Task #{$i}"],
                [
                    'created_by' => $admin->id,
                    'assigned_to' => $docente->id,
                    'description' => "Tarea staff QA volumen #{$i}.",
                    'status' => self::STATUSES[($i - 1) % 3],
                    'due_date' => now()->addDays($i % 45)->toDateString(),
                ],
            );
        });
    }
}
