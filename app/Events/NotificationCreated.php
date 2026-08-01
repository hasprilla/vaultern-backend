<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\AppNotification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public AppNotification $notification) {}

    /**
     * @return array<int, \Illuminate\Broadcasting\PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->notification->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'NotificationCreated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $data = $this->notification->data ?? [];

        return [
            'v'            => 1,
            'type'         => 'notification',
            'entity_id'    => $this->notification->id,
            'action'       => 'created',
            'notification' => [
                'id'          => $this->notification->id,
                'type'        => $this->notification->type,
                'title'       => $this->notification->title,
                'body'        => $this->notification->body,
                'entity_type' => $data['entity_type'] ?? null,
                'entity_id'   => $data['entity_id'] ?? null,
            ],
            'unread_delta' => 1,
        ];
    }
}
