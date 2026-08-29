<?php

declare(strict_types=1);

namespace App\Application\School\Support;

final class BuildWeekSlotsFromBlocks
{
    /**
     * @param  list<array{start: string, end: string, kind: string, label: ?string}>  $blocks
     * @param  list<string>  $subjects
     * @return list<array<string, mixed>>
     */
    public function handle(array $blocks, array $subjects): array
    {
        $slots = [];
        for ($day = 1; $day <= 5; $day++) {
            $period = 0;
            foreach ($blocks as $b) {
                $isBreak = ($b['kind'] ?? '') === 'break';
                if (! $isBreak) {
                    $period++;
                }
                $slots[] = [
                    'day' => $day,
                    'start' => $b['start'],
                    'end' => $b['end'],
                    'kind' => $isBreak ? 'break' : 'lesson',
                    'subject' => $this->label($isBreak, $b['label'] ?? null, $subjects, $day, $period),
                ];
            }
        }

        return $slots;
    }

    /** @param  list<string>  $subjects */
    private function label(
        bool $isBreak,
        ?string $breakLabel,
        array $subjects,
        int $day,
        int $period,
    ): string {
        if ($isBreak) {
            return $breakLabel ?: 'Recreo / descanso pedagógico';
        }
        if ($subjects === []) {
            return 'Periodo '.$period;
        }

        return $subjects[(($day - 1) * 6 + ($period - 1)) % count($subjects)];
    }
}
