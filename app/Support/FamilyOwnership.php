<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Family;
use App\Models\User;

/** Resolución única de “quién es el dueño de la membresía”. */
final class FamilyOwnership
{
    public static function actorIsOwner(User $actor, ?Family $family = null): bool
    {
        if ($actor->family_id === null) {
            return false;
        }

        $family ??= Family::query()->find($actor->family_id);
        if ($family === null) {
            return false;
        }

        if ($actor->isFamilyOwner()) {
            return true;
        }

        // Solo owner_user_id (o isFamilyOwner). Sin match por email: evita falsos dueños.
        return $family->owner_user_id !== null
            && (int) $family->owner_user_id === (int) $actor->id;
    }
}
