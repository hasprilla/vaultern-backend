<?php

declare(strict_types=1);

namespace App\Application\Family\Actions;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\ChildGuardianService;

/**
 * El dueño otorga a un padre/madre/tutor acceso a hijos concretos.
 *
 * @phpstan-type SyncSuccess array{ok: true, parent_id: string, child_ids: list<string>}
 * @phpstan-type SyncFailure array{ok: false, status: int, message: string}
 */
final class SyncParentChildAccessAction
{
    public function __construct(
        private readonly ChildGuardianService $guardians,
    ) {}

    /**
     * @param  list<int|string>  $childIds
     * @return SyncSuccess|SyncFailure
     */
    public function execute(User $actor, string $familyId, User $parent, array $childIds): array
    {
        $family = Family::query()->find($familyId);
        $isOwner = $actor->isFamilyOwner()
            || ($family !== null
                && $family->owner_user_id !== null
                && (int) $family->owner_user_id === (int) $actor->id);

        if (! $isOwner) {
            return [
                'ok' => false,
                'status' => 403,
                'message' => 'Solo el dueño de la membresía puede otorgar permisos a padres, madres o tutores.',
            ];
        }

        if (! in_array($parent->role, ['padre', 'madre', 'tutor'], true)
            || (string) $parent->family_id !== (string) $familyId) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Solo puedes otorgar permisos a padres, madres o tutores del núcleo.',
            ];
        }

        $active = FamilyMember::query()
            ->where('family_id', $familyId)
            ->where('user_id', $parent->id)
            ->where('status', 'active')
            ->exists();

        if (! $active) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Ese miembro no tiene acceso activo al núcleo.',
            ];
        }

        $wanted = array_values(array_unique(array_map('intval', $childIds)));

        $children = User::query()
            ->where('family_id', $familyId)
            ->where('role', 'hijo')
            ->get();

        $validChildIds = $children->pluck('id')->map(fn ($id) => (int) $id)->all();
        foreach ($wanted as $id) {
            if (! in_array($id, $validChildIds, true)) {
                return [
                    'ok' => false,
                    'status' => 422,
                    'message' => 'Uno o más hijos no pertenecen a esta familia.',
                ];
            }
        }

        $fallbackGuardianId = $family?->owner_user_id !== null
            ? (int) $family->owner_user_id
            : (int) $actor->id;

        foreach ($children as $child) {
            $current = $this->guardians->guardianIdsOfChild((int) $child->id);
            $shouldHave = in_array((int) $child->id, $wanted, true);

            if ($shouldHave) {
                $next = array_values(array_unique([...$current, (int) $parent->id]));
            } else {
                $next = array_values(array_filter(
                    $current,
                    static fn (int $id) => $id !== (int) $parent->id,
                ));
                if ($next === []) {
                    $next = [$fallbackGuardianId];
                }
            }

            $this->guardians->syncForChild($child, $next);
        }

        return [
            'ok' => true,
            'parent_id' => (string) $parent->id,
            'child_ids' => array_map('strval', $wanted),
        ];
    }
}
