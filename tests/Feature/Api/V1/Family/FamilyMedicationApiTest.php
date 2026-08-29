<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Family;

use App\Models\FamilyMedication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\AuthenticatesUsers;
use Tests\TestCase;

class FamilyMedicationApiTest extends TestCase
{
    use AuthenticatesUsers;
    use RefreshDatabase;

    public function test_medications_require_authentication(): void
    {
        $this->getJson('/api/v1/medications')->assertStatus(401);
    }

    public function test_parent_can_create_list_taken_and_logs(): void
    {
        ['tokens' => $tokens, 'family' => $family] = $this->createUserWithFamily();

        $create = $this->postJson('/api/v1/medications', [
            'name' => 'Ibuprofeno',
            'dose_text' => '5 ml',
            'schedule_times' => ['08:00', '20:00'],
        ], $this->authHeaders($tokens))
            ->assertCreated()
            ->assertJsonPath('data.name', 'Ibuprofeno');

        $id = $create->json('data.id');
        $this->assertDatabaseHas('family_medications', [
            'id' => $id,
            'family_id' => $family->id,
        ]);

        $this->getJson('/api/v1/medications', $this->authHeaders($tokens))
            ->assertOk()
            ->assertJsonFragment(['id' => $id]);

        $this->postJson("/api/v1/medications/{$id}/taken", ['note' => 'AM'], $this->authHeaders($tokens))
            ->assertCreated();

        $this->getJson("/api/v1/medications/{$id}/logs", $this->authHeaders($tokens))
            ->assertOk()
            ->assertJsonFragment(['note' => 'AM']);

        $this->patchJson("/api/v1/medications/{$id}", ['active' => false], $this->authHeaders($tokens))
            ->assertOk()
            ->assertJsonPath('data.active', false);
    }

    public function test_cannot_access_other_family_medication(): void
    {
        ['user' => $userA, 'family' => $familyA] = $this->createUserWithFamily();
        ['tokens' => $tokensB] = $this->createUserWithFamily();

        $med = FamilyMedication::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $familyA->id,
            'created_by' => $userA->id,
            'name' => 'Privado',
            'active' => true,
        ]);

        $this->postJson("/api/v1/medications/{$med->id}/taken", [], $this->authHeaders($tokensB))
            ->assertNotFound();
    }
}
