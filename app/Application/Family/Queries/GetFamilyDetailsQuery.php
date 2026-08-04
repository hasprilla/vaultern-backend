<?php

declare(strict_types=1);

namespace App\Application\Family\Queries;

use App\Http\Resources\Api\V1\UserResource;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\ChildGuardianService;
use App\Support\FamilyOwnership;
use App\Support\SchemaCompat;

final class GetFamilyDetailsQuery
{
    public function __construct(
        private readonly ChildGuardianService $guardians,
    ) {}

    /**
     * @return array{
     *   id: mixed,
     *   name: mixed,
     *   plan: mixed,
     *   invite_code: mixed,
     *   owner_user_id: string|null,
     *   is_owner: bool,
     *   members: list<array<string, mixed>>,
     *   my_child_ids: list<string>
     * }
     */
    public function execute(User $viewer): array
    {
        if ($viewer->family_id === null || $viewer->family_id === '') {
            return [
                'id' => null,
                'name' => null,
                'plan' => 'free',
                'invite_code' => null,
                'owner_user_id' => null,
                'is_owner' => false,
                'viewer_user_id' => (string) $viewer->id,
                'members' => [],
                'my_child_ids' => [],
                'has_family' => false,
                'empty_reason' => 'no_family',
            ];
        }

        $family = Family::query()->find($viewer->family_id);
        if ($family === null) {
            return [
                'id' => null,
                'name' => null,
                'plan' => 'free',
                'invite_code' => null,
                'owner_user_id' => null,
                'is_owner' => false,
                'viewer_user_id' => (string) $viewer->id,
                'members' => [],
                'my_child_ids' => [],
                'has_family' => false,
                'empty_reason' => 'family_missing',
            ];
        }

        $family->refresh();

        // Si la columna está vacía, auto-asignar al primer padre/madre (suele ser quien creó la cuenta).
        if ($family->owner_user_id === null
            && SchemaCompat::hasColumn('families', 'owner_user_id')
            && in_array($viewer->role, ['padre', 'madre'], true)) {
            $firstParentId = User::query()
                ->where('family_id', $family->id)
                ->whereIn('role', ['padre', 'madre'])
                ->orderBy('id')
                ->value('id');
            if ($firstParentId !== null && (int) $firstParentId === (int) $viewer->id) {
                $family->forceFill(['owner_user_id' => $viewer->id])->save();
                $family->refresh();
            }
        }

        $isOwner = FamilyOwnership::actorIsOwner($viewer, $family);

        $with = SchemaCompat::hasTable('child_guardians')
            ? ['user.guardians']
            : ['user'];

        $membersQuery = FamilyMember::query()
            ->with($with)
            ->where('family_id', $family->id);

        if ($isOwner) {
            // Activos de cualquier rol + inactivos del núcleo (para reactivar).
            $membersQuery->where(function ($query) {
                $query->where('status', 'active')
                    ->orWhere(function ($inactive) {
                        $inactive->where('status', 'inactive')
                            ->whereIn('role', ['padre', 'madre', 'tutor', 'hijo']);
                    });
            });
        } else {
            $membersQuery->where('status', 'active');
        }

        $members = $membersQuery->get();
        $myChildIds = $this->guardians->childIdsFor($viewer);

        $visible = $members->filter(function (FamilyMember $m) use ($viewer, $myChildIds, $isOwner) {
            $user = $m->user;
            if ($user === null) {
                return false;
            }
            if ($user->role !== 'hijo') {
                return true;
            }
            // Dueño ve todos los hijos para administrar custodios / acceso.
            if ($isOwner) {
                return true;
            }
            if (! in_array($viewer->role, ['padre', 'madre', 'tutor'], true)) {
                return true;
            }

            return in_array((int) $user->id, $myChildIds, true);
        });

        return [
            'id' => $family->id,
            'name' => $family->name,
            'plan' => $family->plan,
            'invite_code' => $family->invite_code,
            'owner_user_id' => $family->owner_user_id !== null ? (string) $family->owner_user_id : null,
            'is_owner' => $isOwner,
            'viewer_user_id' => (string) $viewer->id,
            'members' => $visible->values()->map(function (FamilyMember $m) {
                $payload = (new UserResource($m->user))->resolve();
                $payload['membership_status'] = $m->status;

                $role = (string) ($m->role ?? $m->user?->role ?? '');
                $defaultTasks = in_array($role, ['padre', 'madre', 'tutor'], true);
                $defaultFinances = in_array($role, ['padre', 'madre'], true);

                if (SchemaCompat::hasColumn('family_members', 'can_tasks')) {
                    $payload['can_tasks'] = $m->can_tasks ?? $defaultTasks;
                    $payload['can_finances'] = $m->can_finances ?? $defaultFinances;
                } else {
                    $payload['can_tasks'] = $defaultTasks;
                    $payload['can_finances'] = $defaultFinances;
                }

                return $payload;
            })->all(),
            'my_child_ids' => array_map('strval', $myChildIds),
            'has_family' => true,
        ];
    }
}
