<?php

declare(strict_types=1);

namespace App\Application\School\Actions;

use App\Models\ClassEnrollment;
use App\Models\User;
use App\Services\FamilyNotificationService;

/**
 * @phpstan-type CancelSuccess array{ok: true}
 * @phpstan-type CancelFailure array{ok: false, status: int, message: string}
 */
final class CancelEnrollmentAction
{
    public function __construct(
        private readonly FamilyNotificationService $notifications,
    ) {}

    /**
     * @return CancelSuccess|CancelFailure
     */
    public function execute(User $actor, ClassEnrollment $enrollment): array
    {
        if (! $actor->canManageTasks()) {
            return ['ok' => false, 'status' => 403, 'message' => 'Forbidden'];
        }

        if ((string) $enrollment->family_id !== (string) $actor->family_id) {
            return ['ok' => false, 'status' => 403, 'message' => 'No autorizado'];
        }

        $enrollment->load(['schoolClass.school', 'student']);
        $studentName = $enrollment->student?->name ?? 'Alumno';
        $schoolName = $enrollment->schoolClass?->school?->name ?? 'Colegio';

        $enrollment->update(['status' => 'cancelled']);

        $this->notifications->notifyFamily(
            $actor,
            'school_enrollment',
            'Inscripción cancelada',
            "{$actor->name} canceló la inscripción de {$studentName} en {$schoolName}",
            ['entity_type' => 'school_enrollment', 'entity_id' => $enrollment->id],
        );

        return ['ok' => true];
    }
}
