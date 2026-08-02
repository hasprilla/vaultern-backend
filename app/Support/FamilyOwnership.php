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

        if ($family->owner_user_id !== null
            && (int) $family->owner_user_id === (int) $actor->id) {
            return true;
        }

        if ($family->owner_user_id !== null) {
            $ownerEmail = User::query()->where('id', $family->owner_user_id)->value('email');
            if (is_string($ownerEmail)
                && strcasecmp(trim($ownerEmail), trim((string) $actor->email)) === 0) {
                return true;
            }
        }

        return false;
    }
}
