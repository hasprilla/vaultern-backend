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
        $ids = array_values(array_unique(array_map('intval', $this->recipientIds)));
        if ($this->actorId !== null) {
            $ids = array_values(array_filter($ids, fn (int $id) => $id !== (int) $this->actorId));
        }

        if ($ids === []) {
            return;
        }

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

        $recipients = User::query()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy(fn (User $u) => (int) $u->id);

        $now = now();
        $rows = [];
        foreach ($ids as $userId) {
            if (! $recipients->has($userId)) {
                continue;
            }
            $rows[] = [
                'id'         => (string) Str::uuid(),
                'family_id'  => $this->familyId,
                'user_id'    => $userId,
                'type'       => $this->type,
                'title'      => $this->title,
                'body'       => $this->body,
                'data'       => json_encode($payload, JSON_THROW_ON_ERROR),
                'read'       => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows === []) {
            return;
        }

        // Bulk insert (compatible con cola database + cron cPanel).
        AppNotification::query()->insert($rows);

        foreach ($rows as $row) {
            $notification = new AppNotification([
                'id'        => $row['id'],
                'family_id' => $row['family_id'],
                'user_id'   => $row['user_id'],
                'type'      => $row['type'],
                'title'     => $row['title'],
                'body'      => $row['body'],
                'data'      => $payload,
                'read'      => false,
            ]);
            $notification->exists = true;

            event(new NotificationCreated($notification));

            $recipient = $recipients->get((int) $row['user_id']);
            if ($recipient === null) {
                continue;
            }

            $fcm->sendToUser(
                $recipient,
                $this->title,
                $this->body,
                $this->type,
                array_merge($payload, [
                    'notification_id' => $row['id'],
                ]),
            );
        }
    }
}
