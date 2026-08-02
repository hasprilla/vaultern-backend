<?php

declare(strict_types=1);

namespace App\Application\Task\Actions;

use App\Models\Task;
use App\Models\User;
use App\Services\FamilyNotificationService;
use App\Services\TaskVisibilityService;
use App\Support\FamilyRealtime;
use Illuminate\Validation\ValidationException;

final class UpdateTaskAction
{
    public function __construct(
        private readonly FamilyNotificationService $notifications,
        private readonly TaskVisibilityService $visibility,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(User $actor, Task $task, array $validated): Task
    {
        if (array_key_exists('assigned_to', $validated)) {
            $assigneeId = $validated['assigned_to'] !== null ? (int) $validated['assigned_to'] : null;
            if (! $this->visibility->canAssignTo($actor, $assigneeId)) {
                throw ValidationException::withMessages([
                    'assigned_to' => 'Solo puedes asignar tareas a hijos de los que eres custodio.',
                ]);
            }
        }

        $task->update($validated);
        $task = $task->fresh(['creator', 'assignee']);

        $this->notifyTaskAudience(
            $actor,
            $task,
            'task_updated',
            'Tarea actualizada',
            "{$actor->name} actualizó «{$task->title}»",
        );

        FamilyRealtime::taskChanged(
            familyId: (string) $actor->family_id,
            taskId: (string) $task->id,
            action: 'updated',
            status: $task->status,
            title: $task->title,
            assigneeId: $task->assigned_to !== null ? (int) $task->assigned_to : null,
            actorId: (int) $actor->id,
        );

        return $task;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function notifyTaskAudience(
        User $actor,
        Task $task,
        string $type,
        string $title,
        string $body,
        array $data = [],
    ): void {
        $payload = array_merge($data, [
            'entity_type' => 'task',
            'entity_id' => (string) $task->id,
        ]);

        $assignee = $task->relationLoaded('assignee')
            ? $task->assignee
            : ($task->assigned_to !== null ? User::query()->find($task->assigned_to) : null);

        if ($assignee !== null && $assignee->role === 'hijo') {
            $this->notifications->notifyChildGuardians(
                $actor,
                (int) $assignee->id,
                $type,
                $title,
                $body,
                $payload,
            );

            if ((int) $assignee->id !== (int) $actor->id) {
                $this->notifications->notifyUsers(
                    $actor,
                    [(int) $assignee->id],
                    $type,
                    $title,
                    $body,
                    $payload,
                );
            }

            return;
        }

        $this->notifications->notifyFamily($actor, $type, $title, $body, $payload);
    }
}
