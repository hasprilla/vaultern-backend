<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\FamilyEvent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/** ~100 eventos familiares QA para la familia del padre. */
final class QaFamilyEventsSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('family_events')) {
            return;
        }
        $padre = QaSchoolContext::padre();
        if ($padre === null || $padre->family_id === null) {
            return;
        }

        // QA: admin escolar puede abrir Eventos desde Operación.
        $admin = QaSchoolContext::admin();
        $admin?->update(['family_id' => $padre->family_id]);

        QaBulkSupport::each(function (int $i) use ($padre): void {
            $starts = now()->addDays($i % 60);
            FamilyEvent::query()->updateOrCreate(
                ['family_id' => $padre->family_id, 'title' => "QA Evento #{$i}"],
                [
                    'created_by' => $padre->id,
                    'description' => "Evento familiar QA volumen #{$i}.",
                    'starts_at' => $starts,
                    'ends_at' => $starts->copy()->addHours(2),
                    'location' => 'Casa QA',
                    'status' => $starts->isFuture() ? 'scheduled' : 'done',
                ],
            );
        });
    }
}
