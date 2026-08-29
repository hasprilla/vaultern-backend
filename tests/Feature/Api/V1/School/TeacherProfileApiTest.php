<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\School;

use App\Models\School;
use App\Models\SchoolClass;
use App\Models\TeacherMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\AuthenticatesUsers;
use Tests\TestCase;

class TeacherProfileApiTest extends TestCase
{
    use AuthenticatesUsers;
    use RefreshDatabase;

    public function test_teacher_profile_requires_authentication(): void
    {
        $this->getJson('/api/v1/school/teachers/me')->assertStatus(401);
        $this->patchJson('/api/v1/school/teachers/me', [])->assertStatus(401);
    }

    public function test_teacher_can_get_and_update_profile_subjects(): void
    {
        ['user' => $teacher, 'tokens' => $tokens] = $this->createUserWithFamily([
            'role' => 'docente',
        ]);

        $school = School::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Colegio Perfil Docente',
            'code' => strtoupper(Str::random(8)),
            'created_by' => $teacher->id,
            'is_active' => true,
        ]);
        SchoolClass::query()->create([
            'id' => (string) Str::uuid(),
            'school_id' => $school->id,
            'name' => '5°A',
        ]);
        TeacherMembership::query()->create([
            'id' => (string) Str::uuid(),
            'school_id' => $school->id,
            'user_id' => $teacher->id,
            'role' => 'teacher',
            'status' => 'active',
        ]);

        $this->getJson('/api/v1/school/teachers/me', $this->authHeaders($tokens))
            ->assertOk()
            ->assertJsonPath('data.user.id', $teacher->id)
            ->assertJsonPath('data.memberships.0.school_name', 'Colegio Perfil Docente')
            ->assertJsonPath('data.memberships.0.classes.0.name', '5°A');

        $this->patchJson('/api/v1/school/teachers/me', [
            'primary_subject' => 'Matemáticas',
            'subjects' => ['Matemáticas', 'Ciencias'],
        ], $this->authHeaders($tokens))
            ->assertOk()
            ->assertJsonPath('data.memberships.0.primary_subject', 'Matemáticas')
            ->assertJsonPath('data.memberships.0.subjects.0', 'Matemáticas')
            ->assertJsonPath('data.memberships.0.subjects.1', 'Ciencias');

        $this->assertDatabaseHas('teacher_memberships', [
            'user_id' => $teacher->id,
            'primary_subject' => 'Matemáticas',
        ]);

        $this->getJson('/api/v1/school/teachers/classes', $this->authHeaders($tokens))
            ->assertOk()
            ->assertJsonPath('data.0.subject', 'Matemáticas');
    }
}
