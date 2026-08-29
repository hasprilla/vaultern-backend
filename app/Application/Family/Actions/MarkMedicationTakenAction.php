<?php

declare(strict_types=1);

namespace App\Application\Family\Actions;

use App\Models\FamilyMedication;
use App\Models\FamilyMedicationLog;
use App\Models\User;
use App\Services\FamilyNotificationService;
use Illuminate\Support\Str;

final class MarkMedicationTakenAction
{
    public function __construct(
        private readonly FamilyNotificationService $notifications,
    ) {}

    public function execute(User $actor, FamilyMedication $med, ?string $note = null): FamilyMedicationLog
    {
        abort_if((string) $actor->family_id !== (string) $med->family_id, 403);
        abort_unless($med->active, 422, 'Medicamento inactivo');

        $med->loadMissing('patient:id,name');

        $log = FamilyMedicationLog::query()->create([
            'id' => (string) Str::uuid(),
            'medication_id' => $med->id,
            'taken_by' => $actor->id,
            'taken_at' => now(),
            'note' => $note,
        ]);

        $who = $med->patient?->name ?? 'el paciente';
        $this->notifications->notifyFamily(
            $actor,
            'family_medication',
            'Dosis registrada',
            "{$actor->name}: «{$med->name}» tomada ({$who})",
            [
                'entity_type' => 'family_medication',
                'entity_id' => $med->id,
                'family_medication_id' => $med->id,
            ],
        );

        return $log->load('taker:id,name');
    }
}
