<?php

declare(strict_types=1);

namespace App\Application\Family\Actions;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\FamilyNotificationService;

/**
 * @phpstan-type ReactivateSuccess array{ok: true, member_id: string}
 * @phpstan-type ReactivateFailure array{ok: false, status: int, message: string}
 */
final class ReactivateMemberAction
{
    public function __construct(
        private readonly FamilyNotificationService $notifications,
    ) {}

    /**
     * @return ReactivateSuccess|ReactivateFailure
     */
    public function execute(User $actor, string $familyId, string $memberId): array
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
                'message' => 'Solo el dueño de la membresía puede reactivar a un miembro.',
            ];
        }

        $membership = FamilyMember::query()
            ->where('family_id', $familyId)
            ->where('user_id', $memberId)
            ->firstOrFail();

        if (! in_array($membership->role, ['padre', 'madre', 'tutor', 'hijo'], true)) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Este tipo de miembro no se puede reactivar desde aquí.',
            ];
        }

        if ($membership->status === 'active') {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Este miembro ya tiene acceso activo.',
            ];
        }

        $memberUser = User::query()->findOrFail($memberId);
        $membership->update(['status' => 'active']);

        $this->notifications->notifyFamily(
            $actor,
            'family_updated',
            'Acceso reactivado',
            "{$actor->name} reactivó el acceso de {$memberUser->name} al núcleo familiar",
            ['entity_type' => 'user', 'entity_id' => (string) $memberId],
        );

        return ['ok' => true, 'member_id' => (string) $memberId];
    }
}
