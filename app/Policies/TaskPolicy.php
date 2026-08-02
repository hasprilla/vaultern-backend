<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use App\Services\TaskVisibilityService;

class TaskPolicy
{
    public function __construct(
        private readonly TaskVisibilityService $visibility,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->family_id !== null && $user->hasActiveFamilyMembership();
    }

    public function view(User $user, Task $task): bool
    {
        return $this->visibility->canView($user, $task);
    }

    public function create(User $user): bool
    {
        return $user->canManageTasks();
    }

    public function update(User $user, Task $task): bool
    {
        return $user->canManageTasks() && $this->visibility->canView($user, $task);
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->canManageTasks() && $this->visibility->canView($user, $task);
    }

    public function complete(User $user, Task $task): bool
    {
        return $this->visibility->canView($user, $task);
    }

    public function assign(User $user, Task $task): bool
    {
        return $user->canManageTasks() && $this->visibility->canView($user, $task);
    }
}
