<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\School;

use App\Application\School\Actions\CancelEnrollmentAction;
use App\Application\School\Actions\EnrollStudentAction;
use App\Application\School\Actions\RegisterSchoolAction;
use App\Http\Controllers\Controller;
use App\Models\ClassEnrollment;
use App\Models\School;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchoolEnrollmentController extends Controller
{
    public function __construct(
        private readonly EnrollStudentAction $enrollStudent,
        private readonly CancelEnrollmentAction $cancelEnrollment,
        private readonly RegisterSchoolAction $registerSchool,
    ) {}

    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'school_code' => ['required', 'string', 'max:12'],
        ]);

        $school = School::query()
            ->where('code', strtoupper($validated['school_code']))
            ->where('is_active', true)
            ->with(['classes' => fn ($q) => $q->orderBy('name')])
            ->first();

        if ($school === null) {
            return response()->json(['message' => 'Colegio no encontrado'], 404);
        }

        return response()->json(['data' => $school]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'school_code' => ['required', 'string', 'max:12'],
            'school_class_id' => ['required', 'uuid', 'exists:school_classes,id'],
            'student_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $result = $this->enrollStudent->execute($request->user(), $validated);

        if (($result['ok'] ?? false) !== true) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        return response()->json(['data' => $result['enrollment']], 201);
    }

    public function index(Request $request): JsonResponse
    {
        if ($request->user()->family_id === null) {
            return response()->json(['data' => []]);
        }

        $enrollments = ClassEnrollment::query()
            ->with(['schoolClass.school', 'student'])
            ->where('family_id', $request->user()->family_id)
            ->where('status', 'active')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $enrollments]);
    }

    public function destroy(Request $request, string $enrollment): JsonResponse
    {
        $model = ClassEnrollment::query()->findOrFail($enrollment);
        $result = $this->cancelEnrollment->execute($request->user(), $model);

        if (($result['ok'] ?? false) !== true) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        return response()->json(['message' => 'Vinculación con el colegio cancelada.']);
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'city' => ['nullable', 'string', 'max:80'],
            'class_name' => ['required', 'string', 'min:1', 'max:80'],
        ]);

        $result = $this->registerSchool->execute($request->user(), $validated);

        if (($result['ok'] ?? false) !== true) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        return response()->json([
            'message' => 'Colegio registrado. Comparte el código con otras familias.',
            'data' => $result['school'],
        ], 201);
    }
}
