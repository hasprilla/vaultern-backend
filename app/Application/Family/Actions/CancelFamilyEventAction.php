<?php

declare(strict_types=1);

namespace App\Application\Family\Actions;

use App\Models\FamilyEvent;
use App\Models\User;
use App\Services\FamilyNotificationService;

final class CancelFamilyEventAction
{
    public function __construct(
        private readonly FamilyNotificationService $notifications,
    ) {}

    public function execute(User $actor, FamilyEvent $event): FamilyEvent
    {
        abort_if((string) $actor->family_id !== (string) $event->family_id, 403);

        $event->status = 'cancelled';
        $event->save();

        $this->notifications->notifyFamily(
            $actor,
            'family_event',
            'Evento cancelado',
            "{$event->title} fue cancelado",
            [
                'entity_type' => 'family_event',
                'entity_id' => $event->id,
                'family_event_id' => $event->id,
            ],
        );

        return $event->load(['creator:id,name', 'guests']);
    }
}
