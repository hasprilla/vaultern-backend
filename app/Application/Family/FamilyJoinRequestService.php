<?php

declare(strict_types=1);

namespace App\Application\Family;

use App\Models\Family;
use App\Models\FamilyJoinRequest;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FamilyJoinRequestService
{
    public function submit(
        Family $family,
        User $inviter,
        string $name,
        string $email,
        string $password,
        string $role,
    ): FamilyJoinRequest {
        if ($inviter->family_id !== $family->id) {
            throw ValidationException::withMessages(['invited_by' => 'El invitador no pertenece a esta familia.']);
        }

        if (! $inviter->familyRole()->canInviteMembers()) {
            throw ValidationException::withMessages(['invited_by' => 'Solo un padre o madre puede invitar.']);
        }

        $pending = FamilyJoinRequest::query()
            ->where('family_id', $family->id)
            ->where('email', $email)
            ->where('status', 'pending')
            ->exists();

        if ($pending) {
            throw ValidationException::withMessages(['email' => 'Ya hay una solicitud pendiente con este email.']);
        }

        return FamilyJoinRequest::query()->create([
            'id'                  => (string) Str::uuid(),
            'family_id'           => $family->id,
            'invited_by_user_id'  => $inviter->id,
            'name'                => $name,
            'email'               => $email,
            'password'            => $password,
            'role'                => $role,
            'status'              => 'pending',
        ]);
    }

    public function approve(FamilyJoinRequest $request, User $approver): User
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages(['status' => 'La solicitud ya fue procesada.']);
        }

        if ($approver->id !== $request->invited_by_user_id) {
            abort(403, 'Solo quien compartió el código puede aprobar esta solicitud.');
        }

        $user = User::query()->create([
            'name'              => $request->name,
            'email'             => $request->email,
            'password'          => $request->password,
            'role'              => $request->role,
            'family_id'         => $request->family_id,
            'email_verified_at' => now(),
        ]);

        FamilyMember::query()->create([
            'id'        => (string) Str::uuid(),
            'family_id' => $request->family_id,
            'user_id'   => $user->id,
            'role'      => $request->role,
            'status'    => 'active',
        ]);

        $request->update(['status' => 'approved']);

        return $user;
    }

    public function reject(FamilyJoinRequest $request, User $approver): void
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages(['status' => 'La solicitud ya fue procesada.']);
        }

        if ($approver->id !== $request->invited_by_user_id) {
            abort(403, 'Solo quien compartió el código puede rechazar esta solicitud.');
        }

        $request->update(['status' => 'rejected']);
    }
}
