<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\School;

use App\Http\Controllers\Controller;
use App\Jobs\DispatchSchoolTaskBroadcastJob;
use App\Models\ClassEnrollment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolTaskBroadcast;
use App\Models\TeacherMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SchoolBroadcastController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $schoolIds = $this->schoolIdsFor($request);

        $broadcasts = SchoolTaskBroadcast::query()
            ->with(['schoolClass', 'creator:id,name'])
            ->whereIn('school_id', $schoolIds)
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($broadcasts);
    }

    public function store(Request $request): JsonResponse
    {
        if (! $request->user()->canBroadcastSchoolTasks()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'school_id'       => ['required', 'uuid', 'exists:schools,id'],
            'school_class_id' => ['nullable', 'uuid', 'exists:school_classes,id'],
            'title'           => ['required', 'string', 'max:255'],
            'description'     => ['nullable', 'string'],
            'subject'         => ['nullable', 'string', 'max:120'],
            'priority'        => ['nullable', 'in:baja,media,alta,urgente'],
            'due_date'        => ['nullable', 'date'],
        ]);

        if (! $this->userBelongsToSchool($request, $validated['school_id'])) {
            return response()->json(['message' => 'No perteneces a este colegio'], 403);
        }

        if (! empty($validated['school_class_id'])) {
            $class = SchoolClass::query()->findOrFail($validated['school_class_id']);
            if ($class->school_id !== $validated['school_id']) {
                return response()->json(['message' => 'Clase inválida para el colegio'], 422);
            }
        }

        $broadcast = SchoolTaskBroadcast::query()->create([
            'id'              => (string) Str::uuid(),
            'school_id'       => $validated['school_id'],
            'school_class_id' => $validated['school_class_id'] ?? null,
            'created_by'      => $request->user()->id,
            'title'           => $validated['title'],
            'description'     => $validated['description'] ?? null,
            'subject'         => $validated['subject'] ?? null,
            'priority'        => $validated['priority'] ?? 'media',
            'due_date'        => $validated['due_date'] ?? null,
            'status'          => 'pending',
        ]);

        DispatchSchoolTaskBroadcastJob::dispatchSync($broadcast->id);

        return response()->json([
            'data' => $broadcast->fresh(['schoolClass', 'creator:id,name']),
        ], 201);
    }

    public function show(Request $request, SchoolTaskBroadcast $broadcast): JsonResponse
    {
        if (! $this->userBelongsToSchool($request, $broadcast->school_id)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $broadcast->load(['schoolClass', 'creator:id,name', 'tasks.assignee:id,name']);

        $completed = $broadcast->tasks()->where('status', 'done')->count();

        return response()->json([
            'data' => $broadcast,
            'stats' => [
                'total'     => $broadcast->tasks_total,
                'created'   => $broadcast->tasks_created,
                'completed' => $completed,
                'pending'   => max(0, $broadcast->tasks_created - $completed),
            ],
        ]);
    }

    public function classes(Request $request): JsonResponse
    {
        $schoolIds = $this->schoolIdsFor($request);

        $classes = SchoolClass::query()
            ->with(['school:id,name,code'])
            ->withCount('enrollments')
            ->whereIn('school_id', $schoolIds)
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $classes]);
    }

    public function schools(Request $request): JsonResponse
    {
        $schoolIds = $this->schoolIdsFor($request);

        $schools = School::query()
            ->whereIn('id', $schoolIds)
            ->where('is_active', true)
            ->withCount('classes')
            ->get();

        return response()->json(['data' => $schools]);
    }

    /** @return array<int, string> */
    private function schoolIdsFor(Request $request): array
    {
        return TeacherMembership::query()
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->pluck('school_id')
            ->all();
    }

    private function userBelongsToSchool(Request $request, string $schoolId): bool
    {
        if ($request->user()->canManageSchool()) {
            return TeacherMembership::query()
                ->where('user_id', $request->user()->id)
                ->where('school_id', $schoolId)
                ->where('status', 'active')
                ->exists();
        }

        return TeacherMembership::query()
            ->where('user_id', $request->user()->id)
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->exists();
    }
}
