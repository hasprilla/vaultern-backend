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

    public function test_authenticated_user_can_view_task_detail(): void
    {
        ['tokens' => $tokens] = $this->createUserWithFamily();

        $create = $this->postJson('/api/v1/tasks', [
            'title'     => 'Tarea detalle',
            'priority'  => 'alta',
            'is_school' => true,
            'subject'   => 'Matemáticas',
        ], $this->authHeaders($tokens))->assertCreated();

        $taskId = $create->json('data.id');

        $this->getJson("/api/v1/tasks/{$taskId}", $this->authHeaders($tokens))
            ->assertOk()
            ->assertJsonPath('data.id', $taskId)
            ->assertJsonPath('data.title', 'Tarea detalle')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'title',
                    'status',
                    'priority',
                    'is_school',
                    'creator',
                    'assignee',
                ],
            ]);
    }

    public function test_authenticated_user_can_filter_overdue_tasks(): void
    {
        ['tokens' => $tokens] = $this->createUserWithFamily();

        $this->postJson('/api/v1/tasks', [
            'title'    => 'Tarea vencida por fecha',
            'due_date' => now()->subDay()->toDateString(),
        ], $this->authHeaders($tokens))->assertCreated();

        $create = $this->postJson('/api/v1/tasks', [
            'title' => 'Tarea con estado overdue',
        ], $this->authHeaders($tokens))->assertCreated();

        $this->patchJson(
            '/api/v1/tasks/'.$create->json('data.id'),
            ['status' => 'overdue'],
            $this->authHeaders($tokens),
        )->assertOk();

        $this->postJson('/api/v1/tasks', [
            'title'    => 'Tarea al día',
            'due_date' => now()->addDay()->toDateString(),
        ], $this->authHeaders($tokens))->assertCreated();

        $response = $this->getJson('/api/v1/tasks?status=overdue', $this->authHeaders($tokens))
            ->assertOk();

        $this->assertGreaterThanOrEqual(2, count($response->json('data')));
    }

    public function test_pending_filter_excludes_overdue_by_due_date(): void
    {
        ['tokens' => $tokens] = $this->createUserWithFamily();

        $this->postJson('/api/v1/tasks', [
            'title'    => 'Pendiente vencida',
            'due_date' => now()->subDay()->toDateString(),
        ], $this->authHeaders($tokens))->assertCreated();

        $this->postJson('/api/v1/tasks', [
            'title'    => 'Pendiente al día',
            'due_date' => now()->addDay()->toDateString(),
        ], $this->authHeaders($tokens))->assertCreated();

        $this->postJson('/api/v1/tasks', [
            'title' => 'Pendiente sin fecha',
        ], $this->authHeaders($tokens))->assertCreated();

        $response = $this->getJson('/api/v1/tasks?status=pending', $this->authHeaders($tokens))
            ->assertOk();

        $titles = collect($response->json('data'))->pluck('title')->all();

        $this->assertContains('Pendiente al día', $titles);
        $this->assertContains('Pendiente sin fecha', $titles);
        $this->assertNotContains('Pendiente vencida', $titles);
    }
}
