<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\NotifyFamilyJob;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;

class FamilyNotificationService
{
    public function __construct(private readonly ChildGuardianService $guardians) {}

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
            ->where('users.family_id', $actor->family_id)
            ->where('users.id', '!=', $actor->id)
            ->whereIn('users.role', ['padre', 'madre'])
            ->whereExists(function ($query) use ($actor) {
                $query->selectRaw('1')
                    ->from('family_members')
                    ->whereColumn('family_members.user_id', 'users.id')
                    ->where('family_members.family_id', $actor->family_id)
                    ->where('family_members.status', 'active');
            })
            ->pluck('users.id')
            ->all();

        $this->enqueue($actor->family_id, (int) $actor->id, $parentIds, $type, $title, $body, $data);
    }

    /**
     * Notifica solo a los custodios (padre/madre/tutor) de un hijo concreto.
     *
     * @param  array<string, mixed>  $data
     */
    public function notifyChildGuardians(
        User $actor,
        int $childUserId,
        string $type,
        string $title,
        string $body,
        array $data = [],
    ): void {
        if ($actor->family_id === null) {
            return;
        }

        // Solo custodios explícitos del hijo (no fan-out al dueño si no es custodio).
        $recipientIds = array_values(array_unique($this->guardians->guardianIdsOfChild($childUserId)));

        $this->enqueue(
            $actor->family_id,
            (int) $actor->id,
            $recipientIds,
            $type,
            $title,
            $body,
            array_merge($data, [
                'entity_type' => $data['entity_type'] ?? 'user',
                'entity_id'   => $data['entity_id'] ?? (string) $childUserId,
                'child_id'    => $childUserId,
            ]),
        );
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
            $query = User::query()
                ->where('users.family_id', $familyId)
                ->whereExists(function ($sub) use ($familyId) {
                    $sub->selectRaw('1')
                        ->from('family_members')
                        ->whereColumn('family_members.user_id', 'users.id')
                        ->where('family_members.family_id', $familyId)
                        ->where('family_members.status', 'active');
                });
            if ($excludeUserId !== null) {
                $query->where('users.id', '!=', $excludeUserId);
            }
            $onlyUserIds = $query->pluck('users.id')->all();
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

        // Nunca notificar a padres/madres con membresía desactivada (datos se conservan).
        $ids = FamilyMember::query()
            ->where('family_id', $familyId)
            ->where('status', 'active')
            ->whereIn('user_id', $ids)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

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
