<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SchoolTaskBroadcast;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/** ~100 broadcasts de tareas escolares QA (sin tasks hijas). */
final class QaVolumeBroadcastsSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('school_task_broadcasts')) {
            return;
        }
        $school = QaSchoolContext::school();
        $docente = QaSchoolContext::docente();
        $class = QaSchoolContext::class3b($school);
        if ($school === null || $docente === null || $class === null) {
            return;
        }

        QaBulkSupport::each(function (int $i) use ($school, $docente, $class): void {
            SchoolTaskBroadcast::query()->updateOrCreate(
                ['school_id' => $school->id, 'title' => "QA Broadcast #{$i}"],
                [
                    'school_class_id' => $class->id,
                    'created_by' => $docente->id,
                    'description' => "Broadcast QA volumen #{$i}.",
                    'subject' => 'Materia QA',
                    'priority' => $i % 2 === 0 ? 'high' : 'normal',
                    'due_date' => now()->addDays($i % 30)->toDateString(),
                    'status' => $i % 4 === 0 ? 'completed' : 'pending',
                    'tasks_total' => 0,
                    'tasks_created' => 0,
                ],
            );
        });
    }
}
