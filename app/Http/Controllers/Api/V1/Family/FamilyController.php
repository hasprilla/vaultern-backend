<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Family;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Family\CreateFamilyRequest;
use App\Http\Requests\Api\V1\Family\InviteMemberRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FamilyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $family = Family::query()->findOrFail($request->user()->family_id);
        $members = FamilyMember::query()
            ->with('user')
            ->where('family_id', $family->id)
            ->where('status', 'active')
            ->get();

        return response()->json([
            'data' => [
                'id'          => $family->id,
                'name'        => $family->name,
                'plan'        => $family->plan,
                'invite_code' => $family->invite_code,
                'members'     => $members->map(fn (FamilyMember $m) => new UserResource($m->user)),
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
            'id'   => (string) Str::uuid(),
            'name' => $request->validated('name'),
            'plan' => $request->validated('plan') ?? 'free',
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
                'id'   => $family->id,
                'name' => $family->name,
                'plan' => $family->plan,
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

        return response()->json([
            'message' => 'Invitation sent successfully',
            'data'    => [
                'email'  => $request->validated('email'),
                'role'   => $request->validated('role'),
                'status' => 'pending',
            ],
        ], 202);
    }

    public function assignRole(Request $request, string $family, string $member): JsonResponse
    {
        $this->assertFamilyAccess($request, $family);

        if (! $request->user()->canManageFinances()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'role' => ['required', 'in:padre,madre,tutor,hijo'],
        ]);

        $membership = FamilyMember::query()
            ->where('family_id', $family)
            ->where('user_id', $member)
            ->firstOrFail();

        $membership->update(['role' => $validated['role']]);
        User::query()->where('id', $member)->update(['role' => $validated['role']]);

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
