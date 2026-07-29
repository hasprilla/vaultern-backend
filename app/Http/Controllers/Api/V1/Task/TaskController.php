<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Task;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesPagination;
use App\Models\Task;
use App\Services\FamilyNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TaskController extends Controller
{
    use ResolvesPagination;

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
        if (! $request->user()->canManageTasks()) {
            return response()->json(['message' => 'Forbidden'], 403);
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

        $this->notifications->notifyFamily(
            $request->user(),
            'task_created',
            'Nueva tarea',
            $body,
            ['entity_type' => 'task', 'entity_id' => $task->id],
        );

        return response()->json(['data' => $task], 201);
    }

    public function show(string $task): JsonResponse
    {
        $model = Task::query()->with(['creator', 'assignee'])->findOrFail($task);

        return response()->json(['data' => $model]);
    }

    public function update(Request $request, string $task): JsonResponse
    {
        if (! $request->user()->canManageTasks()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $model = Task::query()->findOrFail($task);

        $validated = $request->validate([
            'title'       => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority'    => ['sometimes', 'in:baja,media,alta,urgente'],
            'due_date'    => ['nullable', 'date'],
            'status'      => ['sometimes', 'in:pending,in_progress,done,overdue'],
        ]);

        $model->update($validated);
        $model = $model->fresh(['creator', 'assignee']);

        $this->notifications->notifyFamily(
            $request->user(),
            'task_updated',
            'Tarea actualizada',
            "{$request->user()->name} actualizó «{$model->title}»",
            ['entity_type' => 'task', 'entity_id' => $model->id],
        );

        return response()->json(['data' => $model]);
    }

    public function destroy(Request $request, string $task): JsonResponse
    {
        if (! $request->user()->canManageTasks()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $model = Task::query()->findOrFail($task);
        $title = $model->title;
        $model->delete();

        $this->notifications->notifyFamily(
            $request->user(),
            'task_deleted',
            'Tarea eliminada',
            "{$request->user()->name} eliminó la tarea «{$title}»",
            ['entity_type' => 'task', 'entity_id' => $task],
        );

        return response()->json(['message' => 'Task deleted']);
    }

    public function complete(Request $request, string $task): JsonResponse
    {
        $model = Task::query()->findOrFail($task);

        $model->update([
            'status'       => 'done',
            'completed_at' => now(),
        ]);

        $model = $model->fresh(['creator', 'assignee']);

        $this->notifications->notifyFamily(
            $request->user(),
            'task_completed',
            'Tarea completada',
            "{$request->user()->name} completó «{$model->title}»",
            ['entity_type' => 'task', 'entity_id' => $model->id],
        );

        return response()->json(['data' => $model]);
    }

    public function assign(Request $request, string $task): JsonResponse
    {
        if (! $request->user()->canManageTasks()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
        ]);

        $model = Task::query()->findOrFail($task);
        $model->update(['assigned_to' => $validated['assigned_to']]);
        $model = $model->fresh(['assignee']);

        $this->notifications->notifyFamily(
            $request->user(),
            'task_assigned',
            'Tarea asignada',
            "{$request->user()->name} asignó «{$model->title}» a {$model->assignee?->name}",
            ['entity_type' => 'task', 'entity_id' => $model->id],
        );

        return response()->json(['data' => $model]);
    }
}
