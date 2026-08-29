<?php

declare(strict_types=1);

namespace App\Application\School\Actions;

use App\Models\SchoolSchedule;

final class UpdateSchoolScheduleAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(SchoolSchedule $schedule, array $data): SchoolSchedule
    {
        $schedule->update($data);

        return $schedule->fresh();
    }
}
