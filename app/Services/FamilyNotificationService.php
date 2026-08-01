<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\NotifyFamilyJob;
use App\Models\User;

class FamilyNotificationService
{
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

        $this->enqueue($actor->family_id, (int) $actor->id, $parentIds, $type, $title, $body, $data);
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

        $this->enqueue($actor->family_id, (int) $actor->id, $recipientIds, $type, $title, $body, $data);
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

        $this->enqueue($familyId, $excludeUserId, $onlyUserIds, $type, $title, $body, $data);
    }

    /**
     * @param  array<int|string>  $recipientIds
     * @param  array<string, mixed>  $data
     */
    private function enqueue(
        string $familyId,
        ?int $actorId,
        array $recipientIds,
        string $type,
        string $title,
        string $body,
        array $data,
    ): void {
        $ids = array_values(array_unique(array_map('intval', $recipientIds)));
        if ($ids === []) {
            return;
        }

        NotifyFamilyJob::dispatch(
            $familyId,
            $actorId,
            $ids,
            $type,
            $title,
            $body,
            $data,
        );
    }
}
