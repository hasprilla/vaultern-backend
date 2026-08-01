<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Family;

use App\Application\Family\FamilyJoinRequestService;
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
use App\Services\FamilyNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class FamilyController extends Controller
{
    public function __construct(
        private readonly FamilyJoinRequestService $joinRequests,
        private readonly FamilyNotificationService $notifications,
        private readonly ChildGuardianService $guardians,
        private readonly \App\Services\PlanFeatureService $planFeatures,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $viewer = $request->user();
        $family = Family::query()->findOrFail($viewer->family_id);
        $members = FamilyMember::query()
            ->with(['user.guardians'])
            ->where('family_id', $family->id)
            ->where('status', 'active')
            ->get();

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

        $isOwner = $family->isOwnedBy($viewer);

        return response()->json([
            'data' => [
                'id'             => $family->id,
                'name'           => $family->name,
                'plan'           => $family->plan,
                'invite_code'    => $family->invite_code,
                'owner_user_id'  => $family->owner_user_id !== null ? (string) $family->owner_user_id : null,
                'is_owner'       => $isOwner,
                'members'        => $visible->values()->map(fn (FamilyMember $m) => new UserResource($m->user)),
                'my_child_ids'   => array_map('strval', $myChildIds),
            ],
        ]);
    }

    public function store(CreateFamilyRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->family_id !== null) {
            return response()->json(['message' => 'User already belongs to a family'], 422);
        }

        $family = Family::query()->create([
            'id'            => (string) Str::uuid(),
            'name'          => $request->validated('name'),
            'plan'          => $request->validated('plan') ?? 'free',
            'owner_user_id' => $user->id,
        ]);

        FamilyMember::query()->create([
            'id'        => (string) Str::uuid(),
            'family_id' => $family->id,
            'user_id'   => $user->id,
            'role'      => $user->role,
            'status'    => 'active',
        ]);

        $user->update(['family_id' => $family->id]);

        return response()->json([
            'data' => [
                'id'            => $family->id,
                'name'          => $family->name,
                'plan'          => $family->plan,
                'owner_user_id' => (string) $user->id,
                'is_owner'      => true,
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

        if (! $request->user()->canManageFinances()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:120'],
            'plan' => ['sometimes', 'string', 'in:free,premium'],
        ]);

        $model = Family::query()->findOrFail($family);
        $model->update($validated);

        if ($validated !== []) {
            $this->notifications->notifyFamily(
                $request->user(),
                'family_updated',
                'Familia actualizada',
                "{$request->user()->name} actualizó los datos de la familia",
                ['entity_type' => 'family', 'entity_id' => $family],
            );
        }

        return response()->json(['data' => $model->only(['id', 'name', 'plan'])]);
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

        if (! $request->user()->familyRole()->canInviteMembers()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $this->notifications->notifyFamily(
            $request->user(),
            'family_invite',
            'Invitación enviada',
            "{$request->user()->name} invitó a {$request->validated('email')} como {$request->validated('role')}",
            ['email' => $request->validated('email')],
        );

        return response()->json([
            'message' => 'Invitation sent successfully',
            'data'    => [
                'email'  => $request->validated('email'),
                'role'   => $request->validated('role'),
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
        $childrenCount = User::query()->where('family_id', $family)->where('role', 'hijo')->count();
        $maxChildren = $this->planFeatures->familyFeatureLimit($familyModel, 'max_children', 2);

        if ($childrenCount >= $maxChildren) {
            return response()->json([
                'message' => "Tu plan permite hasta {$maxChildren} hijos. Mejora tu plan para agregar más.",
                'code'    => 'children_limit_reached',
            ], 422);
        }

        $child = User::query()->create([
            'name'      => $request->validated('name'),
            'email'     => 'hijo.'.(string) Str::uuid().'@zumifly.internal',
            'password'  => Hash::make(Str::random(32)),
            'role'      => 'hijo',
            'family_id' => $family,
        ]);

        FamilyMember::query()->create([
            'id'        => (string) Str::uuid(),
            'family_id' => $family,
            'user_id'   => $child->id,
            'role'      => 'hijo',
            'status'    => 'active',
        ]);

        // Solo el dueño de la membresía puede asignar custodios adicionales.
        $guardianIds = $request->user()->isFamilyOwner()
            ? array_map('intval', $request->validated('guardian_ids') ?? [])
            : [];
        $this->guardians->syncForChild($child, $guardianIds, $request->user());
        $child->load('guardians');

        $this->notifications->notifyChildGuardians(
            $request->user(),
            (int) $child->id,
            'family_child',
            'Nuevo hijo/a registrado',
            "{$request->user()->name} registró a {$child->name}",
            ['entity_type' => 'user', 'entity_id' => (string) $child->id],
        );

        return response()->json(['data' => new UserResource($child)], 201);
    }

    public function syncChildGuardians(Request $request, string $family, string $child): JsonResponse
    {
        $this->assertFamilyAccess($request, $family);

        if (! $request->user()->isFamilyOwner()) {
            return response()->json([
                'message' => 'Solo el dueño de la membresía puede definir quién ve la información de cada hijo.',
            ], 403);
        }

        $childUser = User::query()
            ->where('family_id', $family)
            ->where('role', 'hijo')
            ->findOrFail($child);

        $validated = $request->validate([
            'guardian_ids'   => ['required', 'array', 'min:1'],
            'guardian_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $ids = array_map('intval', $validated['guardian_ids']);
        $this->guardians->syncForChild($childUser, $ids);
        $childUser->load('guardians');

        return response()->json(['data' => new UserResource($childUser)]);
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

        $user = $this->joinRequests->approve($model, $request->user());
        $approver = $request->user();
        $approverRole = $approver->role === 'madre' ? 'madre' : 'padre';

        // Aviso al resto de la familia (sin el nuevo miembro).
        $otherIds = User::query()
            ->where('family_id', $family)
            ->where('id', '!=', $approver->id)
            ->where('id', '!=', $user->id)
            ->pluck('id')
            ->all();

        if ($otherIds !== []) {
            $this->notifications->notifyUsers(
                $approver,
                $otherIds,
                'family_join',
                'Nuevo miembro en la familia',
                "{$approver->name} aprobó la entrada de {$user->name}",
                ['entity_type' => 'user', 'entity_id' => (string) $user->id],
            );
        }

        // Notificar a quien fue aceptado: rol + nombre del aprobador.
        $this->notifications->notifyUsers(
            $approver,
            [(int) $user->id],
            'family_join_approved',
            'Solicitud aceptada',
            "Tu {$approverRole} {$approver->name} aceptó tu solicitud. Ya puedes iniciar sesión en Zumifly.",
            [
                'entity_type'   => 'user',
                'entity_id'     => (string) $user->id,
                'approver_id'   => (int) $approver->id,
                'approver_name' => $approver->name,
                'approver_role' => $approverRole,
            ],
        );

        return response()->json([
            'message' => 'Solicitud aprobada. La persona ya puede iniciar sesión.',
            'data'    => new UserResource($user),
        ]);
    }

    public function rejectJoinRequest(Request $request, string $family, string $joinRequest): JsonResponse
    {
        $this->assertFamilyAccess($request, $family);

        $model = FamilyJoinRequest::query()
            ->where('family_id', $family)
            ->findOrFail($joinRequest);

        $this->joinRequests->reject($model, $request->user());

        $this->notifications->notifyFamily(
            $request->user(),
            'family_join',
            'Solicitud rechazada',
            "{$request->user()->name} rechazó la solicitud de {$model->name}",
            ['entity_type' => 'join_request', 'entity_id' => $model->id],
        );

        return response()->json(['message' => 'Solicitud rechazada']);
    }

    public function assignRole(Request $request, string $family, string $member): JsonResponse
    {
        $this->assertFamilyAccess($request, $family);

        if (! $request->user()->isFamilyOwner()) {
            return response()->json([
                'message' => 'Solo el dueño de la membresía puede cambiar roles.',
            ], 403);
        }

        $validated = $request->validate([
            'role' => ['required', 'in:padre,madre,tutor,hijo'],
        ]);

        $familyModel = Family::query()->findOrFail($family);
        if ((int) $familyModel->owner_user_id === (int) $member && $validated['role'] === 'hijo') {
            return response()->json([
                'message' => 'No puedes cambiar el rol del dueño de la membresía a hijo.',
            ], 422);
        }

        $membership = FamilyMember::query()
            ->where('family_id', $family)
            ->where('user_id', $member)
            ->firstOrFail();

        $membership->update(['role' => $validated['role']]);
        User::query()->where('id', $member)->update(['role' => $validated['role']]);

        $memberUser = User::query()->findOrFail($member);
        $this->notifications->notifyFamily(
            $request->user(),
            'family_updated',
            'Rol actualizado',
            "{$request->user()->name} cambió el rol de {$memberUser->name} a {$validated['role']}",
            ['entity_type' => 'user', 'entity_id' => $member],
        );

        return response()->json([
            'message' => 'Role updated successfully',
            'data'    => [
                'member_id' => $member,
                'new_role'  => $validated['role'],
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
