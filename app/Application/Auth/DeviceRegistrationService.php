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

        $device->fill([
            'platform'     => $platform,
            'fcm_token'    => $fcmToken,
            'is_trusted'   => true,
            'last_seen_at' => now(),
        ]);
        $device->save();

        return $device;
    }
}
