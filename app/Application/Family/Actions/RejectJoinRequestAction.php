<?php

declare(strict_types=1);

namespace App\Application\Family\Actions;

use App\Application\Family\FamilyJoinRequestService;
use App\Models\FamilyJoinRequest;
use App\Models\User;
use App\Services\FamilyNotificationService;

final class RejectJoinRequestAction
{
    public function __construct(
        private readonly FamilyJoinRequestService $joinRequests,
        private readonly FamilyNotificationService $notifications,
    ) {}

    public function execute(User $actor, FamilyJoinRequest $joinRequest): void
    {
        $this->joinRequests->reject($joinRequest, $actor);

        $this->notifications->notifyFamily(
            $actor,
            'family_join',
            'Solicitud rechazada',
            "{$actor->name} rechazó la solicitud de {$joinRequest->name}",
            ['entity_type' => 'join_request', 'entity_id' => $joinRequest->id],
        );
    }
}
