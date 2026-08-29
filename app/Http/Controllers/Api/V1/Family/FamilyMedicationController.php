<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Family;

use App\Application\Family\Actions\CreateFamilyMedicationAction;
use App\Application\Family\Actions\MarkMedicationTakenAction;
use App\Application\Family\Actions\UpdateFamilyMedicationAction;
use App\Application\Family\Queries\ListFamilyMedicationsQuery;
use App\Http\Controllers\Concerns\FindsFamilyMedication;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Family\StoreFamilyMedicationRequest;
use App\Http\Requests\Api\V1\Family\UpdateFamilyMedicationRequest;
use App\Http\Resources\Api\V1\Family\FamilyMedicationLogResource;
use App\Http\Resources\Api\V1\Family\FamilyMedicationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FamilyMedicationController extends Controller
{
    use FindsFamilyMedication;

    public function __construct(
        private readonly ListFamilyMedicationsQuery $list,
        private readonly CreateFamilyMedicationAction $create,
        private readonly UpdateFamilyMedicationAction $update,
        private readonly MarkMedicationTakenAction $markTaken,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return FamilyMedicationResource::collection(
            $this->list->execute($request->user(), $request->boolean('active_only', true)),
        )->response();
    }

    public function store(StoreFamilyMedicationRequest $request): JsonResponse
    {
        return response()->json([
            'data' => new FamilyMedicationResource(
                $this->create->execute($request->user(), $request->validated()),
            ),
        ], 201);
    }

    public function update(UpdateFamilyMedicationRequest $request, string $medication): JsonResponse
    {
        return response()->json([
            'data' => new FamilyMedicationResource(
                $this->update->execute(
                    $request->user(),
                    $this->findMedicationForFamily($request, $medication),
                    $request->validated(),
                ),
            ),
        ]);
    }

    public function taken(Request $request, string $medication): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:255']]);

        return response()->json([
            'data' => new FamilyMedicationLogResource(
                $this->markTaken->execute(
                    $request->user(),
                    $this->findMedicationForFamily($request, $medication),
                    $data['note'] ?? null,
                ),
            ),
        ], 201);
    }
}
