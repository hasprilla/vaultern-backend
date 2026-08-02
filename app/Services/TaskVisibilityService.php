<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Visibilidad de tareas por custodia: solo custodios del hijo asignado
 * (más creador/asignado) ven la tarea.
 */
final class TaskVisibilityService
{
    public function __construct(
        private readonly ChildGuardianService $guardians,
    ) {}

    public function canView(User $user, Task $task): bool
    {
        if ($user->family_id === null
            || (string) $user->family_id !== (string) $task->family_id
            || ! $user->hasActiveFamilyMembership()) {
            return false;
        }

        if ((int) $task->created_by === (int) $user->id) {
            return true;
        }

        if ($task->assigned_to !== null && (int) $task->assigned_to === (int) $user->id) {
            return true;
        }

        $assignee = $task->relationLoaded('assignee')
            ? $task->assignee
            : ($task->assigned_to !== null ? User::query()->find($task->assigned_to) : null);

        if ($assignee !== null && $assignee->role === 'hijo') {
            return $this->guardians->isGuardianOf($user, (int) $assignee->id);
        }

        // Sin hijo asignado: visibles para quien gestiona tareas en el núcleo.
        return $user->canManageTasks();
    }

    public function canAssignTo(User $actor, ?int $assigneeId): bool
    {
        if ($assigneeId === null) {
            return true;
        }

        $assignee = User::query()->find($assigneeId);
        if ($assignee === null
            || (string) $assignee->family_id !== (string) $actor->family_id) {
            return false;
        }

        if ($assignee->role === 'hijo') {
            return $this->guardians->isGuardianOf($actor, (int) $assignee->id);
        }

        return $actor->canManageTasks();
    }

    /**
     * @return Builder<Task>
     */
    public function scopedQuery(User $viewer): Builder
    {
        $query = Task::query()->where('family_id', $viewer->family_id);

        if ($viewer->role === 'hijo') {
            return $query->where(function (Builder $q) use ($viewer) {
                $q->where('assigned_to', $viewer->id)
                    ->orWhere('created_by', $viewer->id);
            });
        }

        if (! in_array($viewer->role, ['padre', 'madre', 'tutor'], true)) {
            return $query->whereRaw('1 = 0');
        }

        $childIds = $this->guardians->childIdsFor($viewer);

        return $query->where(function (Builder $q) use ($viewer, $childIds) {
            $q->where('created_by', $viewer->id)
                ->orWhere('assigned_to', $viewer->id);

            if ($childIds !== []) {
                $q->orWhereIn('assigned_to', $childIds);
            }

            // Tareas sin asignar o asignadas a adultos: solo si el viewer gestiona tareas.
            if ($viewer->canManageTasks()) {
                $q->orWhere(function (Builder $inner) {
                    $inner->whereNull('assigned_to')
                        ->orWhereHas('assignee', function (Builder $assignee) {
                            $assignee->whereIn('role', ['padre', 'madre', 'tutor']);
                        });
                });
            }
        });
    }
}
