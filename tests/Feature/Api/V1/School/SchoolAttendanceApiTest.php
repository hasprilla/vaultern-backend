<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\School;

use App\Models\ClassEnrollment;
use App\Models\FamilyMember;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\AuthenticatesUsers;
use Tests\TestCase;

class SchoolAttendanceApiTest extends TestCase
{
    use AuthenticatesUsers;
    use RefreshDatabase;

    public function test_attendance_requires_authentication(): void
    {
        $this->getJson('/api/v1/school/attendance/mine')->assertStatus(401);
        $this->postJson('/api/v1/school/attendance', [])->assertStatus(401);
    }

    public function test_parent_can_list_and_submit_attendance(): void
    {
        ['user' => $parent, 'family' => $family, 'tokens' => $tokens] = $this->createUserWithFamily();

        $child = User::factory()->create([
            'family_id' => $family->id,
            'role' => 'hijo',
        ]);
        FamilyMember::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $family->id,
            'user_id' => $child->id,
            'role' => 'hijo',
            'status' => 'active',
        ]);

        $school = School::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Colegio Asistencia',
            'code' => strtoupper(Str::random(8)),
            'created_by' => $parent->id,
            'is_active' => true,
        ]);
        $class = SchoolClass::query()->create([
            'id' => (string) Str::uuid(),
            'school_id' => $school->id,
            'name' => '4°B',
        ]);
        ClassEnrollment::query()->create([
            'id' => (string) Str::uuid(),
            'school_class_id' => $class->id,
            'student_user_id' => $child->id,
            'family_id' => $family->id,
            'enrolled_by' => $parent->id,
            'status' => 'active',
        ]);

        $this->getJson('/api/v1/school/attendance/mine', $this->authHeaders($tokens))
            ->assertOk()
            ->assertJsonFragment([
                'student_user_id' => $child->id,
                'attendance' => null,
            ]);

        $this->postJson('/api/v1/school/attendance', [
            'student_user_id' => $child->id,
            'status' => 'present',
            'note' => 'Llegó a tiempo',
        ], $this->authHeaders($tokens))
            ->assertCreated()
            ->assertJsonPath('data.status', 'present')
            ->assertJsonPath('data.student_user_id', $child->id);

        $this->getJson('/api/v1/school/attendance/mine', $this->authHeaders($tokens))
            ->assertOk()
            ->assertJsonPath('data.0.attendance.status', 'present');

        $this->assertDatabaseHas('school_attendance_logs', [
            'student_user_id' => $child->id,
            'status' => 'present',
            'family_id' => $family->id,
        ]);
    }
}
