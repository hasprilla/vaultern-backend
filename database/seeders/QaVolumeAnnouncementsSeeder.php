<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SchoolAnnouncement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/** ~100 anuncios escolares QA. */
final class QaVolumeAnnouncementsSeeder extends Seeder
{
    /** @var list<string> */
    private const TYPES = ['announcement', 'no_class', 'activity', 'citation'];

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

        QaBulkSupport::each(function (int $i) use ($school, $admin, $class): void {
            SchoolAnnouncement::query()->updateOrCreate(
                ['school_id' => $school->id, 'title' => "QA Anuncio #{$i}"],
                [
                    'campus_id' => $school->main_campus_id,
                    'school_class_id' => $class?->id,
                    'created_by' => $admin->id,
                    'type' => self::TYPES[($i - 1) % 4],
                    'body' => "Contenido de prueba volumen #{$i}.",
                    'data' => ['seed' => 'qa-volume', 'index' => $i],
                    'scheduled_at' => now()->subMinutes($i),
                ],
            );
        });
    }
}
