<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AppNotification;
use App\Models\User;
use App\Services\Fcm\FcmPushService;
use Illuminate\Support\Str;

class FamilyNotificationService
{
    public function __construct(private readonly FcmPushService $fcm) {}
    /**
     * Notifica a los padres/madres de la familia excepto quien ejecutó la acción.
     *
     * @param  array<string, mixed>  $data
     */
    public function notifyPartnerParents(
        User $actor,
        string $type,
        string $title,
        string $body,
        array $data = [],
    ): void {
        if ($actor->family_id === null) {
            return;
        }

        $recipientIds = User::query()
            ->where('family_id', $actor->family_id)
            ->where('id', '!=', $actor->id)
            ->whereIn('role', ['padre', 'madre'])
            ->pluck('id')
            ->all();

        $this->notifyUsers($actor, $recipientIds, $type, $title, $body, $data);
    }

    /**
     * @param  array<int|string>  $recipientIds
     * @param  array<string, mixed>  $data
     */
    public function notifyUsers(
        User $actor,
        array $recipientIds,
        string $type,
        string $title,
        string $body,
        array $data = [],
    ): void {
        if ($actor->family_id === null || $recipientIds === []) {
            return;
        }

        $payload = array_merge($data, [
            'actor_id'   => $actor->id,
            'actor_name' => $actor->name,
        ]);

        foreach (array_unique(array_map('intval', $recipientIds)) as $userId) {
            if ($userId === (int) $actor->id) {
                continue;
            }

            $notification = AppNotification::query()->create([
                'id'        => (string) Str::uuid(),
                'family_id' => $actor->family_id,
                'user_id'   => $userId,
                'type'      => $type,
                'title'     => $title,
                'body'      => $body,
                'data'      => $payload,
            ]);

            $recipient = User::query()->find($userId);
            if ($recipient !== null) {
                $this->fcm->sendToUser(
                    $recipient,
                    $title,
                    $body,
                    $type,
                    array_merge($payload, [
                        'notification_id' => $notification->id,
                    ]),
                );
            }
        }
    }
}
