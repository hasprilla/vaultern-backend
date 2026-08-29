<?php

declare(strict_types=1);

namespace App\Application\School\Queries;

use App\Models\SchoolClass;
use App\Models\TeacherMembership;
use App\Models\User;

final class GetTeacherProfileQuery
{
    /** @return array{user: array{id: int, name: string, email: string, role: string}, memberships: list<array<string, mixed>>} */
    public function handle(User $user): array
    {
        $memberships = TeacherMembership::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->with(['school:id,name'])
            ->orderByDesc('updated_at')
            ->get();

        $schoolIds = $memberships->pluck('school_id')->filter()->unique()->values()->all();

        $classesBySchool = SchoolClass::query()
            ->whereIn('school_id', $schoolIds)
            ->orderBy('name')
            ->get(['id', 'name', 'school_id'])
            ->groupBy('school_id');

        return [
            'user' => [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'email' => (string) $user->email,
                'role' => (string) $user->role,
            ],
            'memberships' => $memberships->map(static function (TeacherMembership $m) use ($classesBySchool) {
                $classes = ($classesBySchool->get($m->school_id) ?? collect())
                    ->map(static fn (SchoolClass $c) => [
                        'id' => (string) $c->id,
                        'name' => (string) $c->name,
                    ])
                    ->values()
                    ->all();

                return [
                    'school_id' => (string) $m->school_id,
                    'school_name' => (string) ($m->school?->name ?? ''),
                    'role' => (string) $m->role,
                    'subjects' => is_array($m->subjects) ? array_values($m->subjects) : [],
                    'primary_subject' => $m->primary_subject,
                    'classes' => $classes,
                ];
            })->values()->all(),
        ];
    }
}
