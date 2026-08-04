<?php

declare(strict_types=1);

namespace App\Application\Family\Actions;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\FamilyNotificationService;
use App\Services\PlanFeatureService;
use App\Support\FamilyOwnership;
use App\Support\FamilyRealtime;
use App\Support\SchemaCompat;
use Illuminate\Support\Facades\DB;

/**
 * Dueño actualiza rol, módulos (tareas/finanzas) y/o acceso a hijos de un adulto.
 *
 * @phpstan-type Success array{ok: true, member_id: string, role: string, can_tasks: bool, can_finances: bool}
 * @phpstan-type Failure array{ok: false, status: int, message: string}
 */
final class UpdateMemberAccessAction
{
    public function __construct(
        private readonly FamilyNotificationService $notifications,
        private readonly SyncParentChildAccessAction $syncChildAccess,
        private readonly PlanFeatureService $planFeatures,
    ) {}

    /**
     * @param  array{
     *   role?: string,
     *   can_tasks?: bool,
     *   can_finances?: bool,
     *   child_ids?: list<int|string>
     * }  $validated
     * @return Success|Failure
     */
    public function execute(User $actor, Family $family, string $memberId, array $validated): array
    {
        if (! FamilyOwnership::actorIsOwner($actor, $family)) {
            return [
                'ok' => false,
                'status' => 403,
                'message' => 'Solo el dueño de la membresía puede gestionar acceso y módulos.',
            ];
        }

        if ((int) $actor->id === (int) $memberId) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'No puedes editar tu propio acceso de esta forma.',
            ];
        }

        if ((int) $family->owner_user_id === (int) $memberId) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'No puedes cambiar el acceso del dueño de la membresía.',
            ];
        }

        $membership = FamilyMember::query()
            ->where('family_id', $family->id)
            ->where('user_id', $memberId)
            ->first();

        if ($membership === null) {
            return ['ok' => false, 'status' => 404, 'message' => 'Miembro no encontrado.'];
        }

        if ($membership->status !== 'active') {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Reactiva el acceso del miembro antes de editarlo.',
            ];
        }

        $role = $validated['role'] ?? $membership->role;
        if (! in_array($role, ['padre', 'madre', 'tutor', 'hijo'], true)) {
            return ['ok' => false, 'status' => 422, 'message' => 'Rol no válido.'];
        }

        $canTasks = array_key_exists('can_tasks', $validated)
            ? (bool) $validated['can_tasks']
            : $this->defaultTasks($role);
        $canFinances = array_key_exists('can_finances', $validated)
            ? (bool) $validated['can_finances']
            : $this->defaultFinances($role);

        // El plan familiar es el techo; el dueño solo distribuye dentro de lo comprado.
        $planAllowsTasks = $this->planFeatures->familyAllowsModule($family, 'tasks');
        $planAllowsFinances = $this->planFeatures->familyAllowsModule($family, 'finances');
        if ($canTasks && ! $planAllowsTasks) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Tu plan no incluye el módulo de tareas. Mejora el plan de la familia.',
            ];
        }
        if ($canFinances && ! $planAllowsFinances) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Tu plan no incluye el módulo de finanzas. Mejora el plan de la familia.',
            ];
        }

        if ($role === 'hijo') {
            $canTasks = false;
            $canFinances = false;
        } else {
            if (! $canTasks && ! $canFinances) {
                return [
                    'ok' => false,
                    'status' => 422,
                    'message' => 'Un adulto debe tener al menos el módulo de tareas.',
                ];
            }
            // Finanzas implica tareas.
            if ($canFinances) {
                $canTasks = true;
            }
            // Sin finanzas + rol padre/madre → tutor (conserva módulos).
            if (! $canFinances && in_array($role, ['padre', 'madre'], true) && array_key_exists('can_finances', $validated)) {
                // Mantener padre/madre si can_tasks/can_finances se guardan explícitos.
                // Solo forzar tutor cuando no hay columnas (compat).
                if (! SchemaCompat::hasColumn('family_members', 'can_finances')) {
                    $role = 'tutor';
                }
            }
            if ($canFinances && $role === 'tutor' && ! array_key_exists('role', $validated)) {
                $role = 'padre';
            }
        }

        DB::transaction(function () use ($membership, $memberId, $role, $canTasks, $canFinances) {
            $payload = ['role' => $role];
            if (SchemaCompat::hasColumn('family_members', 'can_tasks')) {
                $payload['can_tasks'] = $canTasks;
                $payload['can_finances'] = $canFinances;
            }
            $membership->update($payload);
            User::query()->where('id', $memberId)->update(['role' => $role]);
        });

        $memberUser = User::query()->findOrFail($memberId);
        $memberUser->refresh();

        if (array_key_exists('child_ids', $validated) && $role !== 'hijo') {
            $childResult = $this->syncChildAccess->execute(
                $actor,
                (string) $family->id,
                $memberUser,
                $validated['child_ids'],
            );
            if (($childResult['ok'] ?? false) !== true) {
                return $childResult;
            }
        } else {
            FamilyRealtime::permissionsChanged(
                familyId: (string) $family->id,
                action: 'member_access_updated',
                parentId: (string) $memberId,
                childIds: [],
                guardianIds: [(int) $memberId],
                actorId: (int) $actor->id,
            );
            $this->notifications->notifyFamily(
                $actor,
                'family_updated',
                'Acceso actualizado',
                "{$actor->name} actualizó el acceso de {$memberUser->name}",
                ['entity_type' => 'user', 'entity_id' => $memberId],
            );
        }

        return [
            'ok' => true,
            'member_id' => (string) $memberId,
            'role' => $role,
            'can_tasks' => $canTasks,
            'can_finances' => $canFinances,
        ];
    }

    private function defaultTasks(string $role): bool
    {
        return in_array($role, ['padre', 'madre', 'tutor'], true);
    }

    private function defaultFinances(string $role): bool
    {
        return in_array($role, ['padre', 'madre'], true);
    }
}
