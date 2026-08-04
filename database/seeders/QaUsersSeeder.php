<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AppNotification;
use App\Models\Budget;
use App\Models\ChildGuardian;
use App\Models\ClassEnrollment;
use App\Models\Device;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\School;
use App\Models\SchoolAnnouncement;
use App\Models\SchoolCampus;
use App\Models\SchoolClass;
use App\Models\SchoolGroup;
use App\Models\SchoolGroupMember;
use App\Models\SchoolMeeting;
use App\Models\SchoolMeetingRsvp;
use App\Models\SchoolStaffInvite;
use App\Models\SchoolSubscription;
use App\Models\SchoolTeacherTask;
use App\Models\Subscription;
use App\Models\Task;
use App\Models\TeacherMembership;
use App\Models\Transaction;
use App\Models\User;
use App\Support\DeviceSecurityQuestions;
use App\Support\SchemaCompat;
use App\Support\SubscriptionPlanCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Usuarios QA listos para login en prod: flujos familiares, tutor, colegio y plataforma.
 *
 * Clave de app: password
 * Clave secreta dispositivo: secret1234
 * Respuesta de seguridad: bogota (pregunta key: city)
 */
class QaUsersSeeder extends Seeder
{
    public const PASSWORD = 'password';

    public const DEVICE_SECRET = 'secret1234';

    public const SECURITY_ANSWER = 'bogota';

    public const SECURITY_QUESTION = 'city';

    public const SCHOOL_CODE = 'QAESCOLA';

    public const PADRE_EMAIL = 'qa.padre@yopmail.com';

    public const MADRE_EMAIL = 'qa.madre@yopmail.com';

    public const TUTOR_EMAIL = 'qa.tutor@yopmail.com';

    public const ADMIN_EMAIL = 'qa.admin.escuela@yopmail.com';

    public const PLATFORM_ADMIN_EMAIL = 'qa.admin@yopmail.com';

    public const DOCENTE_EMAIL = 'qa.docente@yopmail.com';

    public const FREE_EMAIL = 'qa.familia.free@yopmail.com';

    public const HIJO_EMAIL = 'qa.hijo.sofia@zumifly.internal';

    public const HIJO2_EMAIL = 'qa.hijo.lucas@zumifly.internal';

    public function run(): void
    {
        SubscriptionPlanCatalog::ensureSeeded();

        $family = $this->seedFamilyHub();
        $school = $this->seedSchool($family);
        $this->seedFamilyActivity($family);
        $this->seedSchoolActivity($school, $family);
        $this->seedFreeFamily();
        $this->seedTutorSoloFamily();
        $this->seedStaffInvite($school);
        $this->seedPlatformAdmin($school);

        $this->command?->info('');
        $this->command?->info('=== Cuentas QA (password: password) ===');
        $this->command?->info('Admin plataforma:  '.self::PLATFORM_ADMIN_EMAIL);
        $this->command?->info('Padre (Plus):      '.self::PADRE_EMAIL);
        $this->command?->info('Madre:             '.self::MADRE_EMAIL);
        $this->command?->info('Tutor (en familia):'.self::TUTOR_EMAIL);
        $this->command?->info('Free:              '.self::FREE_EMAIL);
        $this->command?->info('Admin escuela:     '.self::ADMIN_EMAIL);
        $this->command?->info('Docente:           '.self::DOCENTE_EMAIL);
        $this->command?->info('Colegio código:    '.self::SCHOOL_CODE);
        $this->command?->info('Familia invite:    QAFAMILY  · Free: QAFREE01');
        $this->command?->info('Hijos: TI 1001001001 Sofía · TI 1001001002 Lucas');
        $this->command?->info('Device secret:     '.self::DEVICE_SECRET.' · respuesta: '.self::SECURITY_ANSWER);
        $this->command?->info('Staff invite: qa.docente.pendiente@yopmail.com · QAINVITE1');
    }

    private function seedPlatformAdmin(School $school): void
    {
        $admin = $this->upsertUser(
            self::PLATFORM_ADMIN_EMAIL,
            'QA Super Admin',
            'admin',
            documentType: 'CC',
            documentNumber: '9009009009',
            phone: '3009000000',
            birthdate: '1990-01-01',
            address: 'Bogotá, Colombia',
        );
        $this->applyDeviceRecovery($admin);
        $this->touchDevice($admin);

        TeacherMembership::query()->updateOrCreate(
            ['school_id' => $school->id, 'user_id' => $admin->id],
            [
                'role' => 'admin',
                'status' => 'active',
            ],
        );
    }

    private function seedFamilyHub(): Family
    {
        $padre = $this->upsertUser(
            self::PADRE_EMAIL,
            'QA Padre Rivera',
            'padre',
            documentType: 'CC',
            documentNumber: '1002003001',
            phone: '3001110001',
            birthdate: '1985-03-12',
            address: 'Calle 50 #1-10',
        );
        $madre = $this->upsertUser(
            self::MADRE_EMAIL,
            'QA Madre Rivera',
            'madre',
            documentType: 'CC',
            documentNumber: '1002003002',
            phone: '3001110002',
            birthdate: '1987-06-20',
            address: 'Calle 50 #1-10',
        );
        $tutor = $this->upsertUser(
            self::TUTOR_EMAIL,
            'QA Tutor López',
            'tutor',
            documentType: 'CC',
            documentNumber: '1002003003',
            phone: '3001110003',
            birthdate: '1980-01-05',
            address: 'Carrera 7 #20-30',
        );

        $family = Family::query()->updateOrCreate(
            ['invite_code' => 'QAFAMILY'],
            [
                'name' => 'Familia QA Zumifly',
                'plan' => 'family_plus',
                'owner_user_id' => $padre->id,
                'timezone' => 'America/Bogota',
                'settings' => ['locale' => 'es', 'currency' => 'COP'],
            ],
        );

        if (Schema::hasTable('subscriptions')) {
            Subscription::query()->updateOrCreate(
                ['family_id' => $family->id],
                [
                    'plan_code' => 'family_plus',
                    'billing' => 'monthly',
                    'status' => 'active',
                    'provider' => 'simulated',
                    'current_period_end' => now()->addMonth(),
                    'cancelled_at' => null,
                ],
            );
        }

        $hijo1 = $this->upsertUser(
            self::HIJO_EMAIL,
            'Sofía QA',
            'hijo',
            familyId: $family->id,
            documentType: 'TI',
            documentNumber: '1001001001',
            password: Str::random(40),
            phone: '3001001001',
            birthdate: '2015-05-10',
        );
        $hijo2 = $this->upsertUser(
            self::HIJO2_EMAIL,
            'Lucas QA',
            'hijo',
            familyId: $family->id,
            documentType: 'TI',
            documentNumber: '1001001002',
            password: Str::random(40),
            phone: '3001001002',
            birthdate: '2017-09-22',
        );

        foreach ([$padre, $madre, $tutor] as $u) {
            $u->update(['family_id' => $family->id]);
            $this->applyDeviceRecovery($u);
            $this->touchDevice($u);
        }

        $roles = [
            [$padre, 'padre', true, true],
            [$madre, 'madre', true, true],
            [$tutor, 'tutor', true, false],
            [$hijo1, 'hijo', false, false],
            [$hijo2, 'hijo', false, false],
        ];
        foreach ($roles as [$user, $role, $canTasks, $canFinances]) {
            FamilyMember::query()->updateOrCreate(
                ['family_id' => $family->id, 'user_id' => $user->id],
                [
                    'role' => $role,
                    'status' => 'active',
                    'joined_at' => now()->subMonth(),
                    'can_tasks' => $canTasks,
                    'can_finances' => $canFinances,
                ],
            );
        }

        if (Schema::hasTable('child_guardians')) {
            foreach ([$hijo1, $hijo2] as $child) {
                foreach ([$padre, $madre, $tutor] as $parent) {
                    ChildGuardian::query()->updateOrCreate(
                        [
                            'child_user_id' => $child->id,
                            'parent_user_id' => $parent->id,
                        ],
                        [
                            'id' => (string) Str::uuid(),
                            'family_id' => $family->id,
                            'relation' => $parent->role,
                        ],
                    );
                }
            }
        }

        return $family->fresh();
    }

    private function seedFamilyActivity(Family $family): void
    {
        $padre = User::query()->where('email', self::PADRE_EMAIL)->first();
        $madre = User::query()->where('email', self::MADRE_EMAIL)->first();
        $hijo1 = User::query()->where('email', self::HIJO_EMAIL)->first();
        $hijo2 = User::query()->where('email', self::HIJO2_EMAIL)->first();
        if ($padre === null || $hijo1 === null) {
            return;
        }

        if (Schema::hasTable('tasks')) {
            Task::query()->updateOrCreate(
                [
                    'family_id' => $family->id,
                    'title' => 'Matemáticas – fracciones',
                ],
                [
                    'created_by' => $padre->id,
                    'assigned_to' => $hijo1->id,
                    'created_by_role' => 'padre',
                    'description' => 'Terminar página 24 del cuaderno.',
                    'status' => 'pending',
                    'priority' => 'alta',
                    'is_school' => true,
                    'subject' => 'Matemáticas',
                    'due_date' => now()->addDays(2)->toDateString(),
                ],
            );
            Task::query()->updateOrCreate(
                [
                    'family_id' => $family->id,
                    'title' => 'Ordenar habitación',
                ],
                [
                    'created_by' => $madre?->id ?? $padre->id,
                    'assigned_to' => $hijo2?->id ?? $hijo1->id,
                    'created_by_role' => 'madre',
                    'description' => 'Tarea del hogar QA.',
                    'status' => 'pending',
                    'priority' => 'media',
                    'is_school' => false,
                    'due_date' => now()->addDay()->toDateString(),
                ],
            );
            Task::query()->updateOrCreate(
                [
                    'family_id' => $family->id,
                    'title' => 'Lectura de 15 minutos',
                ],
                [
                    'created_by' => $padre->id,
                    'assigned_to' => $hijo1->id,
                    'created_by_role' => 'padre',
                    'description' => 'Completada como ejemplo.',
                    'status' => 'done',
                    'priority' => 'baja',
                    'is_school' => false,
                    'due_date' => now()->subDay()->toDateString(),
                    'completed_at' => now()->subHours(6),
                ],
            );
        }

        if (Schema::hasTable('transactions')) {
            Transaction::query()->updateOrCreate(
                [
                    'family_id' => $family->id,
                    'description' => 'Útiles escolares Sofía',
                ],
                [
                    'user_id' => $padre->id,
                    'child_id' => $hijo1->id,
                    'amount' => 85000,
                    'currency' => 'COP',
                    'type' => 'expense',
                    'category' => 'educacion',
                    'transaction_date' => now()->subDays(3)->toDateString(),
                ],
            );
            Transaction::query()->updateOrCreate(
                [
                    'family_id' => $family->id,
                    'description' => 'Mesada Lucas',
                ],
                [
                    'user_id' => $madre?->id ?? $padre->id,
                    'child_id' => $hijo2?->id,
                    'amount' => 20000,
                    'currency' => 'COP',
                    'type' => 'expense',
                    'category' => 'mesada',
                    'transaction_date' => now()->subDay()->toDateString(),
                ],
            );
            Transaction::query()->updateOrCreate(
                [
                    'family_id' => $family->id,
                    'description' => 'Ingreso familiar QA',
                ],
                [
                    'user_id' => $padre->id,
                    'amount' => 3500000,
                    'currency' => 'COP',
                    'type' => 'income',
                    'category' => 'salario',
                    'transaction_date' => now()->startOfMonth()->toDateString(),
                ],
            );
        }

        if (Schema::hasTable('budgets')) {
            Budget::query()->updateOrCreate(
                [
                    'family_id' => $family->id,
                    'name' => 'Educación mes',
                ],
                [
                    'amount' => 400000,
                    'currency' => 'COP',
                    'period' => 'monthly',
                    'start_date' => now()->startOfMonth()->toDateString(),
                    'end_date' => now()->endOfMonth()->toDateString(),
                ],
            );
        }

        if (Schema::hasTable('notifications')) {
            AppNotification::query()->updateOrCreate(
                [
                    'family_id' => $family->id,
                    'user_id' => $padre->id,
                    'title' => 'Bienvenida QA',
                ],
                [
                    'type' => 'system',
                    'body' => 'Núcleo listo: plan Familia Plus, hijos y colegio enlazados.',
                    'data' => ['seed' => 'qa'],
                    'read' => false,
                ],
            );
        }
    }

    private function seedSchool(Family $family): School
    {
        $admin = $this->upsertUser(
            self::ADMIN_EMAIL,
            'QA Admin Escuela',
            'admin_escuela',
            documentType: 'CC',
            documentNumber: '1002003010',
            phone: '3002220010',
            birthdate: '1982-04-15',
            address: 'Colegio QA',
        );
        $docente = $this->upsertUser(
            self::DOCENTE_EMAIL,
            'QA Docente Pérez',
            'docente',
            documentType: 'CC',
            documentNumber: '1002003011',
            phone: '3002220011',
            birthdate: '1992-08-08',
            address: 'Colegio QA',
        );
        $this->applyDeviceRecovery($admin);
        $this->applyDeviceRecovery($docente);
        $this->touchDevice($admin);
        $this->touchDevice($docente);

        $school = School::query()->updateOrCreate(
            ['code' => self::SCHOOL_CODE],
            [
                'name' => 'Colegio QA Zumifly',
                'plan' => 'school',
                'city' => 'Bogotá',
                'is_active' => true,
                'created_by' => $admin->id,
            ],
        );

        $campus = null;
        if (Schema::hasTable('school_campuses')) {
            $campus = SchoolCampus::query()->updateOrCreate(
                ['school_id' => $school->id, 'is_main' => true],
                [
                    'name' => 'Sede Principal QA',
                    'code' => 'SP01',
                    'city' => 'Bogotá',
                    'address' => 'Calle 100 #10-20',
                    'is_active' => true,
                ],
            );
            $school->update(['main_campus_id' => $campus->id]);
        }

        $classAttrs = [
            'grade' => '3',
            'section' => 'B',
            'school_year' => (string) now()->year,
            'teacher_user_id' => $docente->id,
        ];
        if ($campus !== null && Schema::hasColumn('school_classes', 'campus_id')) {
            $classAttrs['campus_id'] = $campus->id;
        }

        $class = SchoolClass::query()->updateOrCreate(
            ['school_id' => $school->id, 'name' => '3°B QA'],
            $classAttrs,
        );

        foreach (
            [
                [$admin, 'admin'],
                [$docente, 'teacher'],
            ] as [$user, $membershipRole]
        ) {
            TeacherMembership::query()->updateOrCreate(
                ['school_id' => $school->id, 'user_id' => $user->id],
                [
                    'role' => $membershipRole,
                    'status' => 'active',
                ],
            );
        }

        if (Schema::hasTable('school_subscriptions')) {
            SchoolSubscription::query()->updateOrCreate(
                ['school_id' => $school->id],
                [
                    'plan_code' => 'school',
                    'status' => 'active',
                    'billing' => 'monthly',
                    'current_period_end' => now()->addMonth(),
                ],
            );
        }

        foreach ([self::HIJO_EMAIL, self::HIJO2_EMAIL] as $email) {
            $hijo = User::query()->where('email', $email)->first();
            if ($hijo === null || $hijo->family_id === null) {
                continue;
            }
            ClassEnrollment::query()->updateOrCreate(
                [
                    'school_class_id' => $class->id,
                    'student_user_id' => $hijo->id,
                ],
                [
                    'family_id' => $hijo->family_id,
                    'enrolled_by' => $admin->id,
                    'status' => 'active',
                ],
            );
        }

        if (Schema::hasTable('school_groups')) {
            $group = SchoolGroup::query()->updateOrCreate(
                ['school_id' => $school->id, 'name' => '3°B Padres QA'],
                [
                    'campus_id' => $campus?->id,
                    'type' => 'mixed',
                    'description' => 'Grupo de prueba para notificaciones',
                    'created_by' => $admin->id,
                    'is_active' => true,
                ],
            );

            $padre = User::query()->where('email', self::PADRE_EMAIL)->first();
            $madre = User::query()->where('email', self::MADRE_EMAIL)->first();
            if ($padre !== null) {
                SchoolGroupMember::query()->updateOrCreate(
                    ['school_group_id' => $group->id, 'user_id' => $padre->id],
                    ['member_role' => 'member'],
                );
            }
            if ($madre !== null) {
                SchoolGroupMember::query()->updateOrCreate(
                    ['school_group_id' => $group->id, 'user_id' => $madre->id],
                    ['member_role' => 'member'],
                );
            }
            SchoolGroupMember::query()->updateOrCreate(
                ['school_group_id' => $group->id, 'user_id' => $docente->id],
                ['member_role' => 'owner'],
            );
        }

        return $school->fresh();
    }

    private function seedSchoolActivity(School $school, Family $family): void
    {
        $admin = User::query()->where('email', self::ADMIN_EMAIL)->first();
        $docente = User::query()->where('email', self::DOCENTE_EMAIL)->first();
        $padre = User::query()->where('email', self::PADRE_EMAIL)->first();
        $class = SchoolClass::query()
            ->where('school_id', $school->id)
            ->where('name', '3°B QA')
            ->first();
        $group = Schema::hasTable('school_groups')
            ? SchoolGroup::query()->where('school_id', $school->id)->where('name', '3°B Padres QA')->first()
            : null;

        if ($admin === null || $docente === null) {
            return;
        }

        if (Schema::hasTable('school_announcements')) {
            SchoolAnnouncement::query()->updateOrCreate(
                [
                    'school_id' => $school->id,
                    'title' => 'Bienvenida al ciclo escolar QA',
                ],
                [
                    'campus_id' => $school->main_campus_id,
                    'school_class_id' => $class?->id,
                    'created_by' => $admin->id,
                    'type' => 'general',
                    'body' => 'Anuncio de prueba para padres y docentes del Colegio QA.',
                    'data' => ['seed' => 'qa'],
                    'scheduled_at' => now()->subHour(),
                ],
            );
        }

        if (Schema::hasTable('school_meetings')) {
            $meeting = SchoolMeeting::query()->updateOrCreate(
                [
                    'school_id' => $school->id,
                    'title' => 'Reunión de padres 3°B',
                ],
                [
                    'campus_id' => $school->main_campus_id,
                    'school_class_id' => $class?->id,
                    'school_group_id' => $group?->id,
                    'created_by' => $docente->id,
                    'description' => 'Seguimiento académico trimestral (QA).',
                    'starts_at' => now()->addDays(5)->setTime(17, 0),
                    'ends_at' => now()->addDays(5)->setTime(18, 0),
                    'location' => 'Salón 3°B',
                    'status' => 'scheduled',
                ],
            );

            if ($padre !== null && Schema::hasTable('school_meeting_rsvps')) {
                SchoolMeetingRsvp::query()->updateOrCreate(
                    [
                        'school_meeting_id' => $meeting->id,
                        'user_id' => $padre->id,
                    ],
                    [
                        'status' => 'going',
                    ],
                );
            }
        }

        if (Schema::hasTable('school_teacher_tasks')) {
            SchoolTeacherTask::query()->updateOrCreate(
                [
                    'school_id' => $school->id,
                    'title' => 'Preparar material de fracciones',
                ],
                [
                    'school_group_id' => $group?->id,
                    'created_by' => $admin->id,
                    'assigned_to' => $docente->id,
                    'description' => 'Tarea interna de staff QA.',
                    'status' => 'pending',
                    'due_date' => now()->addDays(3)->toDateString(),
                ],
            );
        }

        // Tarea escolar en el núcleo familiar, emitida desde el colegio
        $hijo1 = User::query()->where('email', self::HIJO_EMAIL)->first();
        if ($hijo1 !== null && Schema::hasTable('tasks')) {
            Task::query()->updateOrCreate(
                [
                    'family_id' => $family->id,
                    'title' => 'Tarea colegio: ciencias naturales',
                ],
                [
                    'created_by' => $docente->id,
                    'assigned_to' => $hijo1->id,
                    'school_id' => $school->id,
                    'created_by_role' => 'docente',
                    'description' => 'Leer capítulo 2 y responder preguntas.',
                    'status' => 'pending',
                    'priority' => 'alta',
                    'is_school' => true,
                    'subject' => 'Ciencias',
                    'due_date' => now()->addDays(4)->toDateString(),
                ],
            );
        }
    }

    private function seedFreeFamily(): void
    {
        $user = $this->upsertUser(
            self::FREE_EMAIL,
            'QA Solo Free',
            'padre',
            documentType: 'CC',
            documentNumber: '1002003099',
            phone: '3003330099',
            birthdate: '1991-11-11',
        );
        $family = Family::query()->updateOrCreate(
            ['invite_code' => 'QAFREE01'],
            [
                'name' => 'Familia Free QA',
                'plan' => 'free',
                'owner_user_id' => $user->id,
                'timezone' => 'America/Bogota',
                'settings' => ['locale' => 'es', 'currency' => 'COP'],
            ],
        );
        $user->update(['family_id' => $family->id]);
        $this->applyDeviceRecovery($user);
        $this->touchDevice($user);

        FamilyMember::query()->updateOrCreate(
            ['family_id' => $family->id, 'user_id' => $user->id],
            [
                'role' => 'padre',
                'status' => 'active',
                'joined_at' => now(),
                'can_tasks' => true,
                'can_finances' => true,
            ],
        );

        // Un hijo para demostrar el límite Free (máx 2)
        $child = $this->upsertUser(
            'qa.hijo.free@zumifly.internal',
            'Hijo Free QA',
            'hijo',
            familyId: $family->id,
            documentType: 'TI',
            documentNumber: '1001001099',
            password: Str::random(40),
            birthdate: '2016-02-02',
        );
        FamilyMember::query()->updateOrCreate(
            ['family_id' => $family->id, 'user_id' => $child->id],
            [
                'role' => 'hijo',
                'status' => 'active',
                'joined_at' => now(),
                'can_tasks' => false,
                'can_finances' => false,
            ],
        );
    }

    /**
     * Tutor con su propio núcleo y plan Tutor Plus (flujo planes tutores).
     * El mismo email qa.tutor@yopmail.com sigue en la familia principal;
     * no creamos email nuevo para no romper credenciales ya compartidas.
     * Aquí solo documentamos que el flujo de tutor se prueba con ese miembro
     * en Familia QA (can_tasks) y con planes en app.
     */
    private function seedTutorSoloFamily(): void
    {
        // Sin email extra: el usuario pidió los mismos. El tutor de la hub
        // cubre join + tareas; el plan de la familia es family_plus.
    }

    private function seedStaffInvite(School $school): void
    {
        if (! Schema::hasTable('school_staff_invites')) {
            return;
        }

        $admin = User::query()->where('email', self::ADMIN_EMAIL)->first();
        if ($admin === null) {
            return;
        }

        SchoolStaffInvite::query()->updateOrCreate(
            [
                'school_id' => $school->id,
                'email' => 'qa.docente.pendiente@yopmail.com',
                'status' => 'pending',
            ],
            [
                'role' => 'docente',
                'invite_code' => 'QAINVITE1',
                'invited_by' => $admin->id,
                'expires_at' => now()->addDays(30),
            ],
        );

        $this->command?->info('Invitación staff pendiente: qa.docente.pendiente@yopmail.com · código QAINVITE1');
    }

    private function upsertUser(
        string $email,
        string $name,
        string $role,
        ?string $familyId = null,
        ?string $documentType = null,
        ?string $documentNumber = null,
        ?string $password = null,
        ?string $phone = null,
        ?string $birthdate = null,
        ?string $address = null,
    ): User {
        $attrs = [
            'name' => $name,
            'password' => Hash::make($password ?? self::PASSWORD),
            'role' => $role,
            'family_id' => $familyId,
            'email_verified_at' => now(),
            'account_status' => 'active',
            'deactivated_at' => null,
            'mfa_enabled' => false,
            'device_secret_must_rotate' => false,
        ];

        if (SchemaCompat::hasColumn('users', 'document_type')) {
            $attrs['document_type'] = $documentType;
            $attrs['document_number'] = $documentNumber;
        }
        if ($phone !== null && SchemaCompat::hasColumn('users', 'phone')) {
            $attrs['phone'] = $phone;
        }
        if ($birthdate !== null && SchemaCompat::hasColumn('users', 'birthdate')) {
            $attrs['birthdate'] = $birthdate;
        }
        if ($address !== null && SchemaCompat::hasColumn('users', 'address')) {
            $attrs['address'] = $address;
        }

        return User::query()->withTrashed()->updateOrCreate(
            ['email' => $email],
            $attrs,
        )->refresh();
    }

    private function applyDeviceRecovery(User $user): void
    {
        if (! SchemaCompat::hasColumn('users', 'device_secret_hash')) {
            return;
        }

        if ($user->trashed()) {
            $user->restore();
        }

        $user->forceFill([
            'device_secret_hash' => Hash::make(self::DEVICE_SECRET),
            'security_question_key' => self::SECURITY_QUESTION,
            'security_answer_hash' => Hash::make(
                DeviceSecurityQuestions::normalizeAnswer(self::SECURITY_ANSWER),
            ),
            'device_secret_must_rotate' => false,
            'deleted_at' => null,
        ])->save();
    }

    private function touchDevice(User $user): void
    {
        if (! Schema::hasTable('devices')) {
            return;
        }

        $existing = Device::query()
            ->where('user_id', $user->id)
            ->where('device_fingerprint', 'qa-device-'.$user->id)
            ->first();

        if ($existing !== null) {
            $existing->update([
                'platform' => 'android',
                'is_trusted' => true,
                'last_seen_at' => now(),
            ]);

            return;
        }

        Device::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'device_fingerprint' => 'qa-device-'.$user->id,
            'platform' => 'android',
            'is_trusted' => true,
            'last_seen_at' => now(),
        ]);
    }
}
