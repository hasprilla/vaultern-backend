<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Task;

use App\Application\Task\Actions\AssignTaskAction;
use App\Application\Task\Actions\CompleteTaskAction;
use App\Application\Task\Actions\CreateTaskAction;
use App\Application\Task\Actions\DeleteTaskAction;
use App\Application\Task\Actions\UpdateTaskAction;
use App\Application\Task\Queries\ListTasksQuery;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesPagination;
use App\Http\Controllers\Concerns\ReturnsForbidden;
use App\Http\Resources\Api\V1\TaskResource;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    use ResolvesPagination;
    use ReturnsForbidden;

    public function __construct(
        private readonly ListTasksQuery $listTasks,
        private readonly CreateTaskAction $createTask,
        private readonly UpdateTaskAction $updateTask,
        private readonly CompleteTaskAction $completeTask,
        private readonly DeleteTaskAction $deleteTask,
        private readonly AssignTaskAction $assignTask,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tasks = $this->listTasks->execute(
            [
                'assigned_to' => $request->input('assigned_to'),
                'status' => $request->input('status'),
            ],
            $this->perPage($request),
        );

        return response()->json($tasks);
    }

    public function store(Request $request): JsonResponse
    {
        if ($forbidden = $this->forbidUnlessAuthorized('create', Task::class)) {
            return $forbidden;
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'priority' => ['nullable', 'in:baja,media,alta,urgente'],
            'due_date' => ['nullable', 'date'],
            'is_school' => ['nullable', 'boolean'],
            'subject' => ['nullable', 'string', 'max:120'],
        ]);

        $task = $this->createTask->execute($request->user(), $validated);

        return response()->json(['data' => new TaskResource($task)], 201);
    }

    public function show(string $task): JsonResponse
    {
        $model = Task::query()->with(['creator', 'assignee'])->findOrFail($task);

        if ($forbidden = $this->forbidUnlessAuthorized('view', $model)) {
            return $forbidden;
        }

        return response()->json(['data' => new TaskResource($model)]);
    }

    public function update(Request $request, string $task): JsonResponse
    {
        $model = Task::query()->findOrFail($task);

        if ($forbidden = $this->forbidUnlessAuthorized('update', $model)) {
            return $forbidden;
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['sometimes', 'in:baja,media,alta,urgente'],
            'due_date' => ['nullable', 'date'],
            'status' => ['sometimes', 'in:pending,in_progress,done,overdue'],
        ]);

        $model = $this->updateTask->execute($request->user(), $model, $validated);

        return response()->json(['data' => new TaskResource($model)]);
    }

    public function destroy(Request $request, string $task): JsonResponse
    {
        $model = Task::query()->with('assignee')->findOrFail($task);

        if ($forbidden = $this->forbidUnlessAuthorized('delete', $model)) {
            return $forbidden;
        }

        $this->deleteTask->execute($request->user(), $model);

        return response()->json(['message' => 'Task deleted']);
    }

    public function complete(Request $request, string $task): JsonResponse
    {
        $model = Task::query()->findOrFail($task);

        if ($forbidden = $this->forbidUnlessAuthorized('complete', $model)) {
            return $forbidden;
        }

        $model = $this->completeTask->execute($request->user(), $model);

        return response()->json(['data' => new TaskResource($model)]);
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

        $model = $this->assignTask->execute(
            $request->user(),
            $model,
            (int) $validated['assigned_to'],
        );

        return response()->json(['data' => new TaskResource($model)]);
    }
}
