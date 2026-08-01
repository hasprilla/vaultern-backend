<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Señal ligera: el cliente revalida analytics sin recibir números en el payload. */
class FamilyStatsInvalidated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public string $familyId,
        public string $reason = 'changed',
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
        return 'FamilyStatsInvalidated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'v'         => 1,
            'type'      => 'family_stats',
            'entity_id' => $this->familyId,
            'action'    => 'invalidated',
            'reason'    => $this->reason,
        ];
    }
}
