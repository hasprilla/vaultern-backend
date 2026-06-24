<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Task;

use App\Application\Task\Commands\CreateTaskCommand;
use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TaskController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Task::query()->with(['creator', 'assignee']);

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->input('assigned_to'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $tasks = $query->orderByDesc('created_at')->paginate(20);

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

        $command = new CreateTaskCommand(
            familyId: $request->user()->family_id,
            title: $validated['title'],
            description: $validated['description'] ?? null,
            createdBy: (string) $request->user()->id,
            assignedTo: isset($validated['assigned_to']) ? (string) $validated['assigned_to'] : null,
            priority: $validated['priority'] ?? 'media',
            dueDate: $validated['due_date'] ?? null,
            isSchool: $validated['is_school'] ?? false,
            subject: $validated['subject'] ?? null,
        );

        $task = Task::query()->create([
            'id'          => (string) Str::uuid(),
            'family_id'   => $command->familyId,
            'created_by'  => $request->user()->id,
            'assigned_to' => $validated['assigned_to'] ?? null,
            'title'       => $command->title,
            'description' => $command->description,
            'priority'    => $command->priority,
            'due_date'    => $command->dueDate,
            'is_school'   => $command->isSchool,
            'subject'     => $command->subject,
            'status'      => 'pending',
        ]);

        return response()->json(['data' => $task->load(['creator', 'assignee'])], 201);
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

        return response()->json(['data' => $model->fresh(['creator', 'assignee'])]);
    }

    public function destroy(Request $request, string $task): JsonResponse
    {
        if (! $request->user()->canManageTasks()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        Task::query()->findOrFail($task)->delete();

        return response()->json(['message' => 'Task deleted']);
    }

    public function complete(Request $request, string $task): JsonResponse
    {
        $model = Task::query()->findOrFail($task);

        $model->update([
            'status'       => 'done',
            'completed_at' => now(),
        ]);

        return response()->json(['data' => $model->fresh()]);
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

        return response()->json(['data' => $model->fresh(['assignee'])]);
    }
}
