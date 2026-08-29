<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\FamilyMedication;
use Illuminate\Http\Request;

trait FindsFamilyMedication
{
    private function findMedicationForFamily(Request $request, string $id): FamilyMedication
    {
        $user = $request->user();
        abort_if($user->family_id === null, 403);

        return FamilyMedication::query()
            ->where('family_id', $user->family_id)
            ->with(['patient:id,name', 'logs' => fn ($q) => $q->latest('taken_at')->limit(1)])
            ->findOrFail($id);
    }
}
