<?php

declare(strict_types=1);

namespace App\Application\Family;

use App\Events\JoinRequestChanged;
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
        array $person = [],
    ): FamilyJoinRequest {
        if ($inviter->family_id !== $family->id) {
            throw ValidationException::withMessages(['invited_by' => 'El invitador no pertenece a esta familia.']);
        }

        if (! $inviter->familyRole()->canInviteMembers()) {
            throw ValidationException::withMessages(['invited_by' => 'Solo un padre o madre puede invitar.']);
        }

        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'Este email ya está registrado.']);
        }

        $pending = FamilyJoinRequest::query()
            ->where('family_id', $family->id)
            ->where('email', $email)
            ->where('status', 'pending')
            ->exists();

        if ($pending) {
            throw ValidationException::withMessages(['email' => 'Ya hay una solicitud pendiente con este email.']);
        }

        $payload = [
            'id' => (string) Str::uuid(),
            'family_id' => $family->id,
            'invited_by_user_id' => $inviter->id,
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => $role,
            'status' => 'pending',
        ];

        foreach (['document_type', 'document_number', 'phone', 'birthdate', 'address'] as $key) {
            if (array_key_exists($key, $person) && $person[$key] !== null) {
                $payload[$key] = $person[$key];
            }
        }

        $joinRequest = FamilyJoinRequest::query()->create($payload);

        event(new JoinRequestChanged(
            familyId: (string) $family->id,
            requestId: (string) $joinRequest->id,
            action: 'created',
            status: 'pending',
            applicantName: $name,
            applicantEmail: $email,
            actorId: (int) $inviter->id,
        ));

        return $joinRequest;
    }

    public function approve(FamilyJoinRequest $request, User $approver): User
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages(['status' => 'La solicitud ya fue procesada.']);
        }

        if ($approver->family_id !== $request->family_id) {
            abort(403, 'No perteneces a esta familia.');
        }

        if (! $approver->familyRole()->canInviteMembers()) {
            abort(403, 'Solo un padre o madre puede aprobar solicitudes.');
        }

        if (User::query()->where('email', $request->email)->exists()) {
            throw ValidationException::withMessages(['email' => 'Este email ya está registrado.']);
        }

        $userPayload = [
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'role' => $request->role,
            'family_id' => $request->family_id,
        ];
        foreach (['document_type', 'document_number', 'phone', 'birthdate', 'address'] as $key) {
            if ($request->{$key} !== null) {
                $userPayload[$key] = $request->{$key};
            }
        }

        $user = User::query()->create($userPayload);

        // email_verified_at no es fillable: forzar verificación (entrada por aprobación familiar).
        $user->forceFill(['email_verified_at' => now()])->save();

        FamilyMember::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $request->family_id,
            'user_id' => $user->id,
            'role' => $request->role,
            'status' => 'active',
        ]);

        $request->update(['status' => 'approved']);

        event(new JoinRequestChanged(
            familyId: (string) $request->family_id,
            requestId: (string) $request->id,
            action: 'approved',
            status: 'approved',
            applicantName: $request->name,
            applicantEmail: $request->email,
            actorId: (int) $approver->id,
        ));

        return $user->fresh();
    }

    public function reject(FamilyJoinRequest $request, User $approver): void
    {
        if (! $request->isPending()) {
            throw ValidationException::withMessages(['status' => 'La solicitud ya fue procesada.']);
        }

        if ($approver->family_id !== $request->family_id) {
            abort(403, 'No perteneces a esta familia.');
        }

        if (! $approver->familyRole()->canInviteMembers()) {
            abort(403, 'Solo un padre o madre puede rechazar solicitudes.');
        }

        $request->update(['status' => 'rejected']);

        event(new JoinRequestChanged(
            familyId: (string) $request->family_id,
            requestId: (string) $request->id,
            action: 'rejected',
            status: 'rejected',
            applicantName: $request->name,
            applicantEmail: $request->email,
            actorId: (int) $approver->id,
        ));
    }
}
