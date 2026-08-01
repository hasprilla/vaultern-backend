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
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\Task;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoHhUserSeeder extends Seeder
{
    /** Registros masivos (inserción por lotes). Total objetivo: 5000. */
    private const BULK_TASK_COUNT = 2000;

    private const BULK_TRANSACTION_COUNT = 2000;

    private const BULK_NOTIFICATION_COUNT = 1000;
    public const PRIMARY_EMAIL = 'hh@yopmail.com';

    public const PARTNER_EMAIL = 'pareja.hh@yopmail.com';

    public const PENDING_PARTNER_EMAIL = 'invitado.pendiente@yopmail.com';

    public const PENDING_TUTOR_EMAIL = 'carlos.invitado@yopmail.com';

    public const DEMO_PASSWORD = 'password';

    /** @var list<string> */
    private const DEMO_EMAILS = [
        self::PARTNER_EMAIL,
        self::PENDING_PARTNER_EMAIL,
        self::PENDING_TUTOR_EMAIL,
        'sofia.demo@zumifly.internal',
        'lucas.demo@zumifly.internal',
        'valentina.demo@zumifly.internal',
    ];

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
            'owner_user_id' => $harvey->id,
            'timezone' => 'America/Bogota',
            'settings' => ['locale' => 'es', 'currency' => 'COP'],
        ]);

        $maria = User::query()->updateOrCreate(
            ['email' => self::PARTNER_EMAIL],
            [
                'name' => 'María Demo',
                'password' => Hash::make(self::DEMO_PASSWORD),
                'role' => 'madre',
                'family_id' => $family->id,
                'email_verified_at' => now(),
            ],
        );

        $sofia = User::query()->updateOrCreate(
            ['email' => 'sofia.demo@zumifly.internal'],
            [
                'name' => 'Sofía Demo',
                'password' => Hash::make(Str::random(32)),
                'role' => 'hijo',
                'family_id' => $family->id,
                'email_verified_at' => now(),
            ],
        );

        $lucas = User::query()->updateOrCreate(
            ['email' => 'lucas.demo@zumifly.internal'],
            [
                'name' => 'Lucas Demo',
                'password' => Hash::make(Str::random(32)),
                'role' => 'hijo',
                'family_id' => $family->id,
                'email_verified_at' => now(),
            ],
        );

        $valentina = User::query()->updateOrCreate(
            ['email' => 'valentina.demo@zumifly.internal'],
            [
                'name' => 'Valentina Demo',
                'password' => Hash::make(Str::random(32)),
                'role' => 'hijo',
                'family_id' => $family->id,
                'email_verified_at' => now(),
            ],
        );

        $harvey->update(['family_id' => $family->id]);

        foreach ([
            [$harvey, 'padre'],
            [$maria, 'madre'],
            [$sofia, 'hijo'],
            [$lucas, 'hijo'],
            [$valentina, 'hijo'],
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

        $tasks = $this->seedTasks($family, $harvey, $maria, $sofia, $lucas, $valentina);
        $transactions = $this->seedTransactions($family, $harvey, $maria, $sofia, $lucas, $valentina);
        $budgets = $this->seedBudgets($family);
        $ocrJobs = $this->seedOcrJobs($family, $maria, $harvey);

        $this->seedJoinRequests($family, $harvey, $maria);

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
        $this->command?->info('Solicitudes pendientes: '.self::PENDING_PARTNER_EMAIL.' y '.self::PENDING_TUTOR_EMAIL.' (clave: '.self::DEMO_PASSWORD.')');
        $this->command?->info(sprintf(
            'Datos generados: %d tareas, %d transacciones, %d notificaciones (%d registros masivos + datos demo).',
            Task::query()->where('family_id', $family->id)->count(),
            Transaction::query()->where('family_id', $family->id)->count(),
            AppNotification::query()->where('family_id', $family->id)->count(),
            self::BULK_TASK_COUNT + self::BULK_TRANSACTION_COUNT + self::BULK_NOTIFICATION_COUNT,
        ));
    }

    private function seedJoinRequests(Family $family, User $harvey, User $maria): void
    {
        FamilyJoinRequest::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $family->id,
            'invited_by_user_id' => $harvey->id,
            'name' => 'Ana Pendiente',
            'email' => self::PENDING_PARTNER_EMAIL,
            'password' => self::DEMO_PASSWORD,
            'role' => 'madre',
            'status' => 'pending',
        ]);

        FamilyJoinRequest::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $family->id,
            'invited_by_user_id' => $maria->id,
            'name' => 'Carlos Tutor',
            'email' => self::PENDING_TUTOR_EMAIL,
            'password' => self::DEMO_PASSWORD,
            'role' => 'tutor',
            'status' => 'pending',
        ]);
    }

    private function resetDemoFamily(): void
    {
        $primary = User::query()->where('email', self::PRIMARY_EMAIL)->first();
        $familyId = $primary?->family_id;

        if ($familyId !== null) {
            $this->purgeFamilyData((string) $familyId);
        }

        $orphanFamily = Family::query()->where('invite_code', 'HHFAMILY')->first();
        if ($orphanFamily !== null && $orphanFamily->id !== $familyId) {
            $this->purgeFamilyData((string) $orphanFamily->id);
        }

        User::query()
            ->withTrashed()
            ->whereIn('email', self::DEMO_EMAILS)
            ->forceDelete();

        $primary?->update(['family_id' => null]);
    }

    private function purgeFamilyData(string $familyId): void
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('support_ticket_messages')) {
            SupportTicketMessage::query()
                ->whereIn('ticket_id', SupportTicket::query()->where('family_id', $familyId)->select('id'))
                ->delete();
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('support_tickets')) {
            SupportTicket::query()->where('family_id', $familyId)->delete();
        }

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
            ->withTrashed()
            ->where('family_id', $familyId)
            ->where('email', '!=', self::PRIMARY_EMAIL)
            ->forceDelete();

        Family::query()->where('id', $familyId)->delete();
    }

    /**
     * @return array<string, Task>
     */
    private function seedTasks(Family $family, User $harvey, User $maria, User $sofia, User $lucas, User $valentina): array
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

        $children = [$sofia, $lucas, $valentina];
        $statuses = ['pending', 'in_progress', 'done', 'overdue'];
        $priorities = ['baja', 'media', 'alta', 'urgente'];
        $subjects = ['Matemáticas', 'Español', 'Ciencias', 'Inglés', 'Arte', 'Historia'];

        for ($i = 1; $i <= 18; $i++) {
            $status = $statuses[$i % count($statuses)];
            $tasks["extra_$i"] = Task::query()->create([
                'id' => (string) Str::uuid(),
                'family_id' => $family->id,
                'created_by' => $i % 2 === 0 ? $harvey->id : $maria->id,
                'assigned_to' => $children[$i % count($children)]->id,
                'title' => "Tarea demo #$i",
                'description' => 'Generada para pruebas de paginación y listados.',
                'status' => $status,
                'priority' => $priorities[$i % count($priorities)],
                'is_school' => $i % 3 !== 0,
                'subject' => $subjects[$i % count($subjects)],
                'due_date' => now()->addDays($i - 9)->toDateString(),
                'completed_at' => $status === 'done' ? now()->subDays($i % 5) : null,
            ]);
        }

        $this->bulkInsertTasks($family, $harvey, $maria, $children, self::BULK_TASK_COUNT);

        return $tasks;
    }

    /**
     * @param  list<User>  $children
     */
    private function bulkInsertTasks(
        Family $family,
        User $harvey,
        User $maria,
        array $children,
        int $count,
    ): void {
        $statuses = ['pending', 'in_progress', 'done', 'overdue'];
        $priorities = ['baja', 'media', 'alta', 'urgente'];
        $subjects = ['Matemáticas', 'Español', 'Ciencias', 'Inglés', 'Arte', 'Historia', 'Educación física'];
        $parents = [$harvey, $maria];
        $now = now();
        $rows = [];

        for ($i = 1; $i <= $count; $i++) {
            $status = $statuses[$i % count($statuses)];
            $createdAt = $now->copy()->subDays($i % 120)->subHours($i % 24);
            $rows[] = [
                'id' => (string) Str::uuid(),
                'family_id' => $family->id,
                'created_by' => $parents[$i % 2]->id,
                'assigned_to' => $children[$i % count($children)]->id,
                'title' => "Tarea volumen #$i",
                'description' => 'Registro masivo para pruebas de paginación y rendimiento.',
                'status' => $status,
                'priority' => $priorities[$i % count($priorities)],
                'is_school' => $i % 3 !== 0,
                'subject' => $i % 3 !== 0 ? $subjects[$i % count($subjects)] : null,
                'due_date' => $now->copy()->addDays(($i % 30) - 15)->toDateString(),
                'completed_at' => $status === 'done' ? $createdAt->copy()->addHours(4) : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            if (count($rows) >= 100) {
                DB::table('tasks')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('tasks')->insert($rows);
        }
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
        User $valentina,
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
            ['key' => 'allowance_valentina', 'amount' => 45000, 'type' => 'expense', 'category' => 'Mesada', 'description' => 'Mesada Valentina', 'days_ago' => 2, 'user' => $maria, 'child' => $valentina],
            ['key' => 'school_lucas', 'amount' => 420000, 'type' => 'expense', 'category' => 'Educación', 'description' => 'Mensualidad Lucas', 'days_ago' => 11, 'user' => $harvey, 'child' => $lucas],
            ['key' => 'utilities', 'amount' => 310000, 'type' => 'expense', 'category' => 'Servicios', 'description' => 'Agua y luz', 'days_ago' => 6, 'user' => $maria, 'child' => null],
            ['key' => 'bonus', 'amount' => 900000, 'type' => 'income', 'category' => 'Bono', 'description' => 'Bono trimestral', 'days_ago' => 20, 'user' => $maria, 'child' => null],
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

        $categories = ['Mercado', 'Transporte', 'Educación', 'Ocio', 'Salud', 'Hogar'];
        $children = [$sofia, $lucas, $valentina];
        for ($i = 1; $i <= 20; $i++) {
            $isIncome = $i % 5 === 0;
            $transactions["generated_$i"] = Transaction::query()->create([
                'id' => (string) Str::uuid(),
                'family_id' => $family->id,
                'user_id' => $i % 2 === 0 ? $harvey->id : $maria->id,
                'child_id' => $i % 4 === 0 ? $children[$i % 3]->id : null,
                'amount' => ($isIncome ? 250000 : 85000) + ($i * 3500),
                'currency' => 'COP',
                'type' => $isIncome ? 'income' : 'expense',
                'category' => $categories[$i % count($categories)],
                'description' => ($isIncome ? 'Ingreso' : 'Gasto')." demo #$i",
                'transaction_date' => now()->subDays($i)->toDateString(),
            ]);
        }

        $this->bulkInsertTransactions($family, $harvey, $maria, $children, self::BULK_TRANSACTION_COUNT);

        return $transactions;
    }

    /**
     * @param  list<User>  $children
     */
    private function bulkInsertTransactions(
        Family $family,
        User $harvey,
        User $maria,
        array $children,
        int $count,
    ): void {
        $categories = ['Mercado', 'Transporte', 'Educación', 'Ocio', 'Salud', 'Hogar', 'Servicios', 'Mesada'];
        $parents = [$harvey, $maria];
        $now = now();
        $rows = [];

        for ($i = 1; $i <= $count; $i++) {
            $isIncome = $i % 6 === 0;
            $createdAt = $now->copy()->subDays($i % 180);
            $rows[] = [
                'id' => (string) Str::uuid(),
                'family_id' => $family->id,
                'user_id' => $parents[$i % 2]->id,
                'child_id' => $i % 5 === 0 ? $children[$i % count($children)]->id : null,
                'amount' => ($isIncome ? 180000 : 65000) + ($i * 1250),
                'currency' => 'COP',
                'type' => $isIncome ? 'income' : 'expense',
                'category' => $categories[$i % count($categories)],
                'description' => ($isIncome ? 'Ingreso' : 'Gasto')." volumen #$i",
                'transaction_date' => $createdAt->toDateString(),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            if (count($rows) >= 100) {
                DB::table('transactions')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('transactions')->insert($rows);
        }
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
            'health' => Budget::query()->create([
                'id' => (string) Str::uuid(),
                'family_id' => $family->id,
                'name' => 'Salud',
                'amount' => 600000,
                'currency' => 'COP',
                'period' => 'monthly',
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ]),
            'transport' => Budget::query()->create([
                'id' => (string) Str::uuid(),
                'family_id' => $family->id,
                'name' => 'Transporte',
                'amount' => 350000,
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
    private function seedOcrJobs(Family $family, User $maria, User $harvey): array
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
            'receipt' => OcrJob::query()->create([
                'id' => (string) Str::uuid(),
                'family_id' => $family->id,
                'user_id' => $harvey->id,
                'type' => 'invoice',
                'status' => 'done',
                'file_path' => 'ocr/demo/recibo-colegio.jpg',
                'mime_type' => 'image/jpeg',
                'raw_text' => ['text' => 'Recibo de colegio digitalizado.'],
                'structured_data' => [
                    'vendor' => 'Colegio Demo',
                    'total' => 450000,
                    'currency' => 'COP',
                    'invoice_date' => now()->subDays(10)->toDateString(),
                ],
                'confidence' => 0.9,
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
                'body' => "{$maria->name} invitó a ".self::PENDING_TUTOR_EMAIL.' como tutor',
                'data' => ['email' => self::PENDING_TUTOR_EMAIL],
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
            $this->createNotification(
                $family,
                $harvey,
                $maria,
                $definition,
            );
        }

        foreach (array_slice($definitions, 0, 8) as $definition) {
            $this->createNotification($family, $maria, $harvey, $definition);
        }

        $this->createNotification($family, $harvey, $harvey, [
            'type' => 'family_invite',
            'title' => 'Solicitud de unión pendiente',
            'body' => 'Ana Pendiente ('.self::PENDING_PARTNER_EMAIL.') espera tu aprobación',
            'data' => ['email' => self::PENDING_PARTNER_EMAIL],
            'read' => false,
            'days' => 0,
        ]);

        $this->bulkInsertNotifications($family, $harvey, $maria, self::BULK_NOTIFICATION_COUNT);
    }

    private function bulkInsertNotifications(Family $family, User $harvey, User $maria, int $count): void
    {
        $types = ['task_created', 'task_completed', 'finance_transaction', 'family_invite', 'ocr_scan', 'alert_read'];
        $recipients = [$harvey, $maria];
        $actors = [$maria, $harvey];
        $now = now();
        $rows = [];

        for ($i = 1; $i <= $count; $i++) {
            $createdAt = $now->copy()->subDays($i % 90)->subHours($i % 12);
            $read = $i % 3 === 0;
            $actor = $actors[$i % 2];
            $rows[] = [
                'id' => (string) Str::uuid(),
                'family_id' => $family->id,
                'user_id' => $recipients[$i % 2]->id,
                'type' => $types[$i % count($types)],
                'title' => "Notificación demo #$i",
                'body' => "{$actor->name} generó actividad de prueba #$i en la familia.",
                'data' => json_encode([
                    'actor_id' => $actor->id,
                    'actor_name' => $actor->name,
                    'entity_type' => 'demo',
                    'entity_id' => (string) Str::uuid(),
                ], JSON_THROW_ON_ERROR),
                'read' => $read,
                'read_at' => $read ? $createdAt->copy()->addHour() : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            if (count($rows) >= 100) {
                DB::table('notifications')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('notifications')->insert($rows);
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function createNotification(
        Family $family,
        User $recipient,
        User $actor,
        array $definition,
    ): void {
        $createdAt = now()->subDays((int) ($definition['days'] ?? 0));
        $read = (bool) ($definition['read'] ?? false);

        AppNotification::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $family->id,
            'user_id' => $recipient->id,
            'type' => $definition['type'],
            'title' => $definition['title'],
            'body' => $definition['body'],
            'data' => array_merge(
                $definition['data'] ?? [],
                [
                    'actor_id' => $actor->id,
                    'actor_name' => $actor->name,
                ],
            ),
            'read' => $read,
            'read_at' => $read ? $createdAt->copy()->addHours(2) : null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}
