<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ChildSupportPayment */
class ChildSupportPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'agreement_id' => (string) $this->agreement_id,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'period_month' => $this->period_month?->toDateString(),
            'paid_on' => $this->paid_on?->toDateString(),
            'notes' => $this->notes,
            'transaction_id' => $this->transaction_id !== null ? (string) $this->transaction_id : null,
            'paid_by' => $this->paid_by !== null ? (string) $this->paid_by : null,
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
        ];
    }
}
