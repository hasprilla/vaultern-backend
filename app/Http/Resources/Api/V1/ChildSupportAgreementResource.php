<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ChildSupportAgreement */
class ChildSupportAgreementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'family_id' => (string) $this->family_id,
            'child_id' => (string) $this->child_id,
            'payer_user_id' => (string) $this->payer_user_id,
            'beneficiary_user_id' => (string) $this->beneficiary_user_id,
            'initial_amount' => (float) $this->initial_amount,
            'current_amount' => $this->currentAmount(),
            'currency' => $this->currency,
            'default_annual_increase_pct' => (float) $this->default_annual_increase_pct,
            'starts_on' => $this->starts_on?->toDateString(),
            'status' => $this->status,
            'notes' => $this->notes,
            'child' => $this->whenLoaded('child', fn () => [
                'id' => (string) $this->child->id,
                'name' => $this->child->name,
            ]),
            'payer' => $this->whenLoaded('payer', fn () => [
                'id' => (string) $this->payer->id,
                'name' => $this->payer->name,
            ]),
            'beneficiary' => $this->whenLoaded('beneficiary', fn () => [
                'id' => (string) $this->beneficiary->id,
                'name' => $this->beneficiary->name,
            ]),
            'adjustments' => $this->whenLoaded('adjustments', fn () => $this->adjustments->map(fn ($a) => [
                'id' => (string) $a->id,
                'increase_pct' => (float) $a->increase_pct,
                'amount_after' => (float) $a->amount_after,
                'effective_on' => $a->effective_on?->toDateString(),
                'notes' => $a->notes,
            ])->values()),
            'payments' => $this->whenLoaded('payments', fn () => ChildSupportPaymentResource::collection($this->payments)),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
