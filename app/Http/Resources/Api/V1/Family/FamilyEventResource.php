<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Family;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\FamilyEvent */
class FamilyEventResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'family_id' => $this->family_id,
            'title' => $this->title,
            'description' => $this->description,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'location' => $this->location,
            'status' => $this->status,
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator?->id,
                'name' => $this->creator?->name,
            ]),
            'guests' => FamilyEventGuestResource::collection($this->whenLoaded('guests')),
            'rsvp_counts' => $this->when($this->relationLoaded('guests'), function () {
                $all = $this->guests;

                return [
                    'pending' => $all->where('status', 'pending')->count(),
                    'attending' => $all->where('status', 'attending')->count(),
                    'declined' => $all->where('status', 'declined')->count(),
                    'maybe' => $all->where('status', 'maybe')->count(),
                    'total' => $all->count(),
                ];
            }),
        ];
    }
}
