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
        $payload = [
            'id'              => (string) $this->id,
            'name'            => $this->name,
            'email'           => $this->email,
            'role'            => $this->role,
            'family_id'       => $this->family_id,
            'avatar'          => $this->resolveAvatarUrl($request),
            'mfa_enabled'     => (bool) $this->mfa_enabled,
            'email_verified'  => $this->email_verified_at !== null,
            'account_status'  => $this->account_status ?? 'active',
            'deactivated_at'  => $this->deactivated_at?->toIso8601String(),
            'device_recovery_configured' => $this->hasDeviceRecoveryConfigured(),
            'device_recovery_setup_required' => $this->mustSetupDeviceRecovery(),
            'device_secret_must_rotate' => $this->mustRotateDeviceSecret(),
            'notification_preferences' => $this->resolvedNotificationPreferences(),
            'created_at'      => $this->created_at?->toIso8601String(),
        ];

        if ($this->role === 'hijo' && $this->relationLoaded('guardians')) {
            $payload['guardian_ids'] = $this->guardians
                ->pluck('parent_user_id')
                ->map(fn ($id) => (string) $id)
                ->values()
                ->all();
        }

        return $payload;
    }

    private function resolveAvatarUrl(Request $request): ?string
    {
        $avatar = $this->avatar;
        if ($avatar === null || $avatar === '') {
            return null;
        }

        if (str_starts_with($avatar, 'http://') || str_starts_with($avatar, 'https://')) {
            return $avatar;
        }

        return $request->getSchemeAndHttpHost().'/storage/'.ltrim($avatar, '/');
    }
}
