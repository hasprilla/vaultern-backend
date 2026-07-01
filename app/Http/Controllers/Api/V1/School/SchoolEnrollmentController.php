<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\School;

use App\Http\Controllers\Controller;
use App\Models\ClassEnrollment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\FamilyNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SchoolEnrollmentController extends Controller
{
    public function __construct(private readonly FamilyNotificationService $notifications) {}

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
        if (! $request->user()->canManageTasks()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'school_code'     => ['required', 'string', 'max:12'],
            'school_class_id' => ['required', 'uuid', 'exists:school_classes,id'],
            'student_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $school = School::query()
            ->where('code', strtoupper($validated['school_code']))
            ->where('is_active', true)
            ->firstOrFail();

        $class = SchoolClass::query()
            ->where('school_id', $school->id)
            ->findOrFail($validated['school_class_id']);

        $student = User::query()->findOrFail($validated['student_user_id']);

        if ($student->family_id !== $request->user()->family_id || $student->role !== 'hijo') {
            return response()->json(['message' => 'El alumno debe ser un hijo de tu familia'], 422);
        }

        $enrollment = ClassEnrollment::query()->updateOrCreate(
            [
                'school_class_id' => $class->id,
                'student_user_id' => $student->id,
            ],
            [
                'family_id'   => $student->family_id,
                'enrolled_by' => $request->user()->id,
                'status'      => 'active',
            ],
        );

        $enrollment->load(['schoolClass.school', 'student']);

        $this->notifications->notifyFamily(
            $request->user(),
            'school_enrollment',
            'Inscripción escolar',
            "{$request->user()->name} inscribió a {$student->name} en {$school->name}",
            ['entity_type' => 'school_enrollment', 'entity_id' => $enrollment->id],
        );

        return response()->json(['data' => $enrollment], 201);
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
        if (! $request->user()->canManageTasks()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $model = ClassEnrollment::query()->findOrFail($enrollment);

        if ($model->family_id !== $request->user()->family_id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $model->load(['schoolClass.school', 'student']);
        $studentName = $model->student?->name ?? 'Alumno';
        $schoolName = $model->schoolClass?->school?->name ?? 'Colegio';

        $model->update(['status' => 'cancelled']);

        $this->notifications->notifyFamily(
            $request->user(),
            'school_enrollment',
            'Inscripción cancelada',
            "{$request->user()->name} canceló la inscripción de {$studentName} en {$schoolName}",
            ['entity_type' => 'school_enrollment', 'entity_id' => $model->id],
        );

        return response()->json(['message' => 'Vinculación con el colegio cancelada.']);
    }
}
