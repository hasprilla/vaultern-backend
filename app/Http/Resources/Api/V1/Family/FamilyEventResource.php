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
            'kind' => $this->kind ?? 'general',
            'child_user_id' => $this->child_user_id,
            'budget_amount' => $this->budget_amount !== null ? (float) $this->budget_amount : null,
            'currency' => $this->currency ?? 'COP',
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator?->id,
                'name' => $this->creator?->name,
            ]),
            'child' => $this->whenLoaded('child', fn () => [
                'id' => $this->child?->id,
                'name' => $this->child?->name,
            ]),
            'guests' => FamilyEventGuestResource::collection($this->whenLoaded('guests')),
            'expenses' => FamilyEventExpenseResource::collection($this->whenLoaded('expenses')),
            'expenses_total' => $this->when(
                $this->relationLoaded('expenses'),
                fn () => (float) $this->expenses->sum('amount'),
            ),
            'rsvp_counts' => $this->when($this->relationLoaded('guests'), function () {
                $all = $this->guests;

                return [
                    'pending' => $all->where('status', 'pending')->count(),
                    'attending' => $all->where('status', 'attending')->count(),
                    'declined' => $all->where('status', 'declined')->count(),
                    'maybe' => $all->where('status', 'maybe')->count(),
                    'total' => $all->count(),
                    'adults' => $all->where('guest_kind', 'adult')->count(),
                    'children' => $all->where('guest_kind', 'child')->count(),
                ];
            }),
        ];
    }
}
