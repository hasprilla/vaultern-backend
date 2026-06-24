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
