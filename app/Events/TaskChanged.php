<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskChanged implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public string $familyId,
        public string $taskId,
        public string $action,
        public ?string $status = null,
        public ?string $title = null,
        public ?int $assigneeId = null,
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
        return 'TaskChanged';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'v'           => 1,
            'type'        => 'task',
            'entity_id'   => $this->taskId,
            'action'      => $this->action,
            'status'      => $this->status,
            'title'       => $this->title,
            'assignee_id' => $this->assigneeId,
            'actor_id'    => $this->actorId,
        ];
    }
}
