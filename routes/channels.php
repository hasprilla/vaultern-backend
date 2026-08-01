<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{id}', function (User $user, int|string $id): bool {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('family.{familyId}', function (User $user, string $familyId): bool {
    return $user->family_id !== null && (string) $user->family_id === $familyId;
});
