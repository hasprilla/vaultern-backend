<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FamilyPermissionsChanged implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  list<int|string>  $childIds
     * @param  list<int|string>  $guardianIds
     */
    public function __construct(
        public string $familyId,
        public string $action,
        public ?string $childId = null,
        public ?string $parentId = null,
        public array $childIds = [],
        public array $guardianIds = [],
        public ?int $actorId = null,
    ) {}

    /**
     * @return array<int, \Illuminate\Broadcasting\PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('family.'.$this->familyId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'FamilyPermissionsChanged';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'v' => 1,
            'type' => 'family_permissions',
            'entity_id' => $this->childId ?? $this->parentId ?? $this->familyId,
            'action' => $this->action,
            'child_id' => $this->childId,
            'parent_id' => $this->parentId,
            'child_ids' => array_map('strval', $this->childIds),
            'guardian_ids' => array_map('strval', $this->guardianIds),
            'actor_id' => $this->actorId,
        ];
    }
}
