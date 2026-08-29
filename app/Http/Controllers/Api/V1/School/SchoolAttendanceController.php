<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\School;

use App\Application\School\Actions\SubmitSchoolAttendanceAction;
use App\Application\School\Queries\ListMySchoolAttendanceQuery;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\School\SchoolAttendanceLogResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SchoolAttendanceController extends Controller
{
    public function __construct(
        private readonly ListMySchoolAttendanceQuery $list,
        private readonly SubmitSchoolAttendanceAction $submit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['sometimes', 'date'],
        ]);

        return response()->json([
            'data' => $this->list->handle($request->user(), $data['date'] ?? null),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_user_id' => ['required', 'integer', 'exists:users,id'],
            'status' => ['required', 'string', 'in:present,absent,late,sick'],
            'attendance_date' => ['sometimes', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json([
            'data' => new SchoolAttendanceLogResource(
                $this->submit->execute($request->user(), $data),
            ),
        ], 201);
    }
}
