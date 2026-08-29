<?php

declare(strict_types=1);

namespace Database\Seeders;

/** Datos estáticos del catálogo curricular Colombia. */
final class ColombiaCurriculumCatalog
{
    /** @return list<string> */
    public static function subjects(string $level): array
    {
        return match ($level) {
            'primaria' => [
                'Matemáticas', 'Lengua castellana', 'Ciencias naturales', 'Ciencias sociales',
                'Inglés', 'Educación artística', 'Educación física', 'Tecnología e informática',
                'Ética y valores', 'Educación religiosa',
            ],
            'secundaria' => [
                'Matemáticas', 'Lengua castellana', 'Ciencias naturales', 'Ciencias sociales',
                'Inglés', 'Educación artística', 'Educación física', 'Tecnología e informática',
                'Ética y valores', 'Filosofía', 'Ciencias económicas', 'Ciencias políticas',
            ],
            default => [],
        };
    }

    /**
     * @return list<array{0:string,1:string,2:string,3:?string}>
     */
    public static function blocks(string $level, string $shift): array
    {
        $break = 'Recreo / descanso pedagógico';
        if ($level === 'primaria' && $shift === 'manana') {
            return [
                ['07:00', '08:00', 'lesson', null], ['08:00', '09:00', 'lesson', null],
                ['09:00', '09:20', 'break', $break], ['09:20', '10:20', 'lesson', null],
                ['10:20', '11:20', 'lesson', null], ['11:20', '12:20', 'lesson', null],
            ];
        }
        if ($level === 'primaria' && $shift === 'tarde') {
            return [
                ['13:00', '14:00', 'lesson', null], ['14:00', '15:00', 'lesson', null],
                ['15:00', '15:20', 'break', $break], ['15:20', '16:20', 'lesson', null],
                ['16:20', '17:20', 'lesson', null], ['17:20', '18:20', 'lesson', null],
            ];
        }
        if ($level === 'secundaria' && $shift === 'manana') {
            return [
                ['07:00', '08:00', 'lesson', null], ['08:00', '09:00', 'lesson', null],
                ['09:00', '09:20', 'break', $break], ['09:20', '10:20', 'lesson', null],
                ['10:20', '11:20', 'lesson', null], ['11:20', '12:20', 'lesson', null],
                ['12:20', '13:20', 'lesson', null],
            ];
        }

        return [
            ['13:00', '14:00', 'lesson', null], ['14:00', '15:00', 'lesson', null],
            ['15:00', '15:20', 'break', $break], ['15:20', '16:20', 'lesson', null],
            ['16:20', '17:20', 'lesson', null], ['17:20', '18:20', 'lesson', null],
            ['18:20', '19:20', 'lesson', null],
        ];
    }
}
