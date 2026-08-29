<?php

declare(strict_types=1);

namespace App\Application\School\Queries;

use App\Models\ClassEnrollment;
use App\Models\SchoolAttendanceLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class ListMySchoolAttendanceQuery
{
    /** @return list<array<string, mixed>> */
    public function handle(User $actor, ?string $date = null): array
    {
        abort_if($actor->family_id === null, 403);

        $day = Carbon::parse($date ?? now()->toDateString())->toDateString();

        $enrollments = ClassEnrollment::query()
            ->with(['schoolClass.school:id,name', 'student:id,name'])
            ->where('family_id', $actor->family_id)
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->get();

        $studentIds = $enrollments->pluck('student_user_id')->unique()->values();
        $logs = SchoolAttendanceLog::query()
            ->whereIn('student_user_id', $studentIds)
            ->whereDate('attendance_date', $day)
            ->get()
            ->keyBy('student_user_id');

        return $enrollments->map(fn (ClassEnrollment $e) => $this->mapRow($e, $logs, $day))->values()->all();
    }

    /** @param Collection<int|string, SchoolAttendanceLog> $logs */
    private function mapRow(ClassEnrollment $e, Collection $logs, string $day): array
    {
        $log = $logs->get($e->student_user_id);

        return [
            'enrollment_id' => (string) $e->id,
            'student_user_id' => (int) $e->student_user_id,
            'student_name' => $e->student?->name,
            'school_id' => (string) ($e->schoolClass?->school_id ?? ''),
            'school_name' => $e->schoolClass?->school?->name,
            'class_name' => $e->schoolClass?->name,
            'attendance_date' => $day,
            'attendance' => $log === null ? null : [
                'id' => (string) $log->id,
                'status' => $log->status,
                'note' => $log->note,
            ],
        ];
    }
}
