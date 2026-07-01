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
     * Notifica a todos los miembros de la familia del actor, excepto al actor.
     *
     * @param  array<string, mixed>  $data
     */
    public function notifyFamily(
        User $actor,
        string $type,
        string $title,
        string $body,
        array $data = [],
    ): void {
        if ($actor->family_id === null) {
            return;
        }

        $this->notifyFamilyById($actor->family_id, (int) $actor->id, $type, $title, $body, $data);
    }

    /**
     * Notifica a padres/madres de la familia excepto quien ejecutó la acción.
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

        $parentIds = User::query()
            ->where('family_id', $actor->family_id)
            ->where('id', '!=', $actor->id)
            ->whereIn('role', ['padre', 'madre'])
            ->pluck('id')
            ->all();

        $this->deliver($actor->family_id, $actor, $parentIds, $type, $title, $body, $data);
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

        $this->deliver($actor->family_id, $actor, $recipientIds, $type, $title, $body, $data);
    }

    /**
     * Notifica miembros de una familia (p.ej. solicitud de unión).
     *
     * @param  array<string, mixed>  $data
     * @param  array<int|string>|null  $onlyUserIds
     */
    public function notifyFamilyById(
        string $familyId,
        ?int $excludeUserId,
        string $type,
        string $title,
        string $body,
        array $data = [],
        ?array $onlyUserIds = null,
    ): void {
        if ($onlyUserIds === null) {
            $query = User::query()->where('family_id', $familyId);
            if ($excludeUserId !== null) {
                $query->where('id', '!=', $excludeUserId);
            }
            $onlyUserIds = $query->pluck('id')->all();
        }

        if ($onlyUserIds === []) {
            return;
        }

        $actor = $excludeUserId !== null ? User::query()->find($excludeUserId) : null;
        $this->deliver($familyId, $actor, $onlyUserIds, $type, $title, $body, $data);
    }

    /**
     * @param  array<int|string>  $recipientIds
     * @param  array<string, mixed>  $data
     */
    private function deliver(
        string $familyId,
        ?User $actor,
        array $recipientIds,
        string $type,
        string $title,
        string $body,
        array $data,
    ): void {
        $payload = $data;
        if ($actor !== null) {
            $payload = array_merge($payload, [
                'actor_id'   => $actor->id,
                'actor_name' => $actor->name,
            ]);
        }

        foreach (array_unique(array_map('intval', $recipientIds)) as $userId) {
            if ($actor !== null && $userId === (int) $actor->id) {
                continue;
            }

            $notification = AppNotification::query()->create([
                'id'        => (string) Str::uuid(),
                'family_id' => $familyId,
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
