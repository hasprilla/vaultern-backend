<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\NotificationCreated;
use App\Models\AppNotification;
use App\Models\User;
use App\Services\Fcm\FcmPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class NotifyFamilyJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<int>  $recipientIds
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $familyId,
        public ?int $actorId,
        public array $recipientIds,
        public string $type,
        public string $title,
        public string $body,
        public array $data = [],
    ) {}

    public function handle(FcmPushService $fcm): void
    {
        $actor = $this->actorId !== null
            ? User::query()->find($this->actorId)
            : null;

        $payload = $this->data;
        if ($actor !== null) {
            $payload = array_merge($payload, [
                'actor_id'   => $actor->id,
                'actor_name' => $actor->name,
            ]);
        }

        foreach (array_unique(array_map('intval', $this->recipientIds)) as $userId) {
            if ($actor !== null && $userId === (int) $actor->id) {
                continue;
            }

            $notification = AppNotification::query()->create([
                'id'        => (string) Str::uuid(),
                'family_id' => $this->familyId,
                'user_id'   => $userId,
                'type'      => $this->type,
                'title'     => $this->title,
                'body'      => $this->body,
                'data'      => $payload,
            ]);

            event(new NotificationCreated($notification));

            $recipient = User::query()->find($userId);
            if ($recipient !== null) {
                $fcm->sendToUser(
                    $recipient,
                    $this->title,
                    $this->body,
                    $this->type,
                    array_merge($payload, [
                        'notification_id' => $notification->id,
                    ]),
                );
            }
        }
    }
}
