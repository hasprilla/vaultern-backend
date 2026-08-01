<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JoinRequestChanged implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public string $familyId,
        public string $requestId,
        public string $action,
        public string $status,
        public ?string $applicantName = null,
        public ?string $applicantEmail = null,
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
        return 'JoinRequestChanged';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'v'         => 1,
            'type'      => 'join_request',
            'entity_id' => $this->requestId,
            'action'    => $this->action,
            'status'    => $this->status,
            'applicant' => [
                'name'  => $this->applicantName,
                'email' => $this->applicantEmail,
            ],
            'actor_id'  => $this->actorId,
        ];
    }
}
