<?php

declare(strict_types=1);

namespace App\Application\School\Actions;

use App\Models\TeacherMembership;
use App\Models\User;

final class UpdateTeacherProfileAction
{
    /**
     * @param  array{primary_subject?: string|null, subjects?: list<string>|null}  $data
     * @return int  Updated memberships count
     */
    public function execute(User $user, array $data): int
    {
        $payload = [];
        if (array_key_exists('primary_subject', $data)) {
            $payload['primary_subject'] = $data['primary_subject'];
        }
        if (array_key_exists('subjects', $data)) {
            $payload['subjects'] = $data['subjects'] ?? [];
        }
        if ($payload === []) {
            return 0;
        }

        $memberships = TeacherMembership::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->get();

        foreach ($memberships as $membership) {
            $membership->fill($payload)->save();
        }

        return $memberships->count();
    }
}
