<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->family_id !== null && $user->hasActiveFamilyMembership();
    }

    public function view(User $user, Task $task): bool
    {
        return $this->sameFamily($user, $task);
    }

    public function create(User $user): bool
    {
        return $user->canManageTasks();
    }

    public function update(User $user, Task $task): bool
    {
        return $user->canManageTasks() && $this->sameFamily($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->canManageTasks() && $this->sameFamily($user, $task);
    }

    public function complete(User $user, Task $task): bool
    {
        return $this->sameFamily($user, $task);
    }

    public function assign(User $user, Task $task): bool
    {
        return $user->canManageTasks() && $this->sameFamily($user, $task);
    }

    private function sameFamily(User $user, Task $task): bool
    {
        return $user->family_id !== null
            && (string) $user->family_id === (string) $task->family_id
            && $user->hasActiveFamilyMembership();
    }
}
