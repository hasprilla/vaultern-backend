<?php

declare(strict_types=1);

namespace App\Application\Family\Actions;

use App\Models\FamilyMember;
use App\Models\User;
use App\Services\ChildGuardianService;

/**
 * @phpstan-type SyncSuccess array{ok: true, child: User}
 * @phpstan-type SyncFailure array{ok: false, status: int, message: string}
 */
final class SyncChildGuardiansAction
{
    public function __construct(
        private readonly ChildGuardianService $guardians,
    ) {}

    /**
     * @param  list<int>  $guardianIds
     * @return SyncSuccess|SyncFailure
     */
    public function execute(User $actor, string $familyId, User $child, array $guardianIds): array
    {
        if (! $actor->isFamilyOwner()) {
            return [
                'ok' => false,
                'status' => 403,
                'message' => 'Solo el dueño de la membresía puede definir quién ve la información de cada hijo.',
            ];
        }

        $activeGuardianIds = FamilyMember::query()
            ->where('family_id', $familyId)
            ->where('status', 'active')
            ->whereIn('role', ['padre', 'madre', 'tutor'])
            ->whereIn('user_id', $guardianIds)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($activeGuardianIds) !== count(array_unique($guardianIds))) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Solo puedes asignar custodios con acceso activo al núcleo familiar.',
            ];
        }

        $this->guardians->syncForChild($child, $guardianIds);
        $child->load('guardians');

        return ['ok' => true, 'child' => $child];
    }
}
