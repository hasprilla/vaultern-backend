<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\School;

use App\Models\School;
use App\Models\SchoolMeeting;
use App\Models\SchoolMeetingRsvp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\AuthenticatesUsers;
use Tests\TestCase;

class ParentSchoolMeetingsApiTest extends TestCase
{
    use AuthenticatesUsers;
    use RefreshDatabase;

    public function test_parent_can_list_and_rsvp_own_meetings(): void
    {
        ['user' => $parent, 'tokens' => $tokens] = $this->createUserWithFamily();

        $school = School::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Colegio Test',
            'code' => strtoupper(Str::random(8)),
            'created_by' => $parent->id,
            'is_active' => true,
        ]);

        $meeting = SchoolMeeting::query()->create([
            'id' => (string) Str::uuid(),
            'school_id' => $school->id,
            'created_by' => $parent->id,
            'title' => 'Entrega de boletines',
            'starts_at' => now()->addDays(3),
            'status' => 'scheduled',
        ]);

        SchoolMeetingRsvp::query()->create([
            'id' => (string) Str::uuid(),
            'school_meeting_id' => $meeting->id,
            'user_id' => $parent->id,
            'status' => 'pending',
        ]);

        $this->getJson('/api/v1/school/meetings/mine', $this->authHeaders($tokens))
            ->assertOk()
            ->assertJsonFragment(['id' => $meeting->id, 'title' => 'Entrega de boletines']);

        $this->postJson("/api/v1/school/meetings/{$meeting->id}/rsvp", [
            'status' => 'attending',
        ], $this->authHeaders($tokens))
            ->assertOk()
            ->assertJsonPath('data.status', 'attending');
    }
}
