<?php

declare(strict_types=1);

namespace App\Application\Family\Actions;

use App\Application\Family\FamilyJoinRequestService;
use App\Models\FamilyJoinRequest;
use App\Models\User;
use App\Services\FamilyNotificationService;

final class ApproveJoinRequestAction
{
    public function __construct(
        private readonly FamilyJoinRequestService $joinRequests,
        private readonly FamilyNotificationService $notifications,
    ) {}

    public function execute(User $approver, FamilyJoinRequest $joinRequest): User
    {
        $user = $this->joinRequests->approve($joinRequest, $approver);
        $approverRole = $approver->role === 'madre' ? 'madre' : 'padre';
        $familyId = (string) $joinRequest->family_id;

        $otherIds = User::query()
            ->where('family_id', $familyId)
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

        $this->notifications->notifyUsers(
            $approver,
            [(int) $user->id],
            'family_join_approved',
            'Solicitud aceptada',
            "Tu {$approverRole} {$approver->name} aceptó tu solicitud. Ya puedes iniciar sesión en Zumifly.",
            [
                'entity_type' => 'user',
                'entity_id' => (string) $user->id,
                'approver_id' => (int) $approver->id,
                'approver_name' => $approver->name,
                'approver_role' => $approverRole,
            ],
        );

        return $user;
    }
}
