<?php

declare(strict_types=1);

namespace App\Application\Profile\Actions;

use App\Application\Profile\HaltFamilyBillingForUser;
use App\Infrastructure\Auth\TokenService;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\FamilyNotificationService;
use App\Support\NotificationPreferences;
use Illuminate\Support\Facades\DB;

final class DeleteAccountAction
{
    public function __construct(
        private readonly FamilyNotificationService $notifications,
        private readonly TokenService $tokens,
        private readonly HaltFamilyBillingForUser $haltBilling,
    ) {}

    public function execute(User $user): void
    {
        if ($user->family_id !== null) {
            $this->notifications->notifyFamily(
                $user,
                'profile_updated',
                'Cuenta eliminada',
                "{$user->name} eliminó su cuenta permanentemente",
                ['entity_type' => 'user', 'entity_id' => (string) $user->id],
            );
        }

        DB::transaction(function () use ($user) {
            $this->haltBilling->execute($user, 'Cuenta eliminada permanentemente');

            $this->tokens->revoke($user);

            FamilyMember::query()
                ->where('user_id', $user->id)
                ->update(['status' => 'inactive']);

            $user->update([
                'account_status' => 'deleted',
                'deactivated_at' => now(),
                'name' => 'Usuario eliminado',
                'email' => 'deleted_'.$user->id.'@deleted.zumifly.local',
                'notification_preferences' => NotificationPreferences::defaults(),
            ]);

            $user->delete();
        });
    }
}
