<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ClassEnrollment;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\TeacherMembership;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoSchoolSeeder extends Seeder
{
    public const TEACHER_EMAIL = 'docente@yopmail.com';

    public const SCHOOL_CODE = 'DEMOESCOL';

    public function run(): void
    {
        $school = School::query()->firstOrCreate(
            ['code' => self::SCHOOL_CODE],
            [
                'id' => (string) Str::uuid(),
                'name' => 'Colegio Demo Zumifly',
                'plan' => 'school',
                'city' => 'Bogotá',
                'is_active' => true,
            ],
        );

        $class = SchoolClass::query()->firstOrCreate(
            ['school_id' => $school->id, 'name' => '3°A'],
            [
                'id' => (string) Str::uuid(),
                'grade' => '3',
                'section' => 'A',
                'school_year' => '2025-2026',
            ],
        );

        $teacher = User::query()->updateOrCreate(
            ['email' => self::TEACHER_EMAIL],
            [
                'name' => 'Prof. Demo García',
                'password' => Hash::make('password'),
                'role' => 'docente',
                'family_id' => null,
                'email_verified_at' => now(),
            ],
        );

        TeacherMembership::query()->firstOrCreate(
            ['school_id' => $school->id, 'user_id' => $teacher->id],
            [
                'id' => (string) Str::uuid(),
                'role' => 'teacher',
                'status' => 'active',
            ],
        );

        $student = User::query()->where('email', 'sofia.demo@zumifly.internal')->first();

        if ($student !== null && $student->family_id !== null) {
            ClassEnrollment::query()->firstOrCreate(
                ['school_class_id' => $class->id, 'student_user_id' => $student->id],
                [
                    'family_id' => $student->family_id,
                    'enrolled_by' => User::query()->where('role', 'padre')->value('id') ?? $teacher->id,
                    'status' => 'active',
                ],
            );
        }

        $this->command?->info('Colegio demo: '.self::SCHOOL_CODE.' · Docente: '.self::TEACHER_EMAIL.' / password');
    }
}
