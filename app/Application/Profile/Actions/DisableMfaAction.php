<?php

declare(strict_types=1);

namespace App\Application\Profile\Actions;

use App\Models\User;

final class DisableMfaAction
{
    public function execute(User $user): void
    {
        $user->update([
            'mfa_enabled' => false,
            'mfa_secret' => null,
        ]);
    }
}
