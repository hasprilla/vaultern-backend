<?php

declare(strict_types=1);

namespace App\Application\Family\Actions;

use App\Models\FamilyMedication;
use App\Models\User;
use App\Services\FamilyNotificationService;
use Illuminate\Support\Str;

final class CreateFamilyMedicationAction
{
    public function __construct(
        private readonly FamilyNotificationService $notifications,
    ) {}

    /** @param array<string, mixed> $data */
    public function execute(User $actor, array $data): FamilyMedication
    {
        abort_if($actor->family_id === null, 403);

        $med = FamilyMedication::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $actor->family_id,
            'patient_user_id' => $data['patient_user_id'] ?? null,
            'created_by' => $actor->id,
            'name' => $data['name'],
            'dose_text' => $data['dose_text'] ?? null,
            'schedule_times' => $data['schedule_times'] ?? [],
            'active' => true,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->notifications->notifyFamily(
            $actor,
            'family_medication',
            'Nuevo medicamento',
            "{$actor->name} registró «{$med->name}»",
            [
                'entity_type' => 'family_medication',
                'entity_id' => $med->id,
                'family_medication_id' => $med->id,
            ],
        );

        return $med->load(['patient:id,name', 'logs' => fn ($q) => $q->latest('taken_at')->limit(1)]);
    }
}
