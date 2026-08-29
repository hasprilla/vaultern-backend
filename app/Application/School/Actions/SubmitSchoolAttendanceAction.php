<?php

declare(strict_types=1);

namespace App\Application\School\Actions;

use App\Models\ClassEnrollment;
use App\Models\SchoolAttendanceLog;
use App\Models\User;
use App\Services\FamilyNotificationService;
use Carbon\Carbon;

final class SubmitSchoolAttendanceAction
{
    public function __construct(private readonly FamilyNotificationService $notifications) {}

    /**
     * @param  array{student_user_id: int, status: string, attendance_date?: string, note?: string|null}  $data
     */
    public function execute(User $actor, array $data): SchoolAttendanceLog
    {
        abort_if($actor->family_id === null, 403);
        abort_if(! $actor->canManageTasks(), 403);

        $enrollment = ClassEnrollment::query()
            ->with('schoolClass')
            ->where('family_id', $actor->family_id)
            ->where('student_user_id', $data['student_user_id'])
            ->where('status', 'active')
            ->first();
        abort_if($enrollment === null || $enrollment->schoolClass === null, 404);

        $day = Carbon::parse($data['attendance_date'] ?? now()->toDateString())->toDateString();
        $log = SchoolAttendanceLog::query()->updateOrCreate(
            [
                'student_user_id' => (int) $data['student_user_id'],
                'attendance_date' => $day,
            ],
            [
                'school_id' => $enrollment->schoolClass->school_id,
                'family_id' => $actor->family_id,
                'reported_by' => $actor->id,
                'status' => $data['status'],
                'note' => $data['note'] ?? null,
            ],
        );

        $name = User::query()->where('id', $data['student_user_id'])->value('name') ?? 'Hijo';
        $this->notifications->notifyFamily(
            $actor,
            'school_attendance',
            'Asistencia escolar',
            "{$actor->name} registró asistencia de {$name}: {$data['status']}",
            [
                'entity_type' => 'school_attendance',
                'entity_id' => $log->id,
                'student_user_id' => (int) $data['student_user_id'],
                'status' => $data['status'],
                'attendance_date' => $day,
            ],
        );

        return $log;
    }
}
