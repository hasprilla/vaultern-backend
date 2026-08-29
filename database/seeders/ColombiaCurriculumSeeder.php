<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CurriculumBlock;
use App\Models\CurriculumProfile;
use App\Models\CurriculumSubject;
use Illuminate\Database\Seeder;

final class ColombiaCurriculumSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['primaria', 'secundaria'] as $level) {
            $this->seedSubjects($level);
            foreach (['manana', 'tarde'] as $shift) {
                $this->seedProfile($level, $shift);
            }
        }
    }

    private function seedSubjects(string $level): void
    {
        foreach (ColombiaCurriculumCatalog::subjects($level) as $i => $name) {
            CurriculumSubject::query()->updateOrCreate(
                ['country_code' => 'CO', 'level' => $level, 'name' => $name],
                ['sort_order' => $i, 'is_active' => true],
            );
        }
    }

    private function seedProfile(string $level, string $shift): void
    {
        $hours = $level === 'primaria' ? 25 : 30;
        $label = sprintf('CO · %s jornada %s (%d h)', ucfirst($level), $shift, $hours);
        $profile = CurriculumProfile::query()->updateOrCreate(
            ['country_code' => 'CO', 'level' => $level, 'shift' => $shift],
            ['label' => $label, 'weekly_hours' => $hours, 'is_active' => true],
        );
        $profile->blocks()->delete();
        foreach (ColombiaCurriculumCatalog::blocks($level, $shift) as $i => $b) {
            CurriculumBlock::query()->create([
                'curriculum_profile_id' => $profile->id,
                'sort_order' => $i,
                'start_time' => $b[0],
                'end_time' => $b[1],
                'kind' => $b[2],
                'label' => $b[3],
            ]);
        }
    }
}
