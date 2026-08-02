<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Str;

class DeviceRegistrationService
{
    public function isTrustedDevice(User $user, string $fingerprint): bool
    {
        return Device::query()
            ->where('user_id', $user->id)
            ->where('device_fingerprint', $fingerprint)
            ->where('is_trusted', true)
            ->exists();
    }

    public function hasAnyTrustedDevice(User $user): bool
    {
        return Device::query()
            ->where('user_id', $user->id)
            ->where('is_trusted', true)
            ->exists();
    }

    /** Registra o actualiza un dispositivo como de confianza. */
    public function registerTrusted(
        User $user,
        string $fingerprint,
        ?string $platform = null,
        ?string $fcmToken = null,
    ): Device {
        return $this->upsert($user, $fingerprint, true, $platform, $fcmToken);
    }

    /**
     * Compat: registro de dispositivo (trusted). Preferir registerTrusted.
     *
     * @deprecated Use registerTrusted()
     */
    public function register(User $user, string $fingerprint, ?string $platform = null, ?string $fcmToken = null): Device
    {
        return $this->registerTrusted($user, $fingerprint, $platform, $fcmToken);
    }

    /** Tras cambio de dispositivo: deja de confiar en los demás. */
    public function revokeOtherTrustedDevices(User $user, string $keepFingerprint): void
    {
        Device::query()
            ->where('user_id', $user->id)
            ->where('device_fingerprint', '!=', $keepFingerprint)
            ->where('is_trusted', true)
            ->update(['is_trusted' => false]);
    }

    private function upsert(
        User $user,
        string $fingerprint,
        bool $trusted,
        ?string $platform,
        ?string $fcmToken,
    ): Device {
        $user->update(['device_fingerprint' => $fingerprint]);

        $device = Device::query()->firstOrNew([
            'user_id' => $user->id,
            'device_fingerprint' => $fingerprint,
        ]);

        if (! $device->exists) {
            $device->id = (string) Str::uuid();
        }

        $attributes = [
            'platform' => $platform,
            'is_trusted' => $trusted,
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
