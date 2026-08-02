<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Application\Family\FamilyJoinRequestService;
use App\Models\Family;
use App\Models\FamilyJoinRequest;
use App\Models\User;
use App\Services\FamilyNotificationService;

/**
 * @phpstan-type JoinSuccess array{
 *   ok: true,
 *   joinRequest: FamilyJoinRequest,
 *   inviter: User,
 *   family: Family
 * }
 * @phpstan-type JoinFailure array{ok: false, status: int, message: string}
 */
final class JoinFamilyAction
{
    public function __construct(
        private readonly FamilyJoinRequestService $joinRequests,
        private readonly FamilyNotificationService $notifications,
    ) {}

    /**
     * @param  array{
     *   invite_code: string,
     *   name: string,
     *   email: string,
     *   password: string,
     *   role: string,
     *   invited_by?: int|null
     * }  $validated
     * @return JoinSuccess|JoinFailure
     */
    public function execute(array $validated): array
    {
        $family = Family::query()
            ->where('invite_code', strtoupper($validated['invite_code']))
            ->first();

        if ($family === null) {
            return ['ok' => false, 'status' => 422, 'message' => 'Código de invitación inválido'];
        }

        $inviterId = $validated['invited_by'] ?? null;
        $inviter = $inviterId !== null ? User::query()->find($inviterId) : null;

        if ($inviter === null || (string) $inviter->family_id !== (string) $family->id) {
            $inviter = User::query()
                ->where('family_id', $family->id)
                ->whereIn('role', ['padre', 'madre'])
                ->orderBy('id')
                ->first();
        }

        if ($inviter === null) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Esta familia no tiene un padre o madre que pueda aprobar invitaciones.',
            ];
        }

        $joinRequest = $this->joinRequests->submit(
            $family,
            $inviter,
            $validated['name'],
            $validated['email'],
            $validated['password'],
            $validated['role'],
        );

        $parentIds = User::query()
            ->where('family_id', $family->id)
            ->whereIn('role', ['padre', 'madre'])
            ->pluck('id')
            ->all();

        $this->notifications->notifyFamilyById(
            $family->id,
            null,
            'family_join_request',
            'Nueva solicitud de unión',
            "{$validated['name']} quiere unirse como {$validated['role']}",
            [
                'entity_type' => 'join_request',
                'entity_id' => $joinRequest->id,
                'actor_name' => $validated['name'],
            ],
            $parentIds,
        );

        return [
            'ok' => true,
            'joinRequest' => $joinRequest,
            'inviter' => $inviter,
            'family' => $family,
        ];
    }
}
