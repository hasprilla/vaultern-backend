<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SchoolMeeting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/** ~100 reuniones escolares QA. */
final class QaVolumeMeetingsSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('school_meetings')) {
            return;
        }
        $school = QaSchoolContext::school();
        $docente = QaSchoolContext::docente();
        $class = QaSchoolContext::class3b($school);
        if ($school === null || $docente === null) {
            return;
        }

        QaBulkSupport::each(function (int $i) use ($school, $docente, $class): void {
            $starts = now()->addDays($i % 60);
            SchoolMeeting::query()->updateOrCreate(
                ['school_id' => $school->id, 'title' => "QA Reunión #{$i}"],
                [
                    'campus_id' => $school->main_campus_id,
                    'school_class_id' => $class?->id,
                    'created_by' => $docente->id,
                    'description' => "Reunión QA volumen #{$i}.",
                    'starts_at' => $starts,
                    'ends_at' => $starts->copy()->addHour(),
                    'location' => 'Sala QA',
                    'status' => 'scheduled',
                ],
            );
        });
    }
}
