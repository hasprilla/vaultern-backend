<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SchoolTaskBroadcast;
use App\Models\Task;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Broadcast de tarea escolar → tasks de Sofía y Lucas. */
final class QaSchoolBroadcastsSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('school_task_broadcasts')) {
            return;
        }
        $school = QaSchoolContext::school();
        $docente = QaSchoolContext::docente();
        $class = QaSchoolContext::class3b($school);
        $sofia = QaSchoolContext::hijo();
        $lucas = QaSchoolContext::hijo2();
        if ($school === null || $docente === null || $class === null || $sofia === null) {
            return;
        }

        $broadcast = SchoolTaskBroadcast::query()->updateOrCreate(
            ['school_id' => $school->id, 'title' => 'Lectura comprensiva cap. 2'],
            [
                'school_class_id' => $class->id,
                'created_by' => $docente->id,
                'description' => 'Leer y resumir el capítulo 2 para el viernes.',
                'subject' => 'Lengua castellana',
                'priority' => 'normal',
                'due_date' => now()->addDays(4)->toDateString(),
                'status' => 'completed',
                'tasks_total' => 2,
                'tasks_created' => 2,
            ],
        );

        if (! Schema::hasTable('tasks')) {
            return;
        }
        foreach (array_filter([$sofia, $lucas]) as $child) {
            if ($child->family_id === null) {
                continue;
            }
            Task::query()->updateOrCreate(
                [
                    'family_id' => $child->family_id,
                    'source_broadcast_id' => $broadcast->id,
                    'assigned_to' => $child->id,
                ],
                [
                    'id' => (string) Str::uuid(),
                    'created_by' => $docente->id,
                    'school_id' => $school->id,
                    'created_by_role' => 'docente',
                    'title' => $broadcast->title,
                    'description' => $broadcast->description,
                    'status' => 'pending',
                    'priority' => 'normal',
                    'is_school' => true,
                    'subject' => $broadcast->subject,
                    'due_date' => $broadcast->due_date,
                ],
            );
        }
    }
}
