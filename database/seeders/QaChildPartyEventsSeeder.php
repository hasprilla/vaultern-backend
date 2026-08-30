<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\FamilyEvent;
use App\Models\FamilyEventExpense;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/** Fiestas de hijos QA + gastos de seguimiento. */
final class QaChildPartyEventsSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('family_events') || ! Schema::hasColumn('family_events', 'kind')) {
            return;
        }
        $padre = QaSchoolContext::padre();
        $sofia = QaSchoolContext::hijo();
        if ($padre === null || $padre->family_id === null || $sofia === null) {
            return;
        }

        $event = FamilyEvent::query()->updateOrCreate(
            ['family_id' => $padre->family_id, 'title' => 'Fiesta Sofía 8 años'],
            [
                'created_by' => $padre->id,
                'description' => 'Cumpleaños en casa con piñata y pastel.',
                'starts_at' => now()->addDays(14)->setTime(15, 0),
                'ends_at' => now()->addDays(14)->setTime(19, 0),
                'location' => 'Casa QA',
                'status' => 'scheduled',
                'kind' => 'child_party',
                'child_user_id' => $sofia->id,
                'budget_amount' => 800000,
                'currency' => 'COP',
            ],
        );

        if (! Schema::hasTable('family_event_expenses')) {
            return;
        }
        foreach ([
            ['Pastel', 180000, 'comida', true],
            ['Decoración', 95000, 'decoracion', true],
            ['Piñata', 45000, 'juego', false],
            ['Recuerdos', 120000, 'otros', false],
        ] as [$title, $amount, $cat, $paid]) {
            FamilyEventExpense::query()->updateOrCreate(
                ['event_id' => $event->id, 'title' => $title],
                [
                    'created_by' => $padre->id,
                    'amount' => $amount,
                    'currency' => 'COP',
                    'category' => $cat,
                    'paid' => $paid,
                ],
            );
        }
    }
}
