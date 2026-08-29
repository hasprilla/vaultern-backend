<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Family;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\FamilyMedication */
class FamilyMedicationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'family_id' => $this->family_id,
            'name' => $this->name,
            'dose_text' => $this->dose_text,
            'schedule_times' => $this->schedule_times ?? [],
            'active' => (bool) $this->active,
            'notes' => $this->notes,
            'patient_user_id' => $this->patient_user_id,
            'patient' => $this->whenLoaded('patient', fn () => [
                'id' => $this->patient?->id,
                'name' => $this->patient?->name,
            ]),
            'created_by' => $this->created_by,
            'last_taken_at' => $this->whenLoaded(
                'logs',
                fn () => $this->logs->sortByDesc('taken_at')->first()?->taken_at?->toIso8601String(),
            ),
        ];
    }
}
