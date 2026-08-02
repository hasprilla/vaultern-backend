<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Family;

use App\Infrastructure\Auth\TokenService;
use App\Models\FamilyJoinRequest;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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

    public function test_register_child_requires_mother_when_mothers_exist(): void
    {
        ['user' => $owner, 'family' => $family, 'tokens' => $tokens] = $this->createUserWithFamily();

        $mother = User::factory()->create([
            'family_id' => $family->id,
            'role' => 'madre',
            'email' => 'madre.custodia@yopmail.com',
        ]);
        FamilyMember::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $family->id,
            'user_id' => $mother->id,
            'role' => 'madre',
            'status' => 'active',
        ]);

        $this->postJson("/api/v1/families/{$family->id}/children", [
            'name' => 'Sin mamá asignada',
        ], $this->authHeaders($tokens))
            ->assertStatus(422)
            ->assertJsonPath('code', 'mother_required');

        $this->postJson("/api/v1/families/{$family->id}/children", [
            'name' => 'Con mamá',
            'guardian_ids' => [(int) $mother->id],
        ], $this->authHeaders($tokens))
            ->assertCreated()
            ->assertJsonPath('data.name', 'Con mamá');
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
            'device_secret_hash' => Hash::make('test-device-secret'),
            'security_question_key' => 'pet_name',
            'security_answer_hash' => Hash::make('firulais'),
            'device_secret_must_rotate' => false,
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

        // Tokens revocados solo porque este era su núcleo actual.
        $this->getJson('/api/v1/families', $this->authHeaders($partnerTokens))
            ->assertUnauthorized();

        // La cuenta sigue activa: puede iniciar sesión (desactivar núcleo ≠ desactivar cuenta).
        // Sin otro núcleo activo, el tenant de familia responde membership inactive.
        $login = $this->postJson('/api/v1/auth/login', [
            'email' => 'madre.desactivar@yopmail.com',
            'password' => 'password',
        ])->assertOk();

        $newToken = $login->json('data.access_token');
        $this->assertNotEmpty($newToken);

        $this->getJson('/api/v1/families', $this->authHeaders([
            'access_token' => $newToken,
        ]))
            ->assertForbidden()
            ->assertJsonPath('code', 'family_membership_inactive');

        $this->assertDatabaseHas('users', [
            'id' => $partner->id,
            'account_status' => 'active',
        ]);

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

    public function test_owner_can_deactivate_tutor_and_child(): void
    {
        ['user' => $owner, 'family' => $family, 'tokens' => $ownerTokens] = $this->createUserWithFamily();

        $tutor = User::factory()->create([
            'family_id' => $family->id,
            'role' => 'tutor',
            'email' => 'tutor.desactivar@yopmail.com',
        ]);
        FamilyMember::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $family->id,
            'user_id' => $tutor->id,
            'role' => 'tutor',
            'status' => 'active',
        ]);

        $child = User::factory()->create([
            'family_id' => $family->id,
            'role' => 'hijo',
            'email' => 'hijo.desactivar@yopmail.com',
        ]);
        FamilyMember::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $family->id,
            'user_id' => $child->id,
            'role' => 'hijo',
            'status' => 'active',
        ]);

        $this->postJson(
            "/api/v1/families/{$family->id}/members/{$tutor->id}/deactivate",
            [],
            $this->authHeaders($ownerTokens),
        )
            ->assertOk()
            ->assertJsonPath('data.membership_status', 'inactive');

        $this->postJson(
            "/api/v1/families/{$family->id}/members/{$child->id}/deactivate",
            [],
            $this->authHeaders($ownerTokens),
        )
            ->assertOk()
            ->assertJsonPath('data.membership_status', 'inactive');

        // Tutor desactivado en este núcleo: cuenta activa, login permitido.
        $this->postJson('/api/v1/auth/login', [
            'email' => 'tutor.desactivar@yopmail.com',
            'password' => 'password',
        ])->assertOk();
    }

    public function test_deactivate_in_one_family_keeps_other_family_access(): void
    {
        ['user' => $ownerA, 'family' => $familyA, 'tokens' => $ownerATokens] = $this->createUserWithFamily();
        ['user' => $ownerB, 'family' => $familyB] = $this->createUserWithFamily([
            'email' => 'owner.b@yopmail.com',
        ]);

        $shared = User::factory()->create([
            'family_id' => $familyA->id,
            'role' => 'madre',
            'email' => 'madre.multi@yopmail.com',
            'account_status' => 'active',
        ]);

        FamilyMember::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $familyA->id,
            'user_id' => $shared->id,
            'role' => 'madre',
            'status' => 'active',
        ]);
        FamilyMember::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $familyB->id,
            'user_id' => $shared->id,
            'role' => 'tutor',
            'status' => 'active',
        ]);

        $this->postJson(
            "/api/v1/families/{$familyA->id}/members/{$shared->id}/deactivate",
            [],
            $this->authHeaders($ownerATokens),
        )->assertOk();

        $this->assertDatabaseHas('family_members', [
            'user_id' => $shared->id,
            'family_id' => $familyA->id,
            'status' => 'inactive',
        ]);
        $this->assertDatabaseHas('family_members', [
            'user_id' => $shared->id,
            'family_id' => $familyB->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $shared->id,
            'account_status' => 'active',
            'family_id' => $familyB->id,
            'role' => 'tutor',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'madre.multi@yopmail.com',
            'password' => 'password',
        ])->assertOk();
    }
}
