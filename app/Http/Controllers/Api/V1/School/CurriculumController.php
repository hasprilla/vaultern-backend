<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\School;

use App\Application\School\Queries\GetCurriculumTemplateQuery;
use App\Application\School\Queries\ListCurriculumProfilesQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class CurriculumController extends Controller
{
    public function profiles(Request $request, ListCurriculumProfilesQuery $query): JsonResponse
    {
        $validated = $request->validate([
            'country' => ['nullable', 'string', 'size:2'],
        ]);

        return response()->json([
            'data' => $query->handle($validated['country'] ?? 'CO'),
        ]);
    }

    public function template(Request $request, GetCurriculumTemplateQuery $query): JsonResponse
    {
        $validated = $request->validate([
            'country' => ['nullable', 'string', 'size:2'],
            'level' => ['required', Rule::in(['primaria', 'secundaria', 'preescolar'])],
            'shift' => ['required', Rule::in(['manana', 'tarde'])],
        ]);

        $data = $query->handle(
            $validated['country'] ?? 'CO',
            $validated['level'],
            $validated['shift'],
        );

        if ($data === null) {
            return response()->json(['message' => 'Plantilla no encontrada'], 404);
        }

        return response()->json(['data' => $data]);
    }
}
