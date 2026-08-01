<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Family;

use App\Infrastructure\Auth\TokenService;
use App\Models\FamilyJoinRequest;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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
            'role' => 'hijo',
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
            'name' => 'Lucía Test',
            'email' => 'lucia@zumifly.app',
            'password' => 'SecurePass123!',
        ], $this->authHeaders($tokens))
            ->assertCreated()
            ->assertJsonPath('data.role', 'hijo')
            ->assertJsonPath('data.name', 'Lucía Test');
    }

    public function test_parent_can_approve_pending_join_request(): void
    {
        ['user' => $parent, 'family' => $family, 'tokens' => $tokens] = $this->createUserWithFamily();

        $joinRequest = FamilyJoinRequest::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $family->id,
            'invited_by_user_id' => $parent->id,
            'name' => 'Pareja Demo',
            'email' => 'pareja.aprobada@yopmail.com',
            'password' => 'SecurePass123!',
            'role' => 'madre',
            'status' => 'pending',
        ]);

        $this->postJson(
            "/api/v1/families/{$family->id}/join-requests/{$joinRequest->id}/approve",
            [],
            $this->authHeaders($tokens),
        )
            ->assertOk()
            ->assertJsonPath('data.email', 'pareja.aprobada@yopmail.com');

        $this->assertDatabaseHas('users', [
            'email' => 'pareja.aprobada@yopmail.com',
            'family_id' => $family->id,
            'role' => 'madre',
        ]);

        $this->assertDatabaseHas('family_join_requests', [
            'id' => $joinRequest->id,
            'status' => 'approved',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'pareja.aprobada@yopmail.com',
            'password' => 'SecurePass123!',
        ])->assertOk();
    }

    public function test_partner_parent_can_approve_join_request_invited_by_other_parent(): void
    {
        ['user' => $parent, 'family' => $family, 'tokens' => $parentTokens] = $this->createUserWithFamily();

        $partner = User::factory()->create([
            'family_id' => $family->id,
            'role' => 'madre',
            'email' => 'madre@yopmail.com',
        ]);

        FamilyMember::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $family->id,
            'user_id' => $partner->id,
            'role' => 'madre',
            'status' => 'active',
        ]);

        $partnerTokens = app(TokenService::class)->issue($partner);

        $joinRequest = FamilyJoinRequest::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $family->id,
            'invited_by_user_id' => $parent->id,
            'name' => 'Tutor Demo',
            'email' => 'tutor@yopmail.com',
            'password' => 'SecurePass123!',
            'role' => 'tutor',
            'status' => 'pending',
        ]);

        $this->postJson(
            "/api/v1/families/{$family->id}/join-requests/{$joinRequest->id}/approve",
            [],
            $this->authHeaders($partnerTokens),
        )->assertOk();
    }

    public function test_owner_can_deactivate_and_reactivate_parent_without_deleting_data(): void
    {
        ['user' => $owner, 'family' => $family, 'tokens' => $ownerTokens] = $this->createUserWithFamily();

        $partner = User::factory()->create([
            'family_id' => $family->id,
            'role' => 'madre',
            'email' => 'madre.desactivar@yopmail.com',
            'name' => 'Madre Activa',
        ]);

        FamilyMember::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $family->id,
            'user_id' => $partner->id,
            'role' => 'madre',
            'status' => 'active',
        ]);

        $partnerTokens = app(TokenService::class)->issue($partner);

        $this->postJson(
            "/api/v1/families/{$family->id}/members/{$partner->id}/deactivate",
            [],
            $this->authHeaders($ownerTokens),
        )
            ->assertOk()
            ->assertJsonPath('data.membership_status', 'inactive');

        $this->assertDatabaseHas('family_members', [
            'user_id' => $partner->id,
            'family_id' => $family->id,
            'status' => 'inactive',
        ]);

        // Datos del usuario se conservan.
        $this->assertDatabaseHas('users', [
            'id' => $partner->id,
            'email' => 'madre.desactivar@yopmail.com',
            'name' => 'Madre Activa',
            'family_id' => $family->id,
            'role' => 'madre',
            'account_status' => 'active',
        ]);

        // Tokens revocados: no puede seguir usando la sesión.
        $this->getJson('/api/v1/families', $this->authHeaders($partnerTokens))
            ->assertUnauthorized();

        // Login bloqueado mientras la membresía esté inactiva.
        $this->postJson('/api/v1/auth/login', [
            'email' => 'madre.desactivar@yopmail.com',
            'password' => 'password',
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'family_membership_inactive');

        // El dueño sigue viendo al miembro inactivo.
        $this->getJson('/api/v1/families', $this->authHeaders($ownerTokens))
            ->assertOk()
            ->assertJsonFragment([
                'email' => 'madre.desactivar@yopmail.com',
                'membership_status' => 'inactive',
            ]);

        $this->postJson(
            "/api/v1/families/{$family->id}/members/{$partner->id}/reactivate",
            [],
            $this->authHeaders($ownerTokens),
        )
            ->assertOk()
            ->assertJsonPath('data.membership_status', 'active');

        $this->assertDatabaseHas('family_members', [
            'user_id' => $partner->id,
            'status' => 'active',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'madre.desactivar@yopmail.com',
            'password' => 'password',
        ])->assertOk();
    }

    public function test_non_owner_cannot_deactivate_parent(): void
    {
        ['user' => $owner, 'family' => $family] = $this->createUserWithFamily();

        $partner = User::factory()->create([
            'family_id' => $family->id,
            'role' => 'madre',
            'email' => 'madre.noowner@yopmail.com',
        ]);

        FamilyMember::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $family->id,
            'user_id' => $partner->id,
            'role' => 'madre',
            'status' => 'active',
        ]);

        $partnerTokens = app(TokenService::class)->issue($partner);

        $this->postJson(
            "/api/v1/families/{$family->id}/members/{$owner->id}/deactivate",
            [],
            $this->authHeaders($partnerTokens),
        )->assertForbidden();
    }
}
