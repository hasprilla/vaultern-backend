<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\ParentMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class ParentMessageSent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public ParentMessage $message) {}

    /**
     * @return array<int, \Illuminate\Broadcasting\PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('family.'.$this->message->family_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ParentMessageSent';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'v'         => 1,
            'type'      => 'parent_message',
            'entity_id' => $this->message->id,
            'action'    => 'sent',
            'message'   => [
                'id'         => $this->message->id,
                'sender_id'  => $this->message->sender_id,
                'preview'    => Str::limit((string) $this->message->message, 120),
                'priority'   => $this->message->priority,
                'created_at' => $this->message->created_at?->toIso8601String(),
            ],
        ];
    }
}
