<?php

declare(strict_types=1);

namespace App\Application\Profile\Actions;

use App\Models\User;
use App\Services\FamilyNotificationService;
use Illuminate\Support\Facades\Hash;

final class ChangePasswordAction
{
    public function __construct(
        private readonly FamilyNotificationService $notifications,
    ) {}

    public function execute(User $user, string $password): void
    {
        $user->update([
            'password' => Hash::make($password),
        ]);

        if ($user->family_id !== null) {
            $this->notifications->notifyFamily(
                $user,
                'profile_updated',
                'Contraseña cambiada',
                "{$user->name} cambió su contraseña",
                ['entity_type' => 'user', 'entity_id' => (string) $user->id],
            );
        }
    }
}
