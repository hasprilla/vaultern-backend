<?php

declare(strict_types=1);

namespace App\Application\School\Actions;

use App\Models\School;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\FamilyNotificationService;

/**
 * @phpstan-type RegisterSuccess array{ok: true, school: School}
 * @phpstan-type RegisterFailure array{ok: false, status: int, message: string}
 */
final class RegisterSchoolAction
{
    public function __construct(
        private readonly FamilyNotificationService $notifications,
    ) {}

    /**
     * @param  array{name: string, city?: string|null, class_name: string}  $validated
     * @return RegisterSuccess|RegisterFailure
     */
    public function execute(User $actor, array $validated): array
    {
        if (! $actor->canManageTasks()) {
            return [
                'ok' => false,
                'status' => 403,
                'message' => 'Solo padres, madres o tutores pueden registrar un colegio.',
            ];
        }

        $school = School::query()->create([
            'name' => $validated['name'],
            'city' => $validated['city'] ?? null,
            'plan' => 'school',
            'is_active' => true,
        ]);

        SchoolClass::query()->create([
            'school_id' => $school->id,
            'name' => $validated['class_name'],
            'school_year' => now()->format('Y').'-'.(now()->year + 1),
        ]);

        if ($actor->role === 'docente') {
            $actor->teacherMemberships()->firstOrCreate(
                ['school_id' => $school->id],
                ['role' => 'teacher', 'status' => 'active'],
            );
        }

        $school->load(['classes' => fn ($q) => $q->orderBy('name')]);

        $this->notifications->notifyFamily(
            $actor,
            'school_registered',
            'Colegio registrado',
            "{$actor->name} registró el colegio {$school->name} (código {$school->code})",
            ['entity_type' => 'school', 'entity_id' => $school->id],
        );

        return ['ok' => true, 'school' => $school];
    }
}
