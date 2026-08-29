<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\School;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\SchoolAttendanceLog */
final class SchoolAttendanceLogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'school_id' => (string) $this->school_id,
            'family_id' => (string) $this->family_id,
            'student_user_id' => (int) $this->student_user_id,
            'reported_by' => (int) $this->reported_by,
            'attendance_date' => $this->attendance_date?->toDateString(),
            'status' => $this->status,
            'note' => $this->note,
        ];
    }
}
