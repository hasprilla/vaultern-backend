<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Profile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesUsers;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use AuthenticatesUsers;
    use RefreshDatabase;

    public function test_user_can_update_notification_preferences(): void
    {
        ['tokens' => $tokens] = $this->createUserWithFamily();

        $this->patchJson('/api/v1/profile/notifications', [
            'push_enabled' => false,
            'tasks'        => true,
        ], $this->authHeaders($tokens))
            ->assertOk()
            ->assertJsonPath('data.push_enabled', false)
            ->assertJsonPath('data.tasks', true)
            ->assertJsonPath('data.finance', true);
    }

    public function test_user_can_deactivate_account(): void
    {
        ['tokens' => $tokens, 'user' => $user] = $this->createUserWithFamily();

        $this->postJson('/api/v1/profile/account/deactivate', [
            'password' => 'password',
        ], $this->authHeaders($tokens))
            ->assertOk();

        $user->refresh();
        $this->assertSame('deactivated', $user->account_status);

        $this->getJson('/api/v1/auth/me', $this->authHeaders($tokens))
            ->assertStatus(401);
    }

    public function test_user_can_reactivate_deactivated_account(): void
    {
        $user = User::factory()->create([
            'email'          => 'reactivate@zumifly.app',
            'password'       => 'password',
            'account_status' => 'deactivated',
            'deactivated_at' => now(),
            'email_verified_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/account/reactivate', [
            'email'    => $user->email,
            'password' => 'password',
        ])->assertOk()
            ->assertJsonStructure(['data' => ['access_token', 'refresh_token', 'user']]);

        $this->assertSame('active', $user->fresh()->account_status);
    }

    public function test_user_can_delete_account(): void
    {
        ['tokens' => $tokens, 'user' => $user] = $this->createUserWithFamily();

        $this->deleteJson('/api/v1/profile/account', [
            'password' => 'password',
        ], $this->authHeaders($tokens))
            ->assertOk();

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }
}
