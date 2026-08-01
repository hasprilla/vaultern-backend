<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Family;

use App\Application\Family\FamilyJoinRequestService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Family\CreateFamilyRequest;
use App\Http\Requests\Api\V1\Family\InviteMemberRequest;
use App\Http\Requests\Api\V1\Family\RegisterChildRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Infrastructure\Auth\TokenService;
use App\Models\Family;
use App\Models\FamilyJoinRequest;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\ChildGuardianService;
use App\Services\FamilyNotificationService;
use App\Support\SchemaCompat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class FamilyController extends Controller
{
    public function __construct(
        private readonly FamilyJoinRequestService $joinRequests,
        private readonly FamilyNotificationService $notifications,
        private readonly ChildGuardianService $guardians,
        private readonly \App\Services\PlanFeatureService $planFeatures,
        private readonly TokenService $tokens,
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

        // Solo el dueño de la membresía puede asignar custodios adicionales (activos).
        $guardianIds = [];
        if ($request->user()->isFamilyOwner()) {
            $requested = array_map('intval', $request->validated('guardian_ids') ?? []);
            if ($requested !== []) {
                $guardianIds = FamilyMember::query()
                    ->where('family_id', $family)
                    ->where('status', 'active')
                    ->whereIn('role', ['padre', 'madre', 'tutor'])
                    ->whereIn('user_id', $requested)
                    ->pluck('user_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
            }
        }
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
        $activeGuardianIds = FamilyMember::query()
            ->where('family_id', $family)
            ->where('status', 'active')
            ->whereIn('role', ['padre', 'madre', 'tutor'])
            ->whereIn('user_id', $ids)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($activeGuardianIds) !== count(array_unique($ids))) {
            return response()->json([
                'message' => 'Solo puedes asignar custodios con acceso activo al núcleo familiar.',
            ], 422);
        }

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

        if ($membership->status !== 'active') {
            return response()->json([
                'message' => 'Reactiva el acceso del miembro antes de cambiar su rol.',
            ], 422);
        }

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

    /**
     * El dueño desactiva a un padre/madre sin borrar datos.
     * Queda sin poder realizar acciones del núcleo familiar hasta reactivación.
     */
    public function deactivateMember(Request $request, string $family, string $member): JsonResponse
    {
        $this->assertFamilyAccess($request, $family);

        if (! $request->user()->isFamilyOwner()) {
            return response()->json([
                'message' => 'Solo el dueño de la membresía puede desactivar a un padre o madre.',
            ], 403);
        }

        $familyModel = Family::query()->findOrFail($family);
        $membership = FamilyMember::query()
            ->where('family_id', $family)
            ->where('user_id', $member)
            ->firstOrFail();

        if (! in_array($membership->role, ['padre', 'madre'], true)) {
            return response()->json([
                'message' => 'Solo se puede desactivar a un padre o una madre.',
            ], 422);
        }

        if ((int) $familyModel->owner_user_id === (int) $member) {
            return response()->json([
                'message' => 'No puedes desactivar al dueño de la membresía.',
            ], 422);
        }

        if ((int) $request->user()->id === (int) $member) {
            return response()->json([
                'message' => 'No puedes desactivarte a ti mismo desde aquí.',
            ], 422);
        }

        if ($membership->status === 'inactive') {
            return response()->json([
                'message' => 'Este miembro ya está desactivado.',
            ], 422);
        }

        $memberUser = User::query()->findOrFail($member);

        DB::transaction(function () use ($membership, $memberUser) {
            $membership->update(['status' => 'inactive']);
            $this->tokens->revoke($memberUser);
        });

        $this->notifications->notifyFamily(
            $request->user(),
            'family_updated',
            'Acceso desactivado',
            "{$request->user()->name} desactivó el acceso de {$memberUser->name} al núcleo familiar",
            ['entity_type' => 'user', 'entity_id' => (string) $member],
        );

        return response()->json([
            'message' => 'Acceso desactivado. La información se conserva y puedes reactivarlo cuando quieras.',
            'data'    => [
                'member_id'          => (string) $member,
                'membership_status'  => 'inactive',
            ],
        ]);
    }

    /** El dueño reactiva a un padre/madre previamente desactivado. */
    public function reactivateMember(Request $request, string $family, string $member): JsonResponse
    {
        $this->assertFamilyAccess($request, $family);

        if (! $request->user()->isFamilyOwner()) {
            return response()->json([
                'message' => 'Solo el dueño de la membresía puede reactivar a un padre o madre.',
            ], 403);
        }

        $membership = FamilyMember::query()
            ->where('family_id', $family)
            ->where('user_id', $member)
            ->firstOrFail();

        if (! in_array($membership->role, ['padre', 'madre'], true)) {
            return response()->json([
                'message' => 'Solo se puede reactivar a un padre o una madre.',
            ], 422);
        }

        if ($membership->status === 'active') {
            return response()->json([
                'message' => 'Este miembro ya tiene acceso activo.',
            ], 422);
        }

        $memberUser = User::query()->findOrFail($member);
        $membership->update(['status' => 'active']);

        $this->notifications->notifyFamily(
            $request->user(),
            'family_updated',
            'Acceso reactivado',
            "{$request->user()->name} reactivó el acceso de {$memberUser->name} al núcleo familiar",
            ['entity_type' => 'user', 'entity_id' => (string) $member],
        );

        return response()->json([
            'message' => 'Acceso reactivado. La persona ya puede volver a iniciar sesión.',
            'data'    => [
                'member_id'         => (string) $member,
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
