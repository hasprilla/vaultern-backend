<?php

declare(strict_types=1);

namespace App\Application\School\Support;

final class BuildWeekSlotsFromBlocks
{
    /**
     * Plantilla vacía: lecciones sin asignatura (el admin las asigna).
     *
     * @param  list<array{start: string, end: string, kind: string, label: ?string}>  $blocks
     * @param  list<string>  $subjects  reservado (catálogo aparte); no rellena slots
     * @return list<array<string, mixed>>
     */
    public function handle(array $blocks, array $subjects = []): array
    {
        $slots = [];
        for ($day = 1; $day <= 5; $day++) {
            foreach ($blocks as $b) {
                $isBreak = ($b['kind'] ?? '') === 'break';
                $slots[] = [
                    'day' => $day,
                    'start' => $b['start'],
                    'end' => $b['end'],
                    'kind' => $isBreak ? 'break' : 'lesson',
                    'subject' => $isBreak
                        ? ($b['label'] ?: 'Recreo / descanso pedagógico')
                        : '',
                    'teacher_user_id' => null,
                    'teacher_name' => null,
                ];
            }
        }

        return $slots;
    }
}
