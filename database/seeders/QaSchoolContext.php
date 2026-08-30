<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\School;
use App\Models\SchoolClass;
use App\Models\SchoolGroup;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

/** Contexto QAESCOLA reutilizable por seeders de demo. */
final class QaSchoolContext
{
    public static function school(): ?School
    {
        return School::query()->where('code', QaUsersSeeder::SCHOOL_CODE)->first();
    }

    public static function class3b(?School $school = null): ?SchoolClass
    {
        $school ??= self::school();
        if ($school === null) {
            return null;
        }

        return SchoolClass::query()
            ->where('school_id', $school->id)
            ->where('name', '3°B QA')
            ->first();
    }

    public static function padresGroup(?School $school = null): ?SchoolGroup
    {
        $school ??= self::school();
        if ($school === null || ! Schema::hasTable('school_groups')) {
            return null;
        }

        return SchoolGroup::query()
            ->where('school_id', $school->id)
            ->where('name', '3°B Padres QA')
            ->first();
    }

    public static function admin(): ?User
    {
        return User::query()->where('email', QaUsersSeeder::ADMIN_EMAIL)->first();
    }

    public static function docente(): ?User
    {
        return User::query()->where('email', QaUsersSeeder::DOCENTE_EMAIL)->first();
    }

    public static function padre(): ?User
    {
        return User::query()->where('email', QaUsersSeeder::PADRE_EMAIL)->first();
    }

    public static function hijo(): ?User
    {
        return User::query()->where('email', QaUsersSeeder::HIJO_EMAIL)->first();
    }

    public static function hijo2(): ?User
    {
        return User::query()->where('email', QaUsersSeeder::HIJO2_EMAIL)->first();
    }
}
