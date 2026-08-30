<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/** Colegios extra QA con varias sedes (árbol colegio → campus). */
final class QaMultiSchoolsSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', QaUsersSeeder::ADMIN_EMAIL)->first();
        $docente = User::query()->where('email', QaUsersSeeder::DOCENTE_EMAIL)->first();
        if ($admin === null) {
            return;
        }

        $support = new QaSchoolSeedSupport;
        $support->upsertCampuses(QaUsersSeeder::SCHOOL_CODE, [
            ['Norte QA', 'NORTE', 'Bogotá', false],
            ['Sur QA', 'SUR', 'Soacha', false],
        ]);

        $support->seedSchool(
            'QAOCCTE',
            'Colegio QA Occidente',
            'Medellín',
            (int) $admin->id,
            $docente?->id !== null ? (int) $docente->id : null,
            [
                ['Principal Occidente', 'OCC-P', 'Medellín', true],
                ['Belén QA', 'OCC-B', 'Medellín', false],
            ],
        );

        $support->seedSchool(
            'QAORIENTE',
            'Colegio QA Oriente',
            'Bucaramanga',
            (int) $admin->id,
            $docente?->id !== null ? (int) $docente->id : null,
            [
                ['Principal Oriente', 'ORI-P', 'Bucaramanga', true],
                ['Floridablanca QA', 'ORI-F', 'Floridablanca', false],
            ],
        );
    }
}
