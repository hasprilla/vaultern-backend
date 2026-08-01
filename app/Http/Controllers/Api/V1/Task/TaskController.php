<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Task;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesPagination;
use App\Http\Controllers\Concerns\ReturnsForbidden;
use App\Models\Task;
use App\Models\User;
use App\Services\FamilyNotificationService;
use App\Support\FamilyRealtime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TaskController extends Controller
{
    use ResolvesPagination;
    use ReturnsForbidden;

    public function __construct(private readonly FamilyNotificationService $notifications) {}

    public function index(Request $request): JsonResponse
    {
        $query = Task::query()->with(['creator', 'assignee']);

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->input('assigned_to'));
        }

        if ($request->filled('status')) {
            $status = $request->input('status');

            if ($status === 'overdue') {
                $query->where('status', '!=', 'done')
                    ->where(function ($builder) {
                        $builder->where('status', 'overdue')
                            ->orWhere(function ($nested) {
                                $nested->whereNotNull('due_date')
                                    ->whereDate('due_date', '<', now());
                            });
                    });
            } elseif ($status === 'pending') {
                // Pendientes reales: status pending y sin fecha vencida.
                $query->where('status', 'pending')
                    ->where(function ($builder) {
                        $builder->whereNull('due_date')
                            ->orWhereDate('due_date', '>=', now()->toDateString());
                    });
            } else {
                $query->where('status', $status);
            }
        }

        if ($request->input('status') === 'overdue') {
            $tasks = $query->orderBy('due_date')->paginate($this->perPage($request));
        } else {
            $tasks = $query->orderByDesc('created_at')->paginate($this->perPage($request));
        }

        return response()->json($tasks);
    }

    public function store(Request $request): JsonResponse
    {
        if ($forbidden = $this->forbidUnlessAuthorized('create', Task::class)) {
            return $forbidden;
        }

        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'priority'    => ['nullable', 'in:baja,media,alta,urgente'],
            'due_date'    => ['nullable', 'date'],
            'is_school'   => ['nullable', 'boolean'],
            'subject'     => ['nullable', 'string', 'max:120'],
        ]);

        $task = Task::query()->create([
            'id'          => (string) Str::uuid(),
            'family_id'   => $request->user()->family_id,
            'created_by'  => $request->user()->id,
            'assigned_to' => $validated['assigned_to'] ?? null,
            'title'       => $validated['title'],
            'description' => $validated['description'] ?? null,
            'priority'    => $validated['priority'] ?? 'media',
            'due_date'    => $validated['due_date'] ?? null,
            'is_school'   => $validated['is_school'] ?? false,
            'subject'     => $validated['subject'] ?? null,
            'status'      => 'pending',
        ]);

        $task->load(['creator', 'assignee']);

        $body = $task->assigned_to && $task->assignee !== null
            ? "{$request->user()->name} creó «{$task->title}» y la asignó a {$task->assignee->name}"
            : "{$request->user()->name} creó la tarea «{$task->title}»";

        $this->notifyTaskAudience(
            $request->user(),
            $task,
            'task_created',
            'Nueva tarea',
            $body,
        );

        FamilyRealtime::taskChanged(
            familyId: (string) $request->user()->family_id,
            taskId: (string) $task->id,
            action: 'created',
            status: $task->status,
            title: $task->title,
            assigneeId: $task->assigned_to !== null ? (int) $task->assigned_to : null,
            actorId: (int) $request->user()->id,
        );

        return response()->json(['data' => $task], 201);
    }

    public function show(string $task): JsonResponse
    {
        $model = Task::query()->with(['creator', 'assignee'])->findOrFail($task);

        if ($forbidden = $this->forbidUnlessAuthorized('view', $model)) {
            return $forbidden;
        }

        return response()->json(['data' => $model]);
    }

    public function update(Request $request, string $task): JsonResponse
    {
        $model = Task::query()->findOrFail($task);

        if ($forbidden = $this->forbidUnlessAuthorized('update', $model)) {
            return $forbidden;
        }

        $validated = $request->validate([
            'title'       => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority'    => ['sometimes', 'in:baja,media,alta,urgente'],
            'due_date'    => ['nullable', 'date'],
            'status'      => ['sometimes', 'in:pending,in_progress,done,overdue'],
        ]);

        $model->update($validated);
        $model = $model->fresh(['creator', 'assignee']);

        $this->notifyTaskAudience(
            $request->user(),
            $model,
            'task_updated',
            'Tarea actualizada',
            "{$request->user()->name} actualizó «{$model->title}»",
        );

        FamilyRealtime::taskChanged(
            familyId: (string) $request->user()->family_id,
            taskId: (string) $model->id,
            action: 'updated',
            status: $model->status,
            title: $model->title,
            assigneeId: $model->assigned_to !== null ? (int) $model->assigned_to : null,
            actorId: (int) $request->user()->id,
        );

        return response()->json(['data' => $model]);
    }

    public function destroy(Request $request, string $task): JsonResponse
    {
        $model = Task::query()->with('assignee')->findOrFail($task);

        if ($forbidden = $this->forbidUnlessAuthorized('delete', $model)) {
            return $forbidden;
        }
        $title = $model->title;

        $this->notifyTaskAudience(
            $request->user(),
            $model,
            'task_deleted',
            'Tarea eliminada',
            "{$request->user()->name} eliminó la tarea «{$title}»",
        );

        $model->delete();

        FamilyRealtime::taskChanged(
            familyId: (string) $request->user()->family_id,
            taskId: (string) $task,
            action: 'deleted',
            status: null,
            title: $title,
            assigneeId: null,
            actorId: (int) $request->user()->id,
        );

        return response()->json(['message' => 'Task deleted']);
    }

    public function complete(Request $request, string $task): JsonResponse
    {
        $model = Task::query()->findOrFail($task);

        if ($forbidden = $this->forbidUnlessAuthorized('complete', $model)) {
            return $forbidden;
        }

        $model->update([
            'status'       => 'done',
            'completed_at' => now(),
        ]);

        $model = $model->fresh(['creator', 'assignee']);

        $this->notifyTaskAudience(
            $request->user(),
            $model,
            'task_completed',
            'Tarea completada',
            "{$request->user()->name} completó «{$model->title}»",
        );

        FamilyRealtime::taskChanged(
            familyId: (string) $request->user()->family_id,
            taskId: (string) $model->id,
            action: 'completed',
            status: $model->status,
            title: $model->title,
            assigneeId: $model->assigned_to !== null ? (int) $model->assigned_to : null,
            actorId: (int) $request->user()->id,
        );

        return response()->json(['data' => $model]);
    }

    public function assign(Request $request, string $task): JsonResponse
    {
        $model = Task::query()->findOrFail($task);

        if ($forbidden = $this->forbidUnlessAuthorized('assign', $model)) {
            return $forbidden;
        }

        $validated = $request->validate([
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
        ]);

        $model->update(['assigned_to' => $validated['assigned_to']]);
        $model = $model->fresh(['assignee']);

        $this->notifyTaskAudience(
            $request->user(),
            $model,
            'task_assigned',
            'Tarea asignada',
            "{$request->user()->name} asignó «{$model->title}» a {$model->assignee?->name}",
        );

        FamilyRealtime::taskChanged(
            familyId: (string) $request->user()->family_id,
            taskId: (string) $model->id,
            action: 'assigned',
            status: $model->status,
            title: $model->title,
            assigneeId: $model->assigned_to !== null ? (int) $model->assigned_to : null,
            actorId: (int) $request->user()->id,
        );

        return response()->json(['data' => $model]);
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
            'entity_id'   => (string) $task->id,
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
