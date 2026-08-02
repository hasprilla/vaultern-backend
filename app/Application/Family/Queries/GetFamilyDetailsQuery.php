<?php

declare(strict_types=1);

namespace App\Application\Family\Queries;

use App\Http\Resources\Api\V1\UserResource;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\ChildGuardianService;
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
        $family = Family::query()->findOrFail($viewer->family_id);
        $isOwner = $family->isOwnedBy($viewer);

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

        $visible = $members->filter(function (FamilyMember $m) use ($viewer, $myChildIds) {
            $user = $m->user;
            if ($user === null) {
                return false;
            }
            if ($user->role !== 'hijo') {
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
            'members' => $visible->values()->map(function (FamilyMember $m) {
                $payload = (new UserResource($m->user))->resolve();
                $payload['membership_status'] = $m->status;

                return $payload;
            })->all(),
            'my_child_ids' => array_map('strval', $myChildIds),
        ];
    }
}
