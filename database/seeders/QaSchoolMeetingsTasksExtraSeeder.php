<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SchoolMeeting;
use App\Models\SchoolMeetingRsvp;
use App\Models\SchoolTeacherTask;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/** Reunión pasada y tareas staff adicionales. */
final class QaSchoolMeetingsTasksExtraSeeder extends Seeder
{
    public function run(): void
    {
        $school = QaSchoolContext::school();
        $admin = QaSchoolContext::admin();
        $docente = QaSchoolContext::docente();
        $class = QaSchoolContext::class3b($school);
        $padre = QaSchoolContext::padre();
        if ($school === null || $admin === null || $docente === null) {
            return;
        }

        if (Schema::hasTable('school_meetings')) {
            $past = SchoolMeeting::query()->updateOrCreate(
                ['school_id' => $school->id, 'title' => 'Entrega de boletines (pasada)'],
                [
                    'campus_id' => $school->main_campus_id,
                    'school_class_id' => $class?->id,
                    'created_by' => $docente->id,
                    'description' => 'Reunión ya realizada (demo).',
                    'starts_at' => now()->subDays(7)->setTime(16, 0),
                    'ends_at' => now()->subDays(7)->setTime(17, 0),
                    'location' => 'Auditorio',
                    'status' => 'scheduled',
                ],
            );
            if ($padre !== null && Schema::hasTable('school_meeting_rsvps')) {
                SchoolMeetingRsvp::query()->updateOrCreate(
                    ['school_meeting_id' => $past->id, 'user_id' => $padre->id],
                    ['status' => 'going'],
                );
            }
        }

        if (! Schema::hasTable('school_teacher_tasks')) {
            return;
        }
        SchoolTeacherTask::query()->updateOrCreate(
            ['school_id' => $school->id, 'title' => 'Planear salida pedagógica'],
            [
                'created_by' => $admin->id,
                'assigned_to' => $docente->id,
                'description' => 'Confirmar cupos y permiso de padres.',
                'status' => 'done',
                'due_date' => now()->subDays(2)->toDateString(),
            ],
        );
        SchoolTeacherTask::query()->updateOrCreate(
            ['school_id' => $school->id, 'title' => 'Actualizar planeador semanal'],
            [
                'created_by' => $admin->id,
                'assigned_to' => $docente->id,
                'description' => 'Subir planeador a la carpeta compartida.',
                'status' => 'in_progress',
                'due_date' => now()->addDay()->toDateString(),
            ],
        );
    }
}
