<?php

declare(strict_types=1);

namespace App\Application\Task\Actions;

use App\Models\Task;
use App\Models\User;
use App\Services\FamilyNotificationService;
use App\Support\FamilyRealtime;

final class DeleteTaskAction
{
    public function __construct(
        private readonly FamilyNotificationService $notifications,
    ) {}

    public function execute(User $actor, Task $task): void
    {
        $taskId = (string) $task->id;
        $title = $task->title;

        $this->notifyTaskAudience(
            $actor,
            $task,
            'task_deleted',
            'Tarea eliminada',
            "{$actor->name} eliminó la tarea «{$title}»",
        );

        $task->delete();

        FamilyRealtime::taskChanged(
            familyId: (string) $actor->family_id,
            taskId: $taskId,
            action: 'deleted',
            status: null,
            title: $title,
            assigneeId: null,
            actorId: (int) $actor->id,
        );
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
