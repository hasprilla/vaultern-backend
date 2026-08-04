<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\School;

use App\Application\School\Actions\CreateSchoolBroadcastAction;
use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolSubscription;
use App\Models\SchoolTaskBroadcast;
use App\Models\TeacherMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchoolBroadcastController extends Controller
{
    public function __construct(
        private readonly CreateSchoolBroadcastAction $createBroadcast,
    ) {}

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
        $validated = $request->validate([
            'school_id'       => ['required', 'uuid', 'exists:schools,id'],
            'school_class_id' => ['nullable', 'uuid', 'exists:school_classes,id'],
            'title'           => ['required', 'string', 'max:255'],
            'description'     => ['nullable', 'string'],
            'subject'         => ['nullable', 'string', 'max:120'],
            'priority'        => ['nullable', 'in:baja,media,alta,urgente'],
            'due_date'        => ['nullable', 'date'],
        ]);

        $result = $this->createBroadcast->execute(
            $request->user(),
            $validated,
            $this->userBelongsToSchool($request, $validated['school_id']),
        );

        if (($result['ok'] ?? false) !== true) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        return response()->json(['data' => $result['broadcast']], 201);
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
        $user = $request->user();

        if ($user->isPlatformAdmin()) {
            $schools = School::query()
                ->where('is_active', true)
                ->withCount('classes')
                ->orderBy('name')
                ->get();

            $subscriptions = SchoolSubscription::query()
                ->whereIn('school_id', $schools->pluck('id'))
                ->get()
                ->keyBy('school_id');

            $data = $schools->map(static function (School $school) use ($subscriptions) {
                $sub = $subscriptions->get($school->id);

                return [
                    'id' => $school->id,
                    'name' => $school->name,
                    'code' => $school->code,
                    'city' => $school->city,
                    'plan' => $school->plan,
                    'is_active' => $school->is_active,
                    'classes_count' => $school->classes_count ?? 0,
                    'membership' => [
                        'id' => null,
                        'role' => 'admin',
                        'status' => 'active',
                    ],
                    'subscription' => $sub === null ? null : [
                        'id' => $sub->id,
                        'plan_code' => $sub->plan_code,
                        'status' => $sub->status,
                        'billing' => $sub->billing,
                        'current_period_end' => $sub->current_period_end,
                    ],
                ];
            })->values();

            return response()->json(['data' => $data]);
        }

        $memberships = TeacherMembership::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->with(['school' => static fn ($q) => $q->where('is_active', true)->withCount('classes')])
            ->orderByDesc('updated_at')
            ->get();

        $schoolIds = $memberships
            ->pluck('school_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $subscriptions = SchoolSubscription::query()
            ->whereIn('school_id', $schoolIds)
            ->get()
            ->keyBy('school_id');

        $data = $memberships
            ->filter(static fn (TeacherMembership $m) => $m->school !== null)
            ->map(static function (TeacherMembership $m) use ($subscriptions) {
                $school = $m->school;
                $sub = $subscriptions->get($school->id);

                return [
                    'id' => $school->id,
                    'name' => $school->name,
                    'code' => $school->code,
                    'city' => $school->city,
                    'plan' => $school->plan,
                    'is_active' => $school->is_active,
                    'classes_count' => $school->classes_count ?? 0,
                    'membership' => [
                        'id' => $m->id,
                        'role' => $m->role,
                        'status' => $m->status,
                    ],
                    'subscription' => $sub === null ? null : [
                        'id' => $sub->id,
                        'plan_code' => $sub->plan_code,
                        'status' => $sub->status,
                        'billing' => $sub->billing,
                        'current_period_end' => $sub->current_period_end,
                    ],
                ];
            })
            ->values();

        return response()->json(['data' => $data]);
    }

    /** @return array<int, string> */
    private function schoolIdsFor(Request $request): array
    {
        if ($request->user()->isPlatformAdmin()) {
            return School::query()->pluck('id')->map(fn ($id) => (string) $id)->all();
        }

        return TeacherMembership::query()
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->pluck('school_id')
            ->all();
    }

    private function userBelongsToSchool(Request $request, string $schoolId): bool
    {
        if ($request->user()->isPlatformAdmin()) {
            return true;
        }

        return TeacherMembership::query()
            ->where('user_id', $request->user()->id)
            ->where('school_id', $schoolId)
            ->where('status', 'active')
            ->exists();
    }
}
