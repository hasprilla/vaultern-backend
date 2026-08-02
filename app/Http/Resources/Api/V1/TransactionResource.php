<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Transaction */
class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'family_id' => $this->family_id,
            'user_id' => $this->user_id !== null ? (string) $this->user_id : null,
            'child_id' => $this->child_id !== null ? (string) $this->child_id : null,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'type' => $this->type,
            'category' => $this->category,
            'description' => $this->description,
            'transaction_date' => $this->transaction_date?->toDateString(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'child' => $this->whenLoaded('child', fn () => $this->child === null ? null : [
                'id' => (string) $this->child->id,
                'name' => $this->child->name,
            ]),
        ];
    }
}
