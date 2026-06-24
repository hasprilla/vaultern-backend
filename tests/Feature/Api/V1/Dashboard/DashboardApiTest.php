<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Dashboard;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesUsers;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use AuthenticatesUsers;
    use RefreshDatabase;

    public function test_dashboard_requires_authentication(): void
    {
        $this->getJson('/api/v1/dashboard/analytics')->assertStatus(401);
    }

    public function test_authenticated_user_can_get_analytics(): void
    {
        ['tokens' => $tokens] = $this->createUserWithFamily();

        $this->getJson('/api/v1/dashboard/analytics?period=weekly', $this->authHeaders($tokens))
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['period', 'tasks_total', 'tasks_done', 'tasks_overdue', 'completion_rate'],
            ]);
    }
}
