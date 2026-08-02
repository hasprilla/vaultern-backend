<?php

declare(strict_types=1);

namespace App\Application\Family\Actions;

use App\Infrastructure\Auth\TokenService;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\FamilyNotificationService;
use App\Support\FamilyOwnership;
use Illuminate\Support\Facades\DB;

/**
 * Desactiva la membresía de un usuario en UN núcleo familiar.
 * No desactiva la cuenta ni bloquea el login global: el miembro puede seguir
 * en otras familias. Solo account_status=deactivated/deleted bloquea sesión.
 *
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
        if (! FamilyOwnership::actorIsOwner($actor, $family)) {
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
                'message' => 'Este miembro ya está desactivado en este núcleo.',
            ];
        }

        $memberUser = User::query()->findOrFail($memberId);
        $wasCurrentFamily = (string) $memberUser->family_id === (string) $family->id;

        DB::transaction(function () use ($membership, $memberUser, $wasCurrentFamily, $family) {
            // Solo esta fila de family_members. Nunca account_status.
            $membership->update(['status' => 'inactive']);
            $memberUser->clearFamilyMembershipCache();

            if ($wasCurrentFamily) {
                // Si tiene otro núcleo activo, cambia el contexto; si no, deja family_id
                // apuntando a este (inactivo) para que el tenant lo rechace solo aquí.
                $switched = $memberUser->ensureActiveFamilyContext();
                if (! $switched && (string) $memberUser->family_id === (string) $family->id) {
                    // Sin otros núcleos: conserva family_id (historial) pero sin membresía activa.
                    $memberUser->clearFamilyMembershipCache();
                }
            }

            // Cierra sesión solo si este era su núcleo actual (fuerza re-login con nuevo contexto).
            // No afecta account_status ni otras membresías.
            if ($wasCurrentFamily) {
                $this->tokens->revoke($memberUser);
            }
        });

        $this->notifications->notifyFamily(
            $actor,
            'family_updated',
            'Acceso desactivado en este núcleo',
            "{$actor->name} desactivó el acceso de {$memberUser->name} solo en este núcleo familiar. Su cuenta y otras familias no se ven afectadas.",
            ['entity_type' => 'user', 'entity_id' => (string) $memberId],
        );

        return ['ok' => true, 'member_id' => (string) $memberId];
    }
}
