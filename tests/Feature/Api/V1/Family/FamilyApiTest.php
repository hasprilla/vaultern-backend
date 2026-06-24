<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Family;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AuthenticatesUsers;
use Tests\TestCase;

class FamilyApiTest extends TestCase
{
    use AuthenticatesUsers;
    use RefreshDatabase;

    public function test_family_index_requires_authentication(): void
    {
        $this->getJson('/api/v1/families')->assertStatus(401);
    }

    public function test_create_family_requires_authentication(): void
    {
        $this->postJson('/api/v1/families', [
            'name' => 'Familia Nueva',
        ])->assertStatus(401);
    }

    public function test_invite_member_requires_authentication(): void
    {
        $this->postJson('/api/v1/families/fam-1/invite', [
            'email' => 'test@test.com',
            'role'  => 'hijo',
        ])->assertStatus(401);
    }

    public function test_authenticated_user_can_list_family(): void
    {
        ['user' => $user, 'family' => $family, 'tokens' => $tokens] = $this->createUserWithFamily();

        $this->getJson('/api/v1/families', $this->authHeaders($tokens))
            ->assertOk()
            ->assertJsonPath('data.id', $family->id);
    }

    public function test_authenticated_parent_can_register_child(): void
    {
        ['user' => $user, 'family' => $family, 'tokens' => $tokens] = $this->createUserWithFamily();

        $this->postJson("/api/v1/families/{$family->id}/children", [
            'name'     => 'Lucía Test',
            'email'    => 'lucia@zumifly.app',
            'password' => 'SecurePass123!',
        ], $this->authHeaders($tokens))
            ->assertCreated()
            ->assertJsonPath('data.role', 'hijo')
            ->assertJsonPath('data.name', 'Lucía Test');
    }
}
