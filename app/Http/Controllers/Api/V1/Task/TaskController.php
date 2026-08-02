<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Task;

use App\Application\Shared\Actions\DeleteAttachmentAction;
use App\Application\Shared\Actions\StoreAttachmentsAction;
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
        private readonly StoreAttachmentsAction $storeAttachmentsAction,
        private readonly DeleteAttachmentAction $deleteAttachmentAction,
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

        return TaskResource::collection($tasks)->response();
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
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:10240'],
        ]);

        $files = $request->file('attachments', []);
        unset($validated['attachments']);

        $task = $this->createTask->execute($request->user(), $validated);
        if ($files !== []) {
            $this->storeAttachmentsAction->execute(
                $request->user(),
                $task,
                is_array($files) ? $files : [$files],
                'tasks',
                'image',
            );
        }
        $task->load(['creator', 'assignee', 'attachments']);

        return response()->json(['data' => new TaskResource($task)], 201);
    }

    public function show(string $task): JsonResponse
    {
        $model = Task::query()->with(['creator', 'assignee', 'attachments'])->findOrFail($task);

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
        $model->load(['creator', 'assignee', 'attachments']);

        return response()->json(['data' => new TaskResource($model)]);
    }

    public function storeAttachments(Request $request, string $task): JsonResponse
    {
        $model = Task::query()->findOrFail($task);

        if ($forbidden = $this->forbidUnlessAuthorized('update', $model)) {
            return $forbidden;
        }

        if ($model->status !== 'pending') {
            return response()->json([
                'message' => 'Solo se pueden agregar adjuntos mientras la tarea está pendiente.',
                'code' => 'task_attachments_locked',
            ], 422);
        }

        $request->validate([
            'attachments' => ['required', 'array', 'min:1', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:10240'],
        ]);

        $files = $request->file('attachments', []);
        $this->storeAttachmentsAction->execute(
            $request->user(),
            $model,
            is_array($files) ? $files : [$files],
            'tasks',
            'image',
        );
        $model->load(['creator', 'assignee', 'attachments']);

        return response()->json(['data' => new TaskResource($model)]);
    }

    public function destroyAttachment(Request $request, string $task, string $attachment): JsonResponse
    {
        $model = Task::query()->findOrFail($task);

        if ($forbidden = $this->forbidUnlessAuthorized('update', $model)) {
            return $forbidden;
        }

        if ($model->status !== 'pending') {
            return response()->json([
                'message' => 'Solo se pueden eliminar adjuntos mientras la tarea está pendiente.',
                'code' => 'task_attachments_locked',
            ], 422);
        }

        $file = $model->attachments()->whereKey($attachment)->firstOrFail();
        $this->deleteAttachmentAction->execute($request->user(), $file);

        return response()->json(['message' => 'Archivo eliminado']);
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
