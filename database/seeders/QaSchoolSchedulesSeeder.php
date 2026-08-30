<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Application\School\Queries\GetCurriculumTemplateQuery;
use App\Models\SchoolSchedule;
use App\Models\SchoolScheduleShare;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/** Horario 3°B + excepciones + shares. */
final class QaSchoolSchedulesSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('school_schedules')) {
            return;
        }
        $school = QaSchoolContext::school();
        $admin = QaSchoolContext::admin();
        $class = QaSchoolContext::class3b($school);
        $docente = QaSchoolContext::docente();
        if ($school === null || $admin === null || $class === null) {
            return;
        }

        $slots = app(GetCurriculumTemplateQuery::class)->handle('CO', 'primaria', 'manana')['slots'] ?? [];
        if ($slots !== [] && $docente !== null) {
            foreach ([0 => 'Matemáticas', 1 => 'Lengua castellana', 3 => 'Ciencias naturales'] as $i => $name) {
                if (! isset($slots[$i]) || ($slots[$i]['kind'] ?? '') === 'break') {
                    continue;
                }
                $slots[$i]['subject'] = $name;
                $slots[$i]['teacher_user_id'] = $docente->id;
                $slots[$i]['teacher_name'] = $docente->name;
            }
        }

        $schedule = SchoolSchedule::query()->updateOrCreate(
            ['school_id' => $school->id, 'title' => '3°B QA · Jornada mañana'],
            [
                'campus_id' => $school->main_campus_id,
                'school_class_id' => $class->id,
                'slots' => $slots,
                'exceptions' => [
                    ['date' => now()->addWeeks(2)->toDateString(), 'end_date' => now()->addWeeks(2)->addDays(4)->toDateString(), 'type' => 'vacation', 'label' => 'Semana de receso QA'],
                    ['date' => now()->addDays(10)->toDateString(), 'type' => 'no_class', 'label' => 'Día institucional'],
                ],
                'created_by' => $admin->id,
                'is_active' => true,
            ],
        );
        $this->share($schedule->id);
    }

    private function share(string $scheduleId): void
    {
        if (! Schema::hasTable('school_schedule_shares')) {
            return;
        }
        $groupId = QaSchoolContext::padresGroup()?->id;
        $padreId = QaSchoolContext::padre()?->id;
        if ($groupId !== null) {
            SchoolScheduleShare::query()->updateOrCreate(
                ['school_schedule_id' => $scheduleId, 'school_group_id' => $groupId],
                ['user_id' => null, 'permission' => 'view'],
            );
        }
        if ($padreId !== null) {
            SchoolScheduleShare::query()->updateOrCreate(
                ['school_schedule_id' => $scheduleId, 'user_id' => $padreId],
                ['school_group_id' => null, 'permission' => 'view'],
            );
        }
    }
}
