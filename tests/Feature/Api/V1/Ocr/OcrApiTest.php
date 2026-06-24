<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Ocr;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\AuthenticatesUsers;
use Tests\TestCase;

class OcrApiTest extends TestCase
{
    use AuthenticatesUsers;
    use RefreshDatabase;

    public function test_ocr_requires_authentication(): void
    {
        $this->postJson('/api/v1/ocr/document', [])->assertStatus(401);
    }

    public function test_authenticated_user_can_process_document(): void
    {
        ['tokens' => $tokens] = $this->createUserWithFamily();

        $this->postJson('/api/v1/ocr/document', [], $this->authHeaders($tokens))
            ->assertAccepted()
            ->assertJsonPath('data.type', 'document')
            ->assertJsonPath('data.status', 'done')
            ->assertJsonStructure(['data' => ['structured_data' => ['tasks']]]);
    }

    public function test_authenticated_user_can_process_notebook_with_image(): void
    {
        ['tokens' => $tokens] = $this->createUserWithFamily();

        $file = UploadedFile::fake()->image('cuaderno.jpg');

        $this->post('/api/v1/ocr/notebook', ['file' => $file], $this->authHeaders($tokens))
            ->assertAccepted()
            ->assertJsonPath('data.type', 'handwriting')
            ->assertJsonStructure(['data' => ['structured_data' => ['tasks']]]);
    }
}
