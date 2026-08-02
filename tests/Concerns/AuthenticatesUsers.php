<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Infrastructure\Auth\TokenService;
use App\Models\Device;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

trait AuthenticatesUsers
{
    protected function createUserWithFamily(array $userAttrs = [], array $familyAttrs = []): array
    {
        $family = Family::query()->create([
            'id'   => (string) Str::uuid(),
            'name' => 'Familia Test',
            'plan' => 'free',
            ...$familyAttrs,
        ]);

        $skipDeviceRecovery = (bool) ($userAttrs['skip_device_recovery'] ?? false);
        unset($userAttrs['skip_device_recovery']);

        $user = User::factory()->create([
            'family_id' => $family->id,
            'role'      => 'padre',
            ...$userAttrs,
        ]);

        if ($family->owner_user_id === null) {
            $family->update(['owner_user_id' => $user->id]);
            $family->refresh();
        }

        FamilyMember::query()->create([
            'id'        => (string) Str::uuid(),
            'family_id' => $family->id,
            'user_id'   => $user->id,
            'role'      => $user->role,
            'status'    => 'active',
        ]);

        // Por defecto con recuperación de dispositivo lista (salvo tests que la omitan).
        if (! $skipDeviceRecovery && ! $user->hasDeviceRecoveryConfigured()) {
            $user->forceFill([
                'device_secret_hash' => Hash::make('test-device-secret'),
                'security_question_key' => 'pet_name',
                'security_answer_hash' => Hash::make('firulais'),
                'device_secret_must_rotate' => false,
            ])->save();
        }

        $fingerprint = $user->device_fingerprint ?: 'test-device-'.((string) $user->id);
        if ($user->device_fingerprint === null) {
            $user->forceFill(['device_fingerprint' => $fingerprint])->save();
        }
        Device::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'device_fingerprint' => $fingerprint,
            ],
            [
                'id' => (string) Str::uuid(),
                'platform' => 'android',
                'is_trusted' => true,
                'last_seen_at' => now(),
            ],
        );

        $tokens = app(TokenService::class)->issue($user->fresh());

        return compact('user', 'family', 'tokens');
    }

    protected function authHeaders(array $tokens): array
    {
        return ['Authorization' => 'Bearer '.$tokens['access_token']];
    }
}
