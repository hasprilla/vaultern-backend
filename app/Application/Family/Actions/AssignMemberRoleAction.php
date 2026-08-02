<?php

declare(strict_types=1);

namespace App\Application\Family\Actions;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\FamilyNotificationService;

/**
 * @phpstan-type AssignSuccess array{ok: true, member_id: string, new_role: string}
 * @phpstan-type AssignFailure array{ok: false, status: int, message: string}
 */
final class AssignMemberRoleAction
{
    public function __construct(
        private readonly FamilyNotificationService $notifications,
    ) {}

    /**
     * @param  array{role: string}  $validated
     * @return AssignSuccess|AssignFailure
     */
    public function execute(User $actor, Family $family, string $memberId, array $validated): array
    {
        $isOwner = $actor->isFamilyOwner()
            || ($family->owner_user_id !== null
                && (int) $family->owner_user_id === (int) $actor->id);
        if (! $isOwner) {
            return [
                'ok' => false,
                'status' => 403,
                'message' => 'Solo el dueño de la membresía puede cambiar roles.',
            ];
        }

        if ((int) $family->owner_user_id === (int) $memberId && $validated['role'] === 'hijo') {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'No puedes cambiar el rol del dueño de la membresía a hijo.',
            ];
        }

        $membership = FamilyMember::query()
            ->where('family_id', $family->id)
            ->where('user_id', $memberId)
            ->firstOrFail();

        if ($membership->status !== 'active') {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Reactiva el acceso del miembro antes de cambiar su rol.',
            ];
        }

        $membership->update(['role' => $validated['role']]);
        User::query()->where('id', $memberId)->update(['role' => $validated['role']]);

        $memberUser = User::query()->findOrFail($memberId);
        $this->notifications->notifyFamily(
            $actor,
            'family_updated',
            'Rol actualizado',
            "{$actor->name} cambió el rol de {$memberUser->name} a {$validated['role']}",
            ['entity_type' => 'user', 'entity_id' => $memberId],
        );

        return [
            'ok' => true,
            'member_id' => (string) $memberId,
            'new_role' => $validated['role'],
        ];
    }
}
