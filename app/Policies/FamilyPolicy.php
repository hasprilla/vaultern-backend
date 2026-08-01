<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Family;
use App\Models\User;

class FamilyPolicy
{
    public function view(User $user, Family $family): bool
    {
        return $user->family_id !== null
            && (string) $user->family_id === (string) $family->id
            && $user->hasActiveFamilyMembership();
    }

    public function update(User $user, Family $family): bool
    {
        return $this->view($user, $family) && $user->isFamilyOwner();
    }
}
