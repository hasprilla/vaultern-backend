<?php

declare(strict_types=1);

namespace App\Application\Family\Actions;

use App\Infrastructure\Auth\TokenService;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\FamilyNotificationService;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-type DeactivateSuccess array{ok: true, member_id: string}
 * @phpstan-type DeactivateFailure array{ok: false, status: int, message: string}
 */
final class DeactivateMemberAction
{
    public function __construct(
        private readonly FamilyNotificationService $notifications,
        private readonly TokenService $tokens,
    ) {}

    /**
     * @return DeactivateSuccess|DeactivateFailure
     */
    public function execute(User $actor, Family $family, string $memberId): array
    {
        $isOwner = $actor->isFamilyOwner()
            || ($family->owner_user_id !== null
                && (int) $family->owner_user_id === (int) $actor->id);
        if (! $isOwner) {
            return [
                'ok' => false,
                'status' => 403,
                'message' => 'Solo el dueño de la membresía puede desactivar a un miembro.',
            ];
        }

        $membership = FamilyMember::query()
            ->where('family_id', $family->id)
            ->where('user_id', $memberId)
            ->firstOrFail();

        // Solo roles del núcleo familiar (no soporte / personal escolar).
        if (! in_array($membership->role, ['padre', 'madre', 'tutor', 'hijo'], true)) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Este tipo de miembro no se puede desactivar desde aquí.',
            ];
        }

        if ((int) $family->owner_user_id === (int) $memberId) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'No puedes desactivar al dueño de la membresía.',
            ];
        }

        if ((int) $actor->id === (int) $memberId) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'No puedes desactivarte a ti mismo desde aquí.',
            ];
        }

        if ($membership->status === 'inactive') {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Este miembro ya está desactivado.',
            ];
        }

        $memberUser = User::query()->findOrFail($memberId);

        DB::transaction(function () use ($membership, $memberUser) {
            $membership->update(['status' => 'inactive']);
            $this->tokens->revoke($memberUser);
        });

        $this->notifications->notifyFamily(
            $actor,
            'family_updated',
            'Acceso desactivado',
            "{$actor->name} desactivó el acceso de {$memberUser->name} al núcleo familiar",
            ['entity_type' => 'user', 'entity_id' => (string) $memberId],
        );

        return ['ok' => true, 'member_id' => (string) $memberId];
    }
}
