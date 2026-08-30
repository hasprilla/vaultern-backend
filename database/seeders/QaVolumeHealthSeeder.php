<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SchoolHealthAlert;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/** ~100 alertas de salud QA. */
final class QaVolumeHealthSeeder extends Seeder
{
    /** @var list<string> */
    private const TYPES = ['sick', 'health', 'weekend'];

    public function run(): void
    {
        if (! Schema::hasTable('school_health_alerts')) {
            return;
        }
        $school = QaSchoolContext::school();
        $admin = QaSchoolContext::admin();
        $sofia = QaSchoolContext::hijo();
        if ($school === null || $admin === null || $sofia === null) {
            return;
        }

        QaBulkSupport::each(function (int $i) use ($school, $admin, $sofia): void {
            SchoolHealthAlert::query()->updateOrCreate(
                ['school_id' => $school->id, 'title' => "QA Salud #{$i}"],
                [
                    'student_user_id' => $sofia->id,
                    'created_by' => $admin->id,
                    'type' => self::TYPES[($i - 1) % 3],
                    'body' => "Alerta salud QA volumen #{$i}.",
                    'phone_contact_failed' => $i % 5 === 0,
                    'occurred_at' => now()->subHours($i),
                ],
            );
        });
    }
}
