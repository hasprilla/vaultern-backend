<?php

declare(strict_types=1);

namespace App\Application\Profile\Actions;

use App\Models\User;
use App\Services\FamilyNotificationService;

final class UpdateProfileAction
{
    public function __construct(
        private readonly FamilyNotificationService $notifications,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(User $user, array $validated): User
    {
        $user->update($validated);

        if ($user->family_id !== null) {
            $this->notifications->notifyFamily(
                $user,
                'profile_updated',
                'Perfil actualizado',
                "{$user->name} actualizó su perfil",
                ['entity_type' => 'user', 'entity_id' => (string) $user->id],
            );
        }

        return $user->fresh() ?? $user;
    }
}
