<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => (string) $this->id,
            'name'            => $this->name,
            'email'           => $this->email,
            'role'            => $this->role,
            'family_id'       => $this->family_id,
            'avatar'          => $this->avatar,
            'mfa_enabled'     => (bool) $this->mfa_enabled,
            'email_verified'  => $this->email_verified_at !== null,
            'account_status'  => $this->account_status ?? 'active',
            'deactivated_at'  => $this->deactivated_at?->toIso8601String(),
            'notification_preferences' => $this->resolvedNotificationPreferences(),
            'created_at'      => $this->created_at?->toIso8601String(),
        ];
    }
}
