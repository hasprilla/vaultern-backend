<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\School;

use App\Application\School\Actions\UpdateTeacherProfileAction;
use App\Application\School\Queries\GetTeacherProfileQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TeacherProfileController extends Controller
{
    public function __construct(
        private readonly GetTeacherProfileQuery $getProfile,
        private readonly UpdateTeacherProfileAction $updateProfile,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->getProfile->handle($request->user()),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'primary_subject' => ['sometimes', 'nullable', 'string', 'max:120'],
            'subjects' => ['sometimes', 'nullable', 'array', 'max:12'],
            'subjects.*' => ['string', 'max:120'],
        ]);

        $this->updateProfile->execute($request->user(), $validated);

        return response()->json([
            'data' => $this->getProfile->handle($request->user()->fresh()),
        ]);
    }
}
