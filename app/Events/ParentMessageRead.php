<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ParentMessageRead implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public string $familyId,
        public string $messageId,
        public int $readerId,
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
        return 'ParentMessageRead';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'v'         => 1,
            'type'      => 'parent_message',
            'entity_id' => $this->messageId,
            'action'    => 'read',
            'reader_id' => $this->readerId,
        ];
    }
}
