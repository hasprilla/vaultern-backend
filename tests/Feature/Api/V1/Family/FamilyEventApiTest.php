<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Family;

use App\Models\FamilyEvent;
use App\Models\FamilyEventGuest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\AuthenticatesUsers;
use Tests\TestCase;

class FamilyEventApiTest extends TestCase
{
    use AuthenticatesUsers;
    use RefreshDatabase;

    public function test_events_require_authentication(): void
    {
        $this->getJson('/api/v1/events')->assertStatus(401);
        $this->postJson('/api/v1/events', ['title' => 'X', 'starts_at' => now()->toIso8601String()])
            ->assertStatus(401);
    }

    public function test_parent_can_create_list_and_show_event(): void
    {
        ['tokens' => $tokens, 'family' => $family] = $this->createUserWithFamily();

        $create = $this->postJson('/api/v1/events', [
            'title' => 'Cumpleaños Ana',
            'description' => 'En casa',
            'starts_at' => now()->addDay()->toIso8601String(),
            'location' => 'Salón',
        ], $this->authHeaders($tokens))
            ->assertCreated()
            ->assertJsonPath('data.title', 'Cumpleaños Ana')
            ->assertJsonPath('data.status', 'scheduled');

        $eventId = $create->json('data.id');
        $this->assertNotEmpty($eventId);
        $this->assertDatabaseHas('family_events', [
            'id' => $eventId,
            'family_id' => $family->id,
        ]);

        $this->getJson('/api/v1/events', $this->authHeaders($tokens))
            ->assertOk()
            ->assertJsonFragment(['id' => $eventId]);

        $this->getJson("/api/v1/events/{$eventId}", $this->authHeaders($tokens))
            ->assertOk()
            ->assertJsonPath('data.location', 'Salón');
    }

    public function test_can_sync_guests_rsvp_and_list_invitations(): void
    {
        ['user' => $parent, 'tokens' => $tokens, 'family' => $family] = $this->createUserWithFamily();

        $partner = User::factory()->create([
            'family_id' => $family->id,
            'role' => 'madre',
            'email' => 'madre.evento@yopmail.com',
            'device_secret_hash' => \Illuminate\Support\Facades\Hash::make('test-device-secret'),
            'security_question_key' => 'pet_name',
            'security_answer_hash' => \Illuminate\Support\Facades\Hash::make('firulais'),
            'device_fingerprint' => 'partner-device-1',
        ]);
        \App\Models\FamilyMember::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $family->id,
            'user_id' => $partner->id,
            'role' => 'madre',
            'status' => 'active',
        ]);
        \App\Models\Device::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $partner->id,
            'device_fingerprint' => 'partner-device-1',
            'platform' => 'android',
            'is_trusted' => true,
            'last_seen_at' => now(),
        ]);

        $event = FamilyEvent::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $family->id,
            'created_by' => $parent->id,
            'title' => 'Salida parque',
            'starts_at' => now()->addDays(2),
            'status' => 'scheduled',
        ]);

        $this->putJson("/api/v1/events/{$event->id}/guests", [
            'guests' => [
                [
                    'user_id' => $partner->id,
                    'name' => $partner->name,
                    'email' => $partner->email,
                    'guest_kind' => 'adult',
                ],
                [
                    'name' => 'Primo niño',
                    'email' => 'primo.nino@yopmail.com',
                    'guest_kind' => 'child',
                ],
            ],
        ], $this->authHeaders($tokens))
            ->assertOk()
            ->assertJsonPath('data.rsvp_counts.total', 2)
            ->assertJsonPath('data.rsvp_counts.adults', 1)
            ->assertJsonPath('data.rsvp_counts.children', 1);

        $this->assertDatabaseHas('family_event_guests', [
            'event_id' => $event->id,
            'name' => 'Primo niño',
            'guest_kind' => 'child',
        ]);

        $guest = FamilyEventGuest::query()
            ->where('event_id', $event->id)
            ->where('user_id', $partner->id)
            ->firstOrFail();

        $partnerTokens = app(\App\Infrastructure\Auth\TokenService::class)->issue($partner);

        $this->patchJson(
            "/api/v1/events/{$event->id}/guests/{$guest->id}/rsvp",
            ['status' => 'attending'],
            $this->authHeaders($partnerTokens),
        )
            ->assertOk()
            ->assertJsonPath('data.status', 'attending');

        $this->getJson('/api/v1/events/invitations/me', $this->authHeaders($partnerTokens))
            ->assertOk()
            ->assertJsonFragment(['id' => $guest->id]);
    }

    public function test_can_cancel_event(): void
    {
        ['user' => $parent, 'tokens' => $tokens, 'family' => $family] = $this->createUserWithFamily();
        $event = FamilyEvent::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $family->id,
            'created_by' => $parent->id,
            'title' => 'Cancelable',
            'starts_at' => now()->addDay(),
            'status' => 'scheduled',
        ]);

        $this->postJson("/api/v1/events/{$event->id}/cancel", [], $this->authHeaders($tokens))
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }
}
