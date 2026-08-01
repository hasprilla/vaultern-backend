<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{id}', function (User $user, int|string $id): bool {
    return (int) $user->id === (int) $id && $user->isActive();
});

Broadcast::channel('family.{familyId}', function (User $user, string $familyId): bool {
    if (! $user->isActive()) {
        return false;
    }

    if ($user->family_id === null || (string) $user->family_id !== $familyId) {
        return false;
    }

    // Exige membresía activa cuando la tabla existe (compat cPanel pre-migrate).
    return $user->hasActiveFamilyMembership();
});
