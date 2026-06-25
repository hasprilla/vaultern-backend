<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_ok(): void
    {
        $response = $this->getJson('/api/v1/health');
        $response->assertStatus(200)
                 ->assertJsonPath('status', 'ok')
                 ->assertJsonPath('app', 'Vaultern API v1');
    }

    public function test_register_requires_name(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'email'    => 'test@zumifly.app',
            'password' => 'SecurePass123!',
            'role'     => 'padre',
        ]);
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['name']);
    }

    public function test_register_rejects_hijo_role(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name'     => 'Niño Test',
            'email'    => 'hijo@zumifly.app',
            'password' => 'SecurePass123!',
            'role'     => 'hijo',
        ]);
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['role']);
    }

    public function test_join_creates_pending_request_for_approver(): void
    {
        $parent = $this->postJson('/api/v1/auth/register', [
            'name'     => 'Padre Test',
            'email'    => 'padre@zumifly.app',
            'password' => 'SecurePass123!',
            'role'     => 'padre',
        ])->assertCreated();

        $parentId = $parent->json('data.user.id');
        $familyId = $parent->json('data.user.family_id');
        $inviteCode = \App\Models\Family::query()->find($familyId)->invite_code;

        $this->postJson('/api/v1/auth/join', [
            'name'        => 'Madre Test',
            'email'       => 'madre@zumifly.app',
            'password'    => 'SecurePass123!',
            'invite_code' => $inviteCode,
            'invited_by'  => $parentId,
            'role'        => 'madre',
        ])
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('family_join_requests', [
            'email'  => 'madre@zumifly.app',
            'status' => 'pending',
        ]);
    }

    public function test_join_rejects_hijo_role(): void
    {
        $parent = $this->postJson('/api/v1/auth/register', [
            'name'     => 'Padre Test',
            'email'    => 'padre2@zumifly.app',
            'password' => 'SecurePass123!',
            'role'     => 'padre',
        ])->assertCreated();

        $familyId = $parent->json('data.user.family_id');
        $inviteCode = \App\Models\Family::query()->find($familyId)->invite_code;

        $this->postJson('/api/v1/auth/join', [
            'name'        => 'Hijo Test',
            'email'       => 'hijo@zumifly.app',
            'password'    => 'SecurePass123!',
            'invite_code' => $inviteCode,
            'role'        => 'hijo',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }

    public function test_register_requires_valid_role(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name'     => 'Harvey Asprilla',
            'email'    => 'test@zumifly.app',
            'password' => 'SecurePass123!',
            'role'     => 'admin',
        ]);
        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['role']);
    }

    public function test_login_returns_401_with_wrong_credentials(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'nobody@zumifly.app',
            'password' => 'WrongPassword!',
        ]);
        $response->assertStatus(401);
    }

    public function test_login_with_device_id_succeeds_after_register(): void
    {
        $email = 'login-device@zumifly.app';

        $this->postJson('/api/v1/auth/register', [
            'name'     => 'Device Test',
            'email'    => $email,
            'password' => 'SecurePass123!',
            'role'     => 'padre',
        ])->assertCreated();

        $this->postJson('/api/v1/auth/login', [
            'email'     => $email,
            'password'  => 'SecurePass123!',
            'device_id' => '550e8400-e29b-41d4-a716-446655440000',
            'platform'  => 'android',
        ])
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['access_token', 'refresh_token', 'user'],
            ]);

        $this->assertDatabaseHas('devices', [
            'device_fingerprint' => '550e8400-e29b-41d4-a716-446655440000',
            'platform'           => 'android',
            'is_trusted'         => true,
        ]);
    }

    public function test_me_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
    }

    public function test_tasks_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/v1/tasks')->assertStatus(401);
    }

    public function test_finance_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/v1/finance/reports/monthly')->assertStatus(401);
    }

    public function test_ocr_endpoint_requires_authentication(): void
    {
        $this->postJson('/api/v1/ocr/invoice', [])->assertStatus(401);
    }

    public function test_dashboard_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/v1/dashboard/analytics')->assertStatus(401);
    }
}
