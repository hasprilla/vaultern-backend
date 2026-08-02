<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Attachment */
class AttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'url' => $this->url($request),
            'mime_type' => $this->mime_type,
            'original_name' => $this->original_name,
            'size' => (int) $this->size,
            'kind' => $this->kind,
            'is_image' => $this->isImage(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
