<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Str;

class DeviceRegistrationService
{
    public function register(User $user, string $fingerprint, ?string $platform = null, ?string $fcmToken = null): Device
    {
        $user->update(['device_fingerprint' => $fingerprint]);

        $device = Device::query()->firstOrNew([
            'user_id'            => $user->id,
            'device_fingerprint' => $fingerprint,
        ]);

        if (! $device->exists) {
            $device->id = (string) Str::uuid();
        }

        $attributes = [
            'platform'     => $platform,
            'is_trusted'   => true,
            'last_seen_at' => now(),
        ];

        if ($fcmToken !== null && $fcmToken !== '') {
            $attributes['fcm_token'] = $fcmToken;
        }

        $device->fill($attributes);
        $device->save();

        return $device;
    }
}
