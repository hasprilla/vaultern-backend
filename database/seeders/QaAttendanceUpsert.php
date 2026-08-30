<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SchoolAttendanceLog;
use Illuminate\Support\Carbon;

/** Upsert de asistencia evitando fallo where= vs date cast en SQLite. */
final class QaAttendanceUpsert
{
    /**
     * @param  array<string, mixed>  $attrs
     */
    public static function put(int $studentUserId, Carbon|string $date, array $attrs): void
    {
        $day = $date instanceof Carbon ? $date->toDateString() : $date;
        $row = SchoolAttendanceLog::query()
            ->where('student_user_id', $studentUserId)
            ->whereDate('attendance_date', $day)
            ->first();

        if ($row === null) {
            $row = new SchoolAttendanceLog([
                'student_user_id' => $studentUserId,
                'attendance_date' => $day,
            ]);
        }

        $row->fill($attrs);
        $row->student_user_id = $studentUserId;
        $row->attendance_date = $day;
        $row->save();
    }
}
