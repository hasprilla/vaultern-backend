<?php

declare(strict_types=1);

namespace App\Application\Family\Actions;

use App\Models\User;
use App\Services\FamilyNotificationService;

/**
 * @phpstan-type InviteSuccess array{ok: true, email: string, role: string}
 * @phpstan-type InviteFailure array{ok: false, status: int, message: string}
 */
final class InviteMemberAction
{
    public function __construct(
        private readonly FamilyNotificationService $notifications,
    ) {}

    /**
     * @param  array{email: string, role: string}  $validated
     * @return InviteSuccess|InviteFailure
     */
    public function execute(User $actor, array $validated): array
    {
        if (! $actor->familyRole()->canInviteMembers()) {
            return ['ok' => false, 'status' => 403, 'message' => 'Forbidden'];
        }

        $this->notifications->notifyFamily(
            $actor,
            'family_invite',
            'Invitación enviada',
            "{$actor->name} invitó a {$validated['email']} como {$validated['role']}",
            ['email' => $validated['email']],
        );

        return [
            'ok' => true,
            'email' => $validated['email'],
            'role' => $validated['role'],
        ];
    }
}
