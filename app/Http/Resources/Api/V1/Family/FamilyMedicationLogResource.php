<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Family;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\FamilyMedicationLog */
class FamilyMedicationLogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'medication_id' => $this->medication_id,
            'taken_by' => $this->taken_by,
            'taken_at' => $this->taken_at?->toIso8601String(),
            'note' => $this->note,
            'taker' => $this->whenLoaded('taker', fn () => [
                'id' => $this->taker?->id,
                'name' => $this->taker?->name,
            ]),
        ];
    }
}
