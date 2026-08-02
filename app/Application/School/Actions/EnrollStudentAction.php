<?php

declare(strict_types=1);

namespace App\Application\School\Actions;

use App\Models\ClassEnrollment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\FamilyNotificationService;

/**
 * @phpstan-type EnrollSuccess array{ok: true, enrollment: ClassEnrollment}
 * @phpstan-type EnrollFailure array{ok: false, status: int, message: string}
 */
final class EnrollStudentAction
{
    public function __construct(
        private readonly FamilyNotificationService $notifications,
    ) {}

    /**
     * @param  array{school_code: string, school_class_id: string, student_user_id: int}  $validated
     * @return EnrollSuccess|EnrollFailure
     */
    public function execute(User $actor, array $validated): array
    {
        if (! $actor->canManageTasks()) {
            return ['ok' => false, 'status' => 403, 'message' => 'Forbidden'];
        }

        $school = School::query()
            ->where('code', strtoupper($validated['school_code']))
            ->where('is_active', true)
            ->firstOrFail();

        $class = SchoolClass::query()
            ->where('school_id', $school->id)
            ->findOrFail($validated['school_class_id']);

        $student = User::query()->findOrFail($validated['student_user_id']);

        if ((string) $student->family_id !== (string) $actor->family_id || $student->role !== 'hijo') {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'El alumno debe ser un hijo de tu familia',
            ];
        }

        $enrollment = ClassEnrollment::query()->updateOrCreate(
            [
                'school_class_id' => $class->id,
                'student_user_id' => $student->id,
            ],
            [
                'family_id' => $student->family_id,
                'enrolled_by' => $actor->id,
                'status' => 'active',
            ],
        );

        $enrollment->load(['schoolClass.school', 'student']);

        $this->notifications->notifyFamily(
            $actor,
            'school_enrollment',
            'Inscripción escolar',
            "{$actor->name} inscribió a {$student->name} en {$school->name}",
            ['entity_type' => 'school_enrollment', 'entity_id' => $enrollment->id],
        );

        return ['ok' => true, 'enrollment' => $enrollment];
    }
}
