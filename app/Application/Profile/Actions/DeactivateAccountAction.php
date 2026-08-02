<?php

declare(strict_types=1);

namespace App\Application\Profile\Actions;

use App\Application\Profile\HaltFamilyBillingForUser;
use App\Infrastructure\Auth\TokenService;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\FamilyNotificationService;
use Illuminate\Support\Facades\DB;

final class DeactivateAccountAction
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
                'Cuenta desactivada',
                "{$user->name} desactivó su cuenta temporalmente",
                ['entity_type' => 'user', 'entity_id' => (string) $user->id],
            );
        }

        DB::transaction(function () use ($user) {
            $this->haltBilling->execute($user, 'Cuenta desactivada temporalmente');

            $user->update([
                'account_status' => 'deactivated',
                'deactivated_at' => now(),
            ]);

            FamilyMember::query()
                ->where('user_id', $user->id)
                ->update(['status' => 'inactive']);

            $this->tokens->revoke($user);
        });
    }
}
