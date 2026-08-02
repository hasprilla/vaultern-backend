<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Task */
class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'family_id' => $this->family_id,
            'created_by' => $this->created_by !== null ? (string) $this->created_by : null,
            'assigned_to' => $this->assigned_to !== null ? (string) $this->assigned_to : null,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'is_school' => (bool) $this->is_school,
            'subject' => $this->subject,
            'due_date' => $this->due_date?->toDateString(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => (string) $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee === null ? null : [
                'id' => (string) $this->assignee->id,
                'name' => $this->assignee->name,
            ]),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
        ];
    }
}
