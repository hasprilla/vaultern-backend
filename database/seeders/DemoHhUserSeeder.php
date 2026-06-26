<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AppNotification;
use App\Models\Budget;
use App\Models\Device;
use App\Models\Family;
use App\Models\FamilyJoinRequest;
use App\Models\FamilyMember;
use App\Models\OcrJob;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoHhUserSeeder extends Seeder
{
    public const PRIMARY_EMAIL = 'hh@yopmail.com';

    public const PARTNER_EMAIL = 'pareja.hh@yopmail.com';

    public const DEMO_PASSWORD = 'password';

    public function run(): void
    {
        $this->resetDemoFamily();

        $harvey = User::query()->updateOrCreate(
            ['email' => self::PRIMARY_EMAIL],
            [
                'name' => 'Harvey Demo',
                'password' => Hash::make(self::DEMO_PASSWORD),
                'role' => 'padre',
                'email_verified_at' => now(),
            ],
        );

        $family = Family::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Familia Harvey',
            'plan' => 'premium',
            'invite_code' => 'HHFAMILY',
            'timezone' => 'America/Bogota',
            'settings' => ['locale' => 'es', 'currency' => 'COP'],
        ]);

        $maria = User::query()->create([
            'name' => 'María Demo',
            'email' => self::PARTNER_EMAIL,
            'password' => Hash::make(self::DEMO_PASSWORD),
            'role' => 'madre',
            'family_id' => $family->id,
            'email_verified_at' => now(),
        ]);

        $sofia = User::query()->create([
            'name' => 'Sofía Demo',
            'email' => 'sofia.demo@zumifly.internal',
            'password' => Hash::make(Str::random(32)),
            'role' => 'hijo',
            'family_id' => $family->id,
            'email_verified_at' => now(),
        ]);

        $lucas = User::query()->create([
            'name' => 'Lucas Demo',
            'email' => 'lucas.demo@zumifly.internal',
            'password' => Hash::make(Str::random(32)),
            'role' => 'hijo',
            'family_id' => $family->id,
            'email_verified_at' => now(),
        ]);

        $harvey->update(['family_id' => $family->id]);

        foreach ([
            [$harvey, 'padre'],
            [$maria, 'madre'],
            [$sofia, 'hijo'],
            [$lucas, 'hijo'],
        ] as [$user, $role]) {
            FamilyMember::query()->create([
                'id' => (string) Str::uuid(),
                'family_id' => $family->id,
                'user_id' => $user->id,
                'role' => $role,
                'status' => 'active',
                'joined_at' => now()->subMonths(2),
            ]);
        }

        $tasks = $this->seedTasks($family, $harvey, $maria, $sofia, $lucas);
        $transactions = $this->seedTransactions($family, $harvey, $maria, $sofia, $lucas);
        $budgets = $this->seedBudgets($family);
        $ocrJobs = $this->seedOcrJobs($family, $maria);

        FamilyJoinRequest::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $family->id,
            'invited_by_user_id' => $harvey->id,
            'name' => 'Carlos Invitado',
            'email' => 'carlos.invitado@yopmail.com',
            'password' => Hash::make(self::DEMO_PASSWORD),
            'role' => 'tutor',
            'status' => 'pending',
        ]);

        Device::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $harvey->id,
            'device_fingerprint' => 'demo-xiaomi-hh',
            'platform' => 'android',
            'is_trusted' => true,
            'last_seen_at' => now(),
        ]);

        $this->seedNotifications($family, $harvey, $maria, $sofia, $tasks, $transactions, $budgets, $ocrJobs);

        $this->command?->info('Demo listo para '.self::PRIMARY_EMAIL.' (clave: '.self::DEMO_PASSWORD.')');
        $this->command?->info("Familia: {$family->name} · Código: {$family->invite_code}");
    }

    private function resetDemoFamily(): void
    {
        $primary = User::query()->where('email', self::PRIMARY_EMAIL)->first();

        if ($primary?->family_id === null) {
            User::query()->where('email', self::PARTNER_EMAIL)->delete();

            return;
        }

        $familyId = $primary->family_id;

        AppNotification::query()->where('family_id', $familyId)->delete();
        Task::query()->where('family_id', $familyId)->delete();
        Transaction::query()->where('family_id', $familyId)->delete();
        Budget::query()->where('family_id', $familyId)->delete();
        OcrJob::query()->where('family_id', $familyId)->delete();
        FamilyJoinRequest::query()->where('family_id', $familyId)->delete();
        FamilyMember::query()->where('family_id', $familyId)->delete();

        $memberIds = User::query()->where('family_id', $familyId)->pluck('id');
        Device::query()->whereIn('user_id', $memberIds)->delete();

        User::query()
            ->where('family_id', $familyId)
            ->where('email', '!=', self::PRIMARY_EMAIL)
            ->delete();

        $primary->update(['family_id' => null]);
        Family::query()->where('id', $familyId)->delete();
    }

    /**
     * @return array<string, Task>
     */
    private function seedTasks(Family $family, User $harvey, User $maria, User $sofia, User $lucas): array
    {
        $definitions = [
            'math' => [
                'title' => 'Entregar tarea de matemáticas',
                'description' => 'Ejercicios del capítulo 5, páginas 42-44.',
                'status' => 'pending',
                'priority' => 'alta',
                'is_school' => true,
                'subject' => 'Matemáticas',
                'due_date' => now()->addDays(2)->toDateString(),
                'created_by' => $maria->id,
                'assigned_to' => $sofia->id,
            ],
            'reading' => [
                'title' => 'Leer capítulo 3 de ciencias',
                'description' => 'Preparar resumen para clase.',
                'status' => 'in_progress',
                'priority' => 'media',
                'is_school' => true,
                'subject' => 'Ciencias',
                'due_date' => now()->addDay()->toDateString(),
                'created_by' => $harvey->id,
                'assigned_to' => $lucas->id,
            ],
            'groceries' => [
                'title' => 'Comprar útiles escolares',
                'description' => 'Cuadernos, lápices y marcadores.',
                'status' => 'pending',
                'priority' => 'media',
                'is_school' => false,
                'subject' => null,
                'due_date' => now()->addDays(5)->toDateString(),
                'created_by' => $maria->id,
                'assigned_to' => $harvey->id,
            ],
            'room' => [
                'title' => 'Ordenar el cuarto',
                'description' => 'Antes del fin de semana.',
                'status' => 'done',
                'priority' => 'baja',
                'is_school' => false,
                'subject' => null,
                'due_date' => now()->subDays(1)->toDateString(),
                'created_by' => $harvey->id,
                'assigned_to' => $sofia->id,
                'completed_at' => now()->subHours(6),
            ],
            'english' => [
                'title' => 'Práctica de inglés',
                'description' => 'Vocabulario unidad 4.',
                'status' => 'overdue',
                'priority' => 'urgente',
                'is_school' => true,
                'subject' => 'Inglés',
                'due_date' => now()->subDays(2)->toDateString(),
                'created_by' => $maria->id,
                'assigned_to' => $lucas->id,
            ],
            'doctor' => [
                'title' => 'Cita pediatra Lucas',
                'description' => 'Control anual a las 10:00.',
                'status' => 'pending',
                'priority' => 'alta',
                'is_school' => false,
                'subject' => null,
                'due_date' => now()->addDays(7)->toDateString(),
                'created_by' => $maria->id,
                'assigned_to' => $harvey->id,
            ],
        ];

        $tasks = [];

        foreach ($definitions as $key => $data) {
            $completedAt = $data['completed_at'] ?? null;
            unset($data['completed_at']);

            $tasks[$key] = Task::query()->create([
                'id' => (string) Str::uuid(),
                'family_id' => $family->id,
                ...$data,
                'completed_at' => $completedAt,
            ]);
        }

        return $tasks;
    }

    /**
     * @return array<string, Transaction>
     */
    private function seedTransactions(
        Family $family,
        User $harvey,
        User $maria,
        User $sofia,
        User $lucas,
    ): array {
        $rows = [
            ['key' => 'salary', 'amount' => 5200000, 'type' => 'income', 'category' => 'Salario', 'description' => 'Nómina mensual', 'days_ago' => 5, 'user' => $harvey, 'child' => null],
            ['key' => 'salary2', 'amount' => 3800000, 'type' => 'income', 'category' => 'Salario', 'description' => 'Nómina mensual', 'days_ago' => 5, 'user' => $maria, 'child' => null],
            ['key' => 'market', 'amount' => 285000, 'type' => 'expense', 'category' => 'Mercado', 'description' => 'Compra semanal', 'days_ago' => 3, 'user' => $maria, 'child' => null],
            ['key' => 'school', 'amount' => 450000, 'type' => 'expense', 'category' => 'Educación', 'description' => 'Mensualidad colegio', 'days_ago' => 10, 'user' => $harvey, 'child' => $sofia],
            ['key' => 'transport', 'amount' => 120000, 'type' => 'expense', 'category' => 'Transporte', 'description' => 'Gasolina', 'days_ago' => 2, 'user' => $harvey, 'child' => null],
            ['key' => 'allowance_sofia', 'amount' => 50000, 'type' => 'expense', 'category' => 'Mesada', 'description' => 'Mesada Sofía', 'days_ago' => 1, 'user' => $maria, 'child' => $sofia],
            ['key' => 'allowance_lucas', 'amount' => 50000, 'type' => 'expense', 'category' => 'Mesada', 'description' => 'Mesada Lucas', 'days_ago' => 1, 'user' => $maria, 'child' => $lucas],
            ['key' => 'health', 'amount' => 180000, 'type' => 'expense', 'category' => 'Salud', 'description' => 'Medicamentos', 'days_ago' => 7, 'user' => $maria, 'child' => null],
            ['key' => 'entertainment', 'amount' => 95000, 'type' => 'expense', 'category' => 'Ocio', 'description' => 'Cine familiar', 'days_ago' => 4, 'user' => $harvey, 'child' => null],
            ['key' => 'freelance', 'amount' => 750000, 'type' => 'income', 'category' => 'Freelance', 'description' => 'Proyecto extra', 'days_ago' => 15, 'user' => $harvey, 'child' => null],
        ];

        $transactions = [];

        foreach ($rows as $row) {
            $key = $row['key'];
            unset($row['key']);

            $transactions[$key] = Transaction::query()->create([
                'id' => (string) Str::uuid(),
                'family_id' => $family->id,
                'user_id' => $row['user']->id,
                'child_id' => $row['child']?->id,
                'amount' => $row['amount'],
                'currency' => 'COP',
                'type' => $row['type'],
                'category' => $row['category'],
                'description' => $row['description'],
                'transaction_date' => now()->subDays($row['days_ago'])->toDateString(),
            ]);
        }

        return $transactions;
    }

    /**
     * @return array<string, Budget>
     */
    private function seedBudgets(Family $family): array
    {
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();

        return [
            'home' => Budget::query()->create([
                'id' => (string) Str::uuid(),
                'family_id' => $family->id,
                'name' => 'Hogar',
                'amount' => 2500000,
                'currency' => 'COP',
                'period' => 'monthly',
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ]),
            'education' => Budget::query()->create([
                'id' => (string) Str::uuid(),
                'family_id' => $family->id,
                'name' => 'Educación',
                'amount' => 1200000,
                'currency' => 'COP',
                'period' => 'monthly',
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ]),
            'leisure' => Budget::query()->create([
                'id' => (string) Str::uuid(),
                'family_id' => $family->id,
                'name' => 'Ocio familiar',
                'amount' => 400000,
                'currency' => 'COP',
                'period' => 'monthly',
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ]),
        ];
    }

    /**
     * @return array<string, OcrJob>
     */
    private function seedOcrJobs(Family $family, User $maria): array
    {
        return [
            'invoice' => OcrJob::query()->create([
                'id' => (string) Str::uuid(),
                'family_id' => $family->id,
                'user_id' => $maria->id,
                'type' => 'invoice',
                'status' => 'done',
                'file_path' => 'ocr/demo/factura-mercado.jpg',
                'mime_type' => 'image/jpeg',
                'raw_text' => ['text' => 'Factura digitalizada. Proveedor y total extraídos.'],
                'structured_data' => [
                    'vendor' => 'Supermercado Demo',
                    'total' => 285000,
                    'currency' => 'COP',
                    'invoice_date' => now()->subDays(3)->toDateString(),
                ],
                'confidence' => 0.92,
            ]),
            'handwriting' => OcrJob::query()->create([
                'id' => (string) Str::uuid(),
                'family_id' => $family->id,
                'user_id' => $maria->id,
                'type' => 'handwriting',
                'status' => 'done',
                'file_path' => 'ocr/demo/cuaderno-sofia.jpg',
                'mime_type' => 'image/jpeg',
                'raw_text' => ['text' => 'Cuaderno escolar digitalizado. Tareas detectadas.'],
                'structured_data' => [
                    'tasks' => [
                        ['title' => 'Matemáticas ej. 1-10', 'subject' => 'Matemáticas'],
                        ['title' => 'Leer capítulo 3', 'subject' => 'Español'],
                    ],
                ],
                'confidence' => 0.88,
            ]),
        ];
    }

    /**
     * @param  array<string, Task>  $tasks
     * @param  array<string, Transaction>  $transactions
     * @param  array<string, Budget>  $budgets
     * @param  array<string, OcrJob>  $ocrJobs
     */
    private function seedNotifications(
        Family $family,
        User $harvey,
        User $maria,
        User $sofia,
        array $tasks,
        array $transactions,
        array $budgets,
        array $ocrJobs,
    ): void {
        $actorPayload = fn (User $actor): array => [
            'actor_id' => $actor->id,
            'actor_name' => $actor->name,
        ];

        $definitions = [
            [
                'type' => 'task_created',
                'title' => 'Nueva tarea familiar',
                'body' => "{$maria->name} creó la tarea «{$tasks['math']->title}»",
                'data' => ['entity_type' => 'task', 'entity_id' => $tasks['math']->id],
                'read' => false,
                'days' => 0,
            ],
            [
                'type' => 'task_assigned',
                'title' => 'Tarea asignada',
                'body' => "{$maria->name} te asignó «{$tasks['groceries']->title}»",
                'data' => ['entity_type' => 'task', 'entity_id' => $tasks['groceries']->id],
                'read' => false,
                'days' => 0,
            ],
            [
                'type' => 'task_updated',
                'title' => 'Tarea actualizada',
                'body' => "{$maria->name} actualizó «{$tasks['english']->title}»",
                'data' => ['entity_type' => 'task', 'entity_id' => $tasks['english']->id],
                'read' => true,
                'days' => 1,
            ],
            [
                'type' => 'task_completed',
                'title' => 'Tarea completada',
                'body' => "{$maria->name} completó «{$tasks['room']->title}»",
                'data' => ['entity_type' => 'task', 'entity_id' => $tasks['room']->id],
                'read' => true,
                'days' => 2,
            ],
            [
                'type' => 'finance_transaction',
                'title' => 'Gasto registrado',
                'body' => "{$maria->name} registró Gasto por $285.000 COP",
                'data' => ['entity_type' => 'transaction', 'entity_id' => $transactions['market']->id],
                'read' => false,
                'days' => 3,
            ],
            [
                'type' => 'finance_transaction',
                'title' => 'Ingreso registrado',
                'body' => "{$maria->name} registró Ingreso por $3.800.000 COP",
                'data' => ['entity_type' => 'transaction', 'entity_id' => $transactions['salary2']->id],
                'read' => true,
                'days' => 5,
            ],
            [
                'type' => 'finance_budget',
                'title' => 'Presupuesto creado',
                'body' => "{$maria->name} creó el presupuesto «{$budgets['education']->name}»",
                'data' => ['entity_type' => 'budget', 'entity_id' => $budgets['education']->id],
                'read' => false,
                'days' => 4,
            ],
            [
                'type' => 'finance_budget',
                'title' => 'Presupuesto actualizado',
                'body' => "{$maria->name} actualizó el presupuesto «{$budgets['home']->name}»",
                'data' => ['entity_type' => 'budget', 'entity_id' => $budgets['home']->id],
                'read' => true,
                'days' => 6,
            ],
            [
                'type' => 'family_child',
                'title' => 'Nuevo hijo/a registrado',
                'body' => "{$maria->name} registró a Sofía Demo en la familia",
                'data' => ['entity_type' => 'user', 'entity_id' => (string) $sofia->id],
                'read' => true,
                'days' => 60,
            ],
            [
                'type' => 'family_invite',
                'title' => 'Invitación enviada',
                'body' => "{$maria->name} invitó a carlos.invitado@yopmail.com como tutor",
                'data' => ['email' => 'carlos.invitado@yopmail.com'],
                'read' => false,
                'days' => 1,
            ],
            [
                'type' => 'family_join',
                'title' => 'Nuevo miembro en la familia',
                'body' => "{$maria->name} aprobó la entrada de María Demo",
                'data' => ['entity_type' => 'user', 'entity_id' => (string) $maria->id],
                'read' => true,
                'days' => 55,
            ],
            [
                'type' => 'ocr_scan',
                'title' => 'Documento escaneado',
                'body' => "{$maria->name} digitalizó un factura con OCR",
                'data' => ['entity_type' => 'ocr_job', 'entity_id' => $ocrJobs['invoice']->id],
                'read' => false,
                'days' => 3,
            ],
            [
                'type' => 'ocr_scan',
                'title' => 'Documento escaneado',
                'body' => "{$maria->name} digitalizó un cuaderno escolar con OCR",
                'data' => ['entity_type' => 'ocr_job', 'entity_id' => $ocrJobs['handwriting']->id],
                'read' => true,
                'days' => 7,
            ],
            [
                'type' => 'task_deleted',
                'title' => 'Tarea eliminada',
                'body' => "{$maria->name} eliminó la tarea «Recordatorio antiguo»",
                'data' => ['entity_type' => 'task', 'entity_id' => (string) Str::uuid()],
                'read' => true,
                'days' => 10,
            ],
            [
                'type' => 'alert_read',
                'title' => 'Alerta vista',
                'body' => "{$maria->name} vio tu alerta: Gasto registrado",
                'data' => [
                    'reader_id' => $maria->id,
                    'reader_name' => $maria->name,
                    'original_notification' => (string) Str::uuid(),
                    'read_at' => now()->subHours(3)->toIso8601String(),
                ],
                'read' => true,
                'days' => 0,
            ],
        ];

        foreach ($definitions as $definition) {
            $createdAt = now()->subDays($definition['days']);
            $read = $definition['read'];

            AppNotification::query()->create([
                'id' => (string) Str::uuid(),
                'family_id' => $family->id,
                'user_id' => $harvey->id,
                'type' => $definition['type'],
                'title' => $definition['title'],
                'body' => $definition['body'],
                'data' => array_merge($definition['data'], $actorPayload($maria)),
                'read' => $read,
                'read_at' => $read ? $createdAt->copy()->addHours(2) : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }
}
