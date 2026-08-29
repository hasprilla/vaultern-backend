<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Family;

use App\Http\Controllers\Concerns\FindsFamilyMedication;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Family\FamilyMedicationLogResource;
use App\Models\FamilyMedicationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FamilyMedicationLogController extends Controller
{
    use FindsFamilyMedication;

    public function index(Request $request, string $medication): JsonResponse
    {
        $med = $this->findMedicationForFamily($request, $medication);
        $rows = FamilyMedicationLog::query()
            ->where('medication_id', $med->id)
            ->with('taker:id,name')
            ->orderByDesc('taken_at')
            ->limit(50)
            ->get();

        return FamilyMedicationLogResource::collection($rows)->response();
    }
}
