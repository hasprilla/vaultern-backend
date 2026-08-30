<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SchoolAnnouncement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/** Anuncios extra (no_class, citation, activity). */
final class QaSchoolAnnouncementsExtraSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('school_announcements')) {
            return;
        }
        $school = QaSchoolContext::school();
        $admin = QaSchoolContext::admin();
        $class = QaSchoolContext::class3b($school);
        if ($school === null || $admin === null) {
            return;
        }

        foreach ([
            ['Sin clase viernes', 'no_class', 'Jornada pedagógica: no hay clases.'],
            ['Citación padres Sofía', 'citation', 'Citación por rendimiento — coordinación.'],
            ['Feria de ciencias', 'activity', 'Inscripciones abiertas hasta el viernes.'],
        ] as [$title, $type, $body]) {
            SchoolAnnouncement::query()->updateOrCreate(
                ['school_id' => $school->id, 'title' => $title],
                [
                    'campus_id' => $school->main_campus_id,
                    'school_class_id' => $class?->id,
                    'created_by' => $admin->id,
                    'type' => $type,
                    'body' => $body,
                    'data' => ['seed' => 'qa-extra'],
                    'scheduled_at' => now()->subMinutes(30),
                ],
            );
        }
    }
}
