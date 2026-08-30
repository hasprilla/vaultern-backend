<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SchoolGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/** ~100 grupos escolares QA (sin members masivos). */
final class QaVolumeGroupsSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('school_groups')) {
            return;
        }
        $school = QaSchoolContext::school();
        $admin = QaSchoolContext::admin();
        if ($school === null || $admin === null) {
            return;
        }

        QaBulkSupport::each(function (int $i) use ($school, $admin): void {
            SchoolGroup::query()->updateOrCreate(
                ['school_id' => $school->id, 'name' => "QA Grupo #{$i}"],
                [
                    'campus_id' => $school->main_campus_id,
                    'type' => 'mixed',
                    'description' => "Grupo QA volumen #{$i}.",
                    'created_by' => $admin->id,
                    'is_active' => true,
                ],
            );
        });
    }
}
