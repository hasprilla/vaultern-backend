<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SchoolHealthAlert;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/** Alertas de salud QA. */
final class QaSchoolHealthSeeder extends Seeder
{
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

        SchoolHealthAlert::query()->updateOrCreate(
            ['school_id' => $school->id, 'title' => 'Sofía — fiebre'],
            [
                'student_user_id' => $sofia->id,
                'created_by' => $admin->id,
                'type' => 'sick',
                'body' => 'Reportada con fiebre; reposo en casa.',
                'phone_contact_failed' => false,
                'occurred_at' => now()->subHours(3),
            ],
        );
        SchoolHealthAlert::query()->updateOrCreate(
            ['school_id' => $school->id, 'title' => 'Control fin de semana'],
            [
                'student_user_id' => $sofia->id,
                'created_by' => $admin->id,
                'type' => 'weekend',
                'body' => 'Recordatorio de hidratación y descanso.',
                'phone_contact_failed' => true,
                'occurred_at' => now()->subDay(),
            ],
        );
        SchoolHealthAlert::query()->updateOrCreate(
            ['school_id' => $school->id, 'title' => 'Alerta general salud'],
            [
                'student_user_id' => $sofia->id,
                'created_by' => $admin->id,
                'type' => 'health',
                'body' => 'Recordatorio de carné de vacunas al día.',
                'phone_contact_failed' => false,
                'occurred_at' => now()->subDays(2),
            ],
        );
    }
}
