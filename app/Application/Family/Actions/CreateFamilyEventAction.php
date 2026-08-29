<?php

declare(strict_types=1);

namespace App\Application\Family\Actions;

use App\Models\FamilyEvent;
use App\Models\User;
use App\Services\FamilyNotificationService;
use Illuminate\Support\Str;

final class CreateFamilyEventAction
{
    public function __construct(
        private readonly FamilyNotificationService $notifications,
    ) {}

    /** @param array<string, mixed> $data */
    public function execute(User $actor, array $data): FamilyEvent
    {
        abort_if($actor->family_id === null, 403, 'Sin familia');

        $event = FamilyEvent::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $actor->family_id,
            'created_by' => $actor->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'] ?? null,
            'location' => $data['location'] ?? null,
            'status' => 'scheduled',
        ]);

        $this->notifications->notifyFamily(
            $actor,
            'family_event',
            'Nuevo evento familiar',
            "{$actor->name}: {$event->title}",
            [
                'entity_type' => 'family_event',
                'entity_id' => $event->id,
                'family_event_id' => $event->id,
            ],
        );

        return $event->load(['creator:id,name', 'guests']);
    }
}
