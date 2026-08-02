<?php

declare(strict_types=1);

namespace App\Application\Family\Actions;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\ChildGuardianService;
use App\Services\FamilyNotificationService;
use App\Support\FamilyOwnership;
use App\Support\FamilyRealtime;

/**
 * @phpstan-type SyncSuccess array{ok: true, child: User}
 * @phpstan-type SyncFailure array{ok: false, status: int, message: string}
 */
final class SyncChildGuardiansAction
{
    public function __construct(
        private readonly ChildGuardianService $guardians,
        private readonly FamilyNotificationService $notifications,
    ) {}

    /**
     * @param  list<int>  $guardianIds
     * @return SyncSuccess|SyncFailure
     */
    public function execute(User $actor, string $familyId, User $child, array $guardianIds): array
    {
        $family = Family::query()->find($familyId);
        $isOwner = FamilyOwnership::actorIsOwner($actor, $family);
        $isGuardian = $this->guardians->isGuardianOf($actor, (int) $child->id);
        // Dueño de la membresía, o custodio ya autorizado sobre ese hijo.
        if (! $isOwner && ! $isGuardian) {
            return [
                'ok' => false,
                'status' => 403,
                'message' => 'Solo el dueño de la membresía o un custodio de este hijo puede definir quién ve su información.',
            ];
        }

        $uniqueIds = array_values(array_unique(array_map('intval', $guardianIds)));
        if ($uniqueIds === []) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Debes asignar al menos un custodio.',
            ];
        }

        $activeGuardianIds = FamilyMember::query()
            ->where('family_id', $familyId)
            ->where('status', 'active')
            ->whereIn('role', ['padre', 'madre', 'tutor'])
            ->whereIn('user_id', $uniqueIds)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (count($activeGuardianIds) !== count($uniqueIds)) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Solo puedes asignar padres, madres o tutores con acceso activo al núcleo.',
            ];
        }

        $this->guardians->syncForChild($child, $activeGuardianIds);
        $child->load('guardians');

        FamilyRealtime::permissionsChanged(
            familyId: $familyId,
            action: 'child_guardians_synced',
            childId: (string) $child->id,
            childIds: [(string) $child->id],
            guardianIds: $activeGuardianIds,
            actorId: (int) $actor->id,
        );

        $this->notifications->notifyFamily(
            $actor,
            'family_permissions',
            'Permisos actualizados',
            "Se actualizó quién puede ver la información de {$child->name}.",
            [
                'entity_type' => 'family_permissions',
                'entity_id' => (string) $child->id,
                'child_id' => (string) $child->id,
                'action' => 'child_guardians_synced',
            ],
        );

        return ['ok' => true, 'child' => $child];
    }
}
