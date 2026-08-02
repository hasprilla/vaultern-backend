<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Auth;

use App\Infrastructure\Auth\TokenService;
use App\Models\Device;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\AuthenticatesUsers;
use Tests\TestCase;

class DeviceRecoveryApiTest extends TestCase
{
    use AuthenticatesUsers;
    use RefreshDatabase;

    public function test_user_must_setup_device_recovery_after_login(): void
    {
        ['user' => $user, 'tokens' => $tokens] = $this->createUserWithFamily([
            'email' => 'setup.device@yopmail.com',
            'email_verified_at' => now(),
            'device_secret_hash' => null,
            'security_question_key' => null,
            'security_answer_hash' => null,
        ]);

        // Quitar recuperación para forzar setup.
        $user->forceFill([
            'device_secret_hash' => null,
            'security_question_key' => null,
            'security_answer_hash' => null,
            'device_secret_must_rotate' => false,
        ])->save();

        // Setup inicial ya no bloquea la API; /me indica que falta configurar.
        $this->getJson('/api/v1/families', $this->authHeaders($tokens))->assertOk();
        $this->getJson('/api/v1/auth/me', $this->authHeaders($tokens))
            ->assertOk()
            ->assertJsonPath('data.device_recovery_setup_required', true);

        $this->postJson('/api/v1/auth/device/recovery', [
            'secret' => 'clave-secreta-1',
            'security_question_key' => 'pet_name',
            'security_answer' => 'Firulais',
        ], $this->authHeaders($tokens))
            ->assertOk();

        $user->refresh();
        $this->assertTrue($user->hasDeviceRecoveryConfigured());

        $this->getJson('/api/v1/auth/me', $this->authHeaders($tokens))
            ->assertOk()
            ->assertJsonPath('data.device_recovery_setup_required', false);
        $this->getJson('/api/v1/families', $this->authHeaders($tokens))->assertOk();
    }

    public function test_new_device_requires_secret_or_security_answer(): void
    {
        ['user' => $user, 'family' => $family] = $this->createUserWithFamily([
            'email' => 'device.change@yopmail.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);

        $user->forceFill([
            'device_secret_hash' => Hash::make('mi-clave-segura'),
            'security_question_key' => 'city',
            'security_answer_hash' => Hash::make('bogota'),
            'device_secret_must_rotate' => false,
        ])->save();

        Device::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'device_fingerprint' => 'device-old',
            'platform' => 'android',
            'is_trusted' => true,
            'last_seen_at' => now(),
        ]);
        $user->update(['device_fingerprint' => 'device-old']);

        $challenge = $this->postJson('/api/v1/auth/login', [
            'email' => 'device.change@yopmail.com',
            'password' => 'password',
            'device_id' => 'device-new',
            'platform' => 'android',
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'requires_device_verification')
            ->json('data');

        $this->assertNotEmpty($challenge['challenge_token']);
        $this->assertSame('city', $challenge['security_question_key']);

        $session = $this->postJson('/api/v1/auth/device/verify', [
            'user_id' => $user->id,
            'challenge_token' => $challenge['challenge_token'],
            'device_id' => 'device-new',
            'platform' => 'android',
            'secret' => 'mi-clave-segura',
        ])->assertOk();

        $this->assertNotEmpty($session->json('data.access_token'));
        $user->refresh();
        $this->assertTrue($user->device_secret_must_rotate);
        $this->assertDatabaseHas('devices', [
            'user_id' => $user->id,
            'device_fingerprint' => 'device-new',
            'is_trusted' => true,
        ]);
        $this->assertDatabaseHas('devices', [
            'user_id' => $user->id,
            'device_fingerprint' => 'device-old',
            'is_trusted' => false,
        ]);
    }

    public function test_security_answer_can_unlock_new_device(): void
    {
        ['user' => $user] = $this->createUserWithFamily([
            'email' => 'device.answer@yopmail.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);

        $user->forceFill([
            'device_secret_hash' => Hash::make('otra-clave'),
            'security_question_key' => 'school',
            'security_answer_hash' => Hash::make('san jose'),
            'device_secret_must_rotate' => false,
        ])->save();

        Device::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'device_fingerprint' => 'phone-a',
            'is_trusted' => true,
            'last_seen_at' => now(),
        ]);

        $challenge = $this->postJson('/api/v1/auth/login', [
            'email' => 'device.answer@yopmail.com',
            'password' => 'password',
            'device_id' => 'phone-b',
        ])->assertForbidden()->json('data');

        $this->postJson('/api/v1/auth/device/verify', [
            'user_id' => $user->id,
            'challenge_token' => $challenge['challenge_token'],
            'device_id' => 'phone-b',
            'security_answer' => 'San Jose',
        ])->assertOk();
    }
}
