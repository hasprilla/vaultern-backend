<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Task;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesUsers;
use Tests\TestCase;

class TaskApiTest extends TestCase
{
    use AuthenticatesUsers;
    use RefreshDatabase;

    public function test_tasks_require_authentication(): void
    {
        $this->getJson('/api/v1/tasks')->assertStatus(401);
    }

    public function test_authenticated_user_can_create_task(): void
    {
        ['tokens' => $tokens] = $this->createUserWithFamily();

        $this->postJson('/api/v1/tasks', [
            'title'    => 'Tarea de matemáticas',
            'priority' => 'alta',
            'is_school'=> true,
            'subject'  => 'Matemáticas',
        ], $this->authHeaders($tokens))
            ->assertCreated()
            ->assertJsonPath('data.title', 'Tarea de matemáticas');
    }

    public function test_authenticated_user_can_list_tasks(): void
    {
        ['tokens' => $tokens] = $this->createUserWithFamily();

        $this->postJson('/api/v1/tasks', ['title' => 'Tarea 1'], $this->authHeaders($tokens));

        $this->getJson('/api/v1/tasks', $this->authHeaders($tokens))
            ->assertOk()
            ->assertJsonStructure(['data']);
    }
}
