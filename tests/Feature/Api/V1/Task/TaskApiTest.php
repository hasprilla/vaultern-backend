<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Task;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_can_create_task_with_image_attachments_marked_as_images(): void
    {
        Storage::fake('public');
        ['tokens' => $tokens] = $this->createUserWithFamily();

        $this->post('/api/v1/tasks', [
            'title' => 'Tarea con fotos',
            'attachments' => [
                UploadedFile::fake()->image('foto1.jpg'),
                UploadedFile::fake()->image('foto2.png'),
            ],
        ], $this->authHeaders($tokens))
            ->assertCreated()
            ->assertJsonPath('data.attachments.0.is_image', true)
            ->assertJsonPath('data.attachments.1.is_image', true)
            ->assertJsonPath('data.attachments.0.kind', 'image');
    }

    public function test_owner_can_delete_attachment_only_while_pending(): void
    {
        Storage::fake('public');
        ['tokens' => $tokens] = $this->createUserWithFamily();

        $create = $this->post('/api/v1/tasks', [
            'title' => 'Tarea adjuntos',
            'attachments' => [UploadedFile::fake()->image('a.jpg')],
        ], $this->authHeaders($tokens))->assertCreated();

        $taskId = $create->json('data.id');
        $attachmentId = $create->json('data.attachments.0.id');

        $this->deleteJson(
            "/api/v1/tasks/{$taskId}/attachments/{$attachmentId}",
            [],
            $this->authHeaders($tokens),
        )->assertOk();

        $create2 = $this->post('/api/v1/tasks', [
            'title' => 'Tarea bloqueada',
            'attachments' => [UploadedFile::fake()->image('b.jpg')],
        ], $this->authHeaders($tokens))->assertCreated();

        $taskId2 = $create2->json('data.id');
        $attachmentId2 = $create2->json('data.attachments.0.id');

        $this->patchJson(
            "/api/v1/tasks/{$taskId2}",
            ['status' => 'in_progress'],
            $this->authHeaders($tokens),
        )->assertOk();

        $this->deleteJson(
            "/api/v1/tasks/{$taskId2}/attachments/{$attachmentId2}",
            [],
            $this->authHeaders($tokens),
        )
            ->assertStatus(422)
            ->assertJsonPath('code', 'task_attachments_locked');
    }
}
