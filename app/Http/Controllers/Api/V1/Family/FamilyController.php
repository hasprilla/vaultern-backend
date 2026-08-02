<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Family;

use App\Application\Family\Actions\ApproveJoinRequestAction;
use App\Application\Family\Actions\AssignMemberRoleAction;
use App\Application\Family\Actions\CreateFamilyAction;
use App\Application\Family\Actions\DeactivateMemberAction;
use App\Application\Family\Actions\InviteMemberAction;
use App\Application\Family\Actions\ReactivateMemberAction;
use App\Application\Family\Actions\RegisterChildAction;
use App\Application\Family\Actions\RejectJoinRequestAction;
use App\Application\Family\Actions\SyncChildGuardiansAction;
use App\Application\Family\Actions\UpdateFamilyAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Family\CreateFamilyRequest;
use App\Http\Requests\Api\V1\Family\InviteMemberRequest;
use App\Http\Requests\Api\V1\Family\RegisterChildRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Family;
use App\Models\FamilyJoinRequest;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\ChildGuardianService;
use App\Support\SchemaCompat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FamilyController extends Controller
{
    public function __construct(
        private readonly ChildGuardianService $guardians,
        private readonly RegisterChildAction $registerChildAction,
        private readonly CreateFamilyAction $createFamily,
        private readonly InviteMemberAction $inviteMember,
        private readonly UpdateFamilyAction $updateFamily,
        private readonly ApproveJoinRequestAction $approveJoinRequestAction,
        private readonly RejectJoinRequestAction $rejectJoinRequestAction,
        private readonly AssignMemberRoleAction $assignMemberRole,
        private readonly SyncChildGuardiansAction $syncChildGuardiansAction,
        private readonly DeactivateMemberAction $deactivateMemberAction,
        private readonly ReactivateMemberAction $reactivateMemberAction,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $viewer = $request->user();
        $family = Family::query()->findOrFail($viewer->family_id);
        $isOwner = $family->isOwnedBy($viewer);

        $with = SchemaCompat::hasTable('child_guardians')
            ? ['user.guardians']
            : ['user'];

        $membersQuery = FamilyMember::query()
            ->with($with)
            ->where('family_id', $family->id);

        if ($isOwner) {
            // El dueño ve activos + padres/madres desactivados (para poder reactivarlos).
            $membersQuery->where(function ($query) {
                $query->where('status', 'active')
                    ->orWhere(function ($inactive) {
                        $inactive->where('status', 'inactive')
                            ->whereIn('role', ['padre', 'madre']);
                    });
            });
        } else {
            $membersQuery->where('status', 'active');
        }

        $members = $membersQuery->get();

        $myChildIds = $this->guardians->childIdsFor($viewer);

        // Padres/madres solo ven hijos de los que son custodios; adultos siempre visibles.
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

        return response()->json([
            'data' => [
                'id'             => $family->id,
                'name'           => $family->name,
                'plan'           => $family->plan,
                'invite_code'    => $family->invite_code,
                'owner_user_id'  => $family->owner_user_id !== null ? (string) $family->owner_user_id : null,
                'is_owner'       => $isOwner,
                'members'        => $visible->values()->map(function (FamilyMember $m) {
                    $payload = (new UserResource($m->user))->resolve();
                    $payload['membership_status'] = $m->status;

                    return $payload;
                }),
                'my_child_ids'   => array_map('strval', $myChildIds),
            ],
        ]);
    }

    public function store(CreateFamilyRequest $request): JsonResponse
    {
        $result = $this->createFamily->execute($request->user(), $request->validated());

        if (($result['ok'] ?? false) !== true) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        $family = $result['family'];
        $user = $request->user()->fresh();

        return response()->json([
            'data' => [
                'id' => $family->id,
                'name' => $family->name,
                'plan' => $family->plan,
                'owner_user_id' => (string) $user->id,
                'is_owner' => true,
            ],
        ], 201);
    }

    public function show(Request $request, string $family): JsonResponse
    {
        $this->assertFamilyAccess($request, $family);

        return $this->index($request);
    }

    public function update(Request $request, string $family): JsonResponse
    {
        $this->assertFamilyAccess($request, $family);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:120'],
            'plan' => ['sometimes', 'string', 'in:free,premium'],
        ]);

        $model = Family::query()->findOrFail($family);
        $result = $this->updateFamily->execute($request->user(), $model, $validated);

        if (($result['ok'] ?? false) !== true) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        return response()->json(['data' => $result['family']->only(['id', 'name', 'plan'])]);
    }

    public function destroy(Request $request, string $family): JsonResponse
    {
        $this->assertFamilyAccess($request, $family);

        if (! $request->user()->canManageFinances()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json(['message' => 'Family deletion is disabled'], 403);
    }

    public function invite(InviteMemberRequest $request, string $family): JsonResponse
    {
        $this->assertFamilyAccess($request, $family);

        $result = $this->inviteMember->execute($request->user(), $request->validated());

        if (($result['ok'] ?? false) !== true) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        return response()->json([
            'message' => 'Invitation sent successfully',
            'data' => [
                'email' => $result['email'],
                'role' => $result['role'],
                'status' => 'pending',
            ],
        ], 202);
    }

    public function registerChild(RegisterChildRequest $request, string $family): JsonResponse
    {
        $this->assertFamilyAccess($request, $family);

        if (! $request->user()->familyRole()->canInviteMembers()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $familyModel = Family::query()->findOrFail($family);
        $result = $this->registerChildAction->execute(
            $request->user(),
            $familyModel,
            $request->validated(),
        );

        if (($result['ok'] ?? false) !== true) {
            $payload = ['message' => $result['message']];
            if (isset($result['code'])) {
                $payload['code'] = $result['code'];
            }

            return response()->json($payload, $result['status']);
        }

        return response()->json(['data' => new UserResource($result['child'])], 201);
    }

    public function syncChildGuardians(Request $request, string $family, string $child): JsonResponse
    {
        $this->assertFamilyAccess($request, $family);

        $childUser = User::query()
            ->where('family_id', $family)
            ->where('role', 'hijo')
            ->findOrFail($child);

        $validated = $request->validate([
            'guardian_ids'   => ['required', 'array', 'min:1'],
            'guardian_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $ids = array_map('intval', $validated['guardian_ids']);
        $result = $this->syncChildGuardiansAction->execute(
            $request->user(),
            $family,
            $childUser,
            $ids,
        );

        if (($result['ok'] ?? false) !== true) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        return response()->json(['data' => new UserResource($result['child'])]);
    }

    public function joinRequests(Request $request, string $family): JsonResponse
    {
        $this->assertFamilyAccess($request, $family);

        if (! $request->user()->familyRole()->canInviteMembers()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $requests = FamilyJoinRequest::query()
            ->where('family_id', $family)
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'data' => $requests->map(fn (FamilyJoinRequest $r) => [
                'id'                 => $r->id,
                'name'               => $r->name,
                'email'              => $r->email,
                'role'               => $r->role,
                'status'             => $r->status,
                'invited_by_user_id' => $r->invited_by_user_id,
                'created_at'         => $r->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function approveJoinRequest(Request $request, string $family, string $joinRequest): JsonResponse
    {
        $this->assertFamilyAccess($request, $family);

        $model = FamilyJoinRequest::query()
            ->where('family_id', $family)
            ->findOrFail($joinRequest);

        $user = $this->approveJoinRequestAction->execute($request->user(), $model);

        return response()->json([
            'message' => 'Solicitud aprobada. La persona ya puede iniciar sesión.',
            'data' => new UserResource($user),
        ]);
    }

    public function rejectJoinRequest(Request $request, string $family, string $joinRequest): JsonResponse
    {
        $this->assertFamilyAccess($request, $family);

        $model = FamilyJoinRequest::query()
            ->where('family_id', $family)
            ->findOrFail($joinRequest);

        $this->rejectJoinRequestAction->execute($request->user(), $model);

        return response()->json(['message' => 'Solicitud rechazada']);
    }

    public function assignRole(Request $request, string $family, string $member): JsonResponse
    {
        $this->assertFamilyAccess($request, $family);

        $validated = $request->validate([
            'role' => ['required', 'in:padre,madre,tutor,hijo'],
        ]);

        $familyModel = Family::query()->findOrFail($family);
        $result = $this->assignMemberRole->execute(
            $request->user(),
            $familyModel,
            $member,
            $validated,
        );

        if (($result['ok'] ?? false) !== true) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        return response()->json([
            'message' => 'Role updated successfully',
            'data' => [
                'member_id' => $result['member_id'],
                'new_role' => $result['new_role'],
            ],
        ]);
    }

    /**
     * El dueño desactiva a un padre/madre sin borrar datos.
     * Queda sin poder realizar acciones del núcleo familiar hasta reactivación.
     */
    public function deactivateMember(Request $request, string $family, string $member): JsonResponse
    {
        $this->assertFamilyAccess($request, $family);

        $familyModel = Family::query()->findOrFail($family);
        $result = $this->deactivateMemberAction->execute($request->user(), $familyModel, $member);

        if (($result['ok'] ?? false) !== true) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        return response()->json([
            'message' => 'Acceso desactivado. La información se conserva y puedes reactivarlo cuando quieras.',
            'data' => [
                'member_id' => $result['member_id'],
                'membership_status' => 'inactive',
            ],
        ]);
    }

    /** El dueño reactiva a un padre/madre previamente desactivado. */
    public function reactivateMember(Request $request, string $family, string $member): JsonResponse
    {
        $this->assertFamilyAccess($request, $family);

        $result = $this->reactivateMemberAction->execute($request->user(), $family, $member);

        if (($result['ok'] ?? false) !== true) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        return response()->json([
            'message' => 'Acceso reactivado. La persona ya puede volver a iniciar sesión.',
            'data' => [
                'member_id' => $result['member_id'],
                'membership_status' => 'active',
            ],
        ]);
    }

    private function assertFamilyAccess(Request $request, string $familyId): void
    {
        if ($request->user()->family_id !== $familyId) {
            abort(403, 'Forbidden');
        }
    }
}
