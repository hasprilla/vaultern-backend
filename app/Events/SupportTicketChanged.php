<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * En cPanel (BROADCAST_CONNECTION=log) no llega por WS;
 * FCM + invalidate en Flutter cubren el sync.
 */
class SupportTicketChanged implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public int $recipientUserId,
        public string $ticketId,
        public string $action,
        public ?string $status = null,
        public ?string $subject = null,
        public ?int $actorId = null,
    ) {}

    /**
     * @return array<int, \Illuminate\Broadcasting\PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->recipientUserId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'SupportTicketChanged';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'v'         => 1,
            'type'      => 'support_ticket',
            'entity_id' => $this->ticketId,
            'action'    => $this->action,
            'status'    => $this->status,
            'subject'   => $this->subject,
            'actor_id'  => $this->actorId,
        ];
    }
}
