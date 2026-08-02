<?php

declare(strict_types=1);

namespace App\Application\Family\Actions;

use App\Models\Family;
use App\Models\User;
use App\Services\FamilyNotificationService;

/**
 * @phpstan-type UpdateSuccess array{ok: true, family: Family}
 * @phpstan-type UpdateFailure array{ok: false, status: int, message: string}
 */
final class UpdateFamilyAction
{
    public function __construct(
        private readonly FamilyNotificationService $notifications,
    ) {}

    /**
     * @param  array{name?: string, plan?: string}  $validated
     * @return UpdateSuccess|UpdateFailure
     */
    public function execute(User $actor, Family $family, array $validated): array
    {
        if (! $actor->canManageFinances()) {
            return ['ok' => false, 'status' => 403, 'message' => 'Forbidden'];
        }

        $family->update($validated);

        if ($validated !== []) {
            $this->notifications->notifyFamily(
                $actor,
                'family_updated',
                'Familia actualizada',
                "{$actor->name} actualizó los datos de la familia",
                ['entity_type' => 'family', 'entity_id' => (string) $family->id],
            );
        }

        return ['ok' => true, 'family' => $family->fresh() ?? $family];
    }
}
