<?php

declare(strict_types=1);

namespace App\Application\Task\Actions;

use App\Models\Task;
use App\Models\User;
use App\Services\FamilyNotificationService;
use App\Support\FamilyRealtime;
use Illuminate\Support\Str;

final class CreateTaskAction
{
    public function __construct(
        private readonly FamilyNotificationService $notifications,
    ) {}

    /**
     * @param  array{
     *   title: string,
     *   description?: string|null,
     *   assigned_to?: int|null,
     *   priority?: string|null,
     *   due_date?: string|null,
     *   is_school?: bool|null,
     *   subject?: string|null
     * }  $validated
     */
    public function execute(User $actor, array $validated): Task
    {
        $task = Task::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $actor->family_id,
            'created_by' => $actor->id,
            'assigned_to' => $validated['assigned_to'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'priority' => $validated['priority'] ?? 'media',
            'due_date' => $validated['due_date'] ?? null,
            'is_school' => $validated['is_school'] ?? false,
            'subject' => $validated['subject'] ?? null,
            'status' => 'pending',
        ]);

        $task->load(['creator', 'assignee']);

        $body = $task->assigned_to && $task->assignee !== null
            ? "{$actor->name} creó «{$task->title}» y la asignó a {$task->assignee->name}"
            : "{$actor->name} creó la tarea «{$task->title}»";

        $this->notifyTaskAudience(
            $actor,
            $task,
            'task_created',
            'Nueva tarea',
            $body,
        );

        FamilyRealtime::taskChanged(
            familyId: (string) $actor->family_id,
            taskId: (string) $task->id,
            action: 'created',
            status: $task->status,
            title: $task->title,
            assigneeId: $task->assigned_to !== null ? (int) $task->assigned_to : null,
            actorId: (int) $actor->id,
        );

        return $task;
    }

    /**
     * Si la tarea está asignada a un hijo, solo notifica a sus custodios (+ el hijo).
     * Si no, mantiene el fan-out familiar.
     *
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
