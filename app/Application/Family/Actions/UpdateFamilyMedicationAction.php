<?php

declare(strict_types=1);

namespace App\Application\Family\Actions;

use App\Models\FamilyMedication;
use App\Models\User;

final class UpdateFamilyMedicationAction
{
    /** @param array<string, mixed> $data */
    public function execute(User $actor, FamilyMedication $med, array $data): FamilyMedication
    {
        abort_if((string) $actor->family_id !== (string) $med->family_id, 403);

        $med->fill(array_intersect_key($data, array_flip([
            'name', 'dose_text', 'schedule_times', 'active', 'notes', 'patient_user_id',
        ])));
        $med->save();

        return $med->load(['patient:id,name', 'logs' => fn ($q) => $q->latest('taken_at')->limit(1)]);
    }
}
