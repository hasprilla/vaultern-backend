<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\School;

use App\Application\School\Actions\StoreSchoolScheduleAction;
use App\Application\School\Actions\SyncSchoolGroupMembersAction;
use App\Application\School\Actions\UpdateSchoolScheduleAction;
use App\Application\School\Queries\ListMySchoolMeetingsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\School\StoreSchoolScheduleRequest;
use App\Http\Requests\School\UpdateSchoolScheduleRequest;
use App\Models\ClassEnrollment;
use App\Models\School;
use App\Models\SchoolAnnouncement;
use App\Models\SchoolCampus;
use App\Models\SchoolClass;
use App\Models\SchoolGroup;
use App\Models\SchoolGroupMember;
use App\Models\SchoolHealthAlert;
use App\Models\SchoolMeeting;
use App\Models\SchoolMeetingRsvp;
use App\Models\SchoolPsychCase;
use App\Models\SchoolPsychNote;
use App\Models\SchoolSchedule;
use App\Models\SchoolScheduleShare;
use App\Models\SchoolStaffInvite;
use App\Models\SchoolSubscription;
use App\Models\SchoolTeacherTask;
use App\Models\TeacherMembership;
use App\Models\User;
use App\Services\ChildGuardianService;
use App\Services\FamilyNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SchoolAdminController extends Controller
{
    public function __construct(
        private readonly FamilyNotificationService $notifications,
        private readonly ChildGuardianService $guardians,
    ) {}

    public function registerInstitution(Request $request): JsonResponse
    {
        $user = $request->user();

        $alreadyAdmin = TeacherMembership::query()
            ->where('user_id', $user->id)
            ->where('role', 'admin')
            ->where('status', 'active')
            ->exists();

        if ($alreadyAdmin) {
            return response()->json([
                'message' => 'Ya administras una institución. Usa el panel de esa escuela o contacta soporte.',
            ], 422);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'city' => ['nullable', 'string', 'max:80'],
            'main_campus_name' => ['required', 'string', 'min:2', 'max:120'],
            'campus_code' => ['nullable', 'string', 'max:32'],
        ]);

        $result = DB::transaction(function () use ($user, $validated) {
            $school = School::query()->create([
                'id' => (string) Str::uuid(),
                'name' => $validated['name'],
                'city' => $validated['city'] ?? null,
                'plan' => 'school_trial',
                'created_by' => $user->id,
                'is_active' => true,
            ]);

            $campus = SchoolCampus::query()->create([
                'id' => (string) Str::uuid(),
                'school_id' => $school->id,
                'name' => $validated['main_campus_name'],
                'code' => isset($validated['campus_code'])
                    ? Str::upper($validated['campus_code'])
                    : null,
                'city' => $validated['city'] ?? null,
                'is_main' => true,
                'is_active' => true,
            ]);

            $school->update(['main_campus_id' => $campus->id]);

            TeacherMembership::query()->create([
                'id' => (string) Str::uuid(),
                'school_id' => $school->id,
                'user_id' => $user->id,
                'role' => 'admin',
                'status' => 'active',
            ]);

            $subscription = SchoolSubscription::query()->create([
                'id' => (string) Str::uuid(),
                'school_id' => $school->id,
                'plan_code' => 'school_trial',
                'status' => 'trialing',
                'billing' => 'monthly',
                'current_period_end' => now()->addDays(14),
            ]);

            $user->update(['role' => 'admin_escuela']);

            $school->load(['mainCampus', 'subscription']);

            return compact('school', 'campus', 'subscription');
        });

        return response()->json([
            'message' => 'Institución registrada correctamente.',
            'data' => $result['school'],
        ], 201);
    }

    public function listCampuses(Request $request, School $school): JsonResponse
    {
        if (! $this->assertBelongsToSchool($request->user(), $school)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $campuses = SchoolCampus::query()
            ->where('school_id', $school->id)
            ->orderByDesc('is_main')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $campuses]);
    }

    public function listClasses(Request $request, School $school): JsonResponse
    {
        if (! $this->assertBelongsToSchool($request->user(), $school)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $classes = SchoolClass::query()
            ->with(['teacher:id,name,email', 'campus:id,name'])
            ->withCount(['enrollments' => static fn ($q) => $q->where('status', 'active')])
            ->where('school_id', $school->id)
            ->orderBy('school_year', 'desc')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $classes]);
    }

    public function storeClass(Request $request, School $school): JsonResponse
    {
        if (! $this->assertManagesSchool($request->user(), $school)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:1', 'max:120'],
            'grade' => ['nullable', 'string', 'max:32'],
            'section' => ['nullable', 'string', 'max:32'],
            'school_year' => ['nullable', 'string', 'max:16'],
            'campus_id' => ['nullable', 'uuid', Rule::exists('school_campuses', 'id')->where('school_id', $school->id)],
            'teacher_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $class = SchoolClass::query()->create([
            'id' => (string) Str::uuid(),
            'school_id' => $school->id,
            'name' => $validated['name'],
            'grade' => $validated['grade'] ?? null,
            'section' => $validated['section'] ?? null,
            'school_year' => $validated['school_year'] ?? (string) now()->year,
            'campus_id' => $validated['campus_id'] ?? null,
            'teacher_user_id' => $validated['teacher_user_id'] ?? null,
        ]);

        $class->load(['teacher:id,name,email', 'campus:id,name']);
        $class->loadCount(['enrollments' => static fn ($q) => $q->where('status', 'active')]);

        return response()->json(['data' => $class], 201);
    }

    public function listStudents(Request $request, School $school): JsonResponse
    {
        if (! $this->assertBelongsToSchool($request->user(), $school)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $classId = $request->query('school_class_id');

        $query = ClassEnrollment::query()
            ->with([
                'student:id,name,email,document_type,document_number,phone,family_id',
                'schoolClass:id,name,grade,section,school_year',
            ])
            ->where('status', 'active')
            ->whereHas('schoolClass', static fn ($q) => $q->where('school_id', $school->id));

        if (is_string($classId) && $classId !== '') {
            $query->where('school_class_id', $classId);
        }

        $rows = $query->orderByDesc('created_at')->limit(500)->get();

        return response()->json(['data' => $rows]);
    }

    public function storeCampus(Request $request, School $school): JsonResponse
    {
        if (! $this->assertManagesSchool($request->user(), $school)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'code' => ['nullable', 'string', 'max:32'],
            'city' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_main' => ['nullable', 'boolean'],
        ]);

        $isMain = (bool) ($validated['is_main'] ?? false);

        $campus = DB::transaction(function () use ($school, $validated, $isMain) {
            if ($isMain) {
                SchoolCampus::query()
                    ->where('school_id', $school->id)
                    ->update(['is_main' => false]);
            }

            $campus = SchoolCampus::query()->create([
                'id' => (string) Str::uuid(),
                'school_id' => $school->id,
                'name' => $validated['name'],
                'code' => isset($validated['code']) ? Str::upper($validated['code']) : null,
                'city' => $validated['city'] ?? null,
                'address' => $validated['address'] ?? null,
                'is_main' => $isMain,
                'is_active' => true,
            ]);

            if ($isMain) {
                $school->update(['main_campus_id' => $campus->id]);
            }

            return $campus;
        });

        return response()->json(['data' => $campus], 201);
    }

    public function updateCampus(Request $request, School $school, SchoolCampus $campus): JsonResponse
    {
        if (! $this->assertManagesSchool($request->user(), $school)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ((string) $campus->school_id !== (string) $school->id) {
            return response()->json(['message' => 'Sede no pertenece a esta institución'], 404);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:120'],
            'code' => ['nullable', 'string', 'max:32'],
            'city' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_main' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($school, $campus, $validated) {
            if (array_key_exists('code', $validated) && $validated['code'] !== null) {
                $validated['code'] = Str::upper($validated['code']);
            }

            if (($validated['is_main'] ?? false) === true) {
                SchoolCampus::query()
                    ->where('school_id', $school->id)
                    ->where('id', '!=', $campus->id)
                    ->update(['is_main' => false]);
                $school->update(['main_campus_id' => $campus->id]);
            }

            $campus->update($validated);
        });

        return response()->json(['data' => $campus->fresh()]);
    }

    public function inviteStaff(Request $request, School $school): JsonResponse
    {
        if (! $this->assertManagesSchool($request->user(), $school)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in(['docente', 'admin_escuela'])],
            'campus_id' => ['nullable', 'uuid', Rule::exists('school_campuses', 'id')->where('school_id', $school->id)],
        ]);

        $email = Str::lower($validated['email']);

        $invite = SchoolStaffInvite::query()->create([
            'id' => (string) Str::uuid(),
            'school_id' => $school->id,
            'campus_id' => $validated['campus_id'] ?? null,
            'email' => $email,
            'role' => $validated['role'],
            'invite_code' => Str::upper(Str::random(8)),
            'status' => 'pending',
            'invited_by' => $request->user()->id,
            'expires_at' => now()->addDays(14),
        ]);

        return response()->json(['data' => $invite], 201);
    }

    public function listStaff(Request $request, School $school): JsonResponse
    {
        if (! $this->assertBelongsToSchool($request->user(), $school)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $staff = TeacherMembership::query()
            ->with(['user:id,name,email,role'])
            ->where('school_id', $school->id)
            ->orderBy('role')
            ->orderByDesc('updated_at')
            ->get();

        return response()->json(['data' => $staff]);
    }

    public function listStaffInvites(Request $request, School $school): JsonResponse
    {
        if (! $this->assertManagesSchool($request->user(), $school)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $invites = SchoolStaffInvite::query()
            ->where('school_id', $school->id)
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $invites]);
    }

    public function updateStaffMembership(Request $request, School $school, TeacherMembership $membership): JsonResponse
    {
        if (! $this->assertManagesSchool($request->user(), $school)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ((string) $membership->school_id !== (string) $school->id) {
            return response()->json(['message' => 'Membresía no pertenece a este colegio'], 404);
        }

        $validated = $request->validate([
            'role' => ['sometimes', Rule::in(['admin', 'teacher'])],
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'suspended'])],
        ]);

        if (isset($validated['role']) && $validated['role'] === 'teacher' && $membership->role === 'admin') {
            $otherAdmins = TeacherMembership::query()
                ->where('school_id', $school->id)
                ->where('status', 'active')
                ->where('role', 'admin')
                ->where('id', '!=', $membership->id)
                ->count();

            if ($otherAdmins === 0) {
                return response()->json([
                    'message' => 'Debe quedar al menos un administrador activo en el colegio',
                ], 422);
            }
        }

        if (isset($validated['status'])
            && $validated['status'] !== 'active'
            && $membership->role === 'admin'
            && $membership->status === 'active'
        ) {
            $otherAdmins = TeacherMembership::query()
                ->where('school_id', $school->id)
                ->where('status', 'active')
                ->where('role', 'admin')
                ->where('id', '!=', $membership->id)
                ->count();

            if ($otherAdmins === 0) {
                return response()->json([
                    'message' => 'No puedes desactivar al único administrador del colegio',
                ], 422);
            }
        }

        $membership->fill($validated);
        $membership->save();

        if (isset($validated['role']) && $membership->user !== null) {
            $appRole = $validated['role'] === 'admin' ? 'admin_escuela' : 'docente';
            $membership->user->forceFill(['role' => $appRole])->save();
        }

        $membership->load(['user:id,name,email,role']);

        return response()->json(['data' => $membership]);
    }

    public function acceptStaffInvite(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invite_code' => ['required', 'string', 'max:16'],
        ]);

        $user = $request->user();
        $code = Str::upper($validated['invite_code']);

        $invite = SchoolStaffInvite::query()
            ->where('invite_code', $code)
            ->where('status', 'pending')
            ->first();

        if ($invite === null) {
            return response()->json(['message' => 'Invitación no válida o ya usada'], 404);
        }

        if ($invite->expires_at !== null && $invite->expires_at->isPast()) {
            $invite->update(['status' => 'expired']);

            return response()->json(['message' => 'La invitación expiró'], 422);
        }

        if (Str::lower((string) $user->email) !== Str::lower($invite->email)) {
            return response()->json([
                'message' => 'Esta invitación está dirigida a otro correo electrónico',
            ], 422);
        }

        $membershipRole = $invite->role === 'admin_escuela' ? 'admin' : 'teacher';

        $membership = DB::transaction(function () use ($invite, $user, $membershipRole) {
            $membership = TeacherMembership::query()->firstOrNew([
                'school_id' => $invite->school_id,
                'user_id' => $user->id,
            ]);

            if (! $membership->exists) {
                $membership->id = (string) Str::uuid();
            }

            $membership->role = $membershipRole;
            $membership->status = 'active';
            $membership->save();

            $user->update(['role' => $invite->role]);

            $invite->update([
                'status' => 'accepted',
                'accepted_at' => now(),
            ]);

            return $membership;
        });

        $membership->load(['school', 'user:id,name,email,role']);

        return response()->json(['data' => $membership]);
    }

    public function lookupStudentByDocument(Request $request, School $school): JsonResponse
    {
        if (! $this->assertBelongsToSchool($request->user(), $school)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'document_type' => ['nullable', 'string', 'max:32'],
            'document_number' => ['required', 'string', 'max:64'],
        ]);

        $documentNumber = \App\Support\PersonIdentity::normalizeDocumentNumber($validated['document_number']);

        $query = User::query()
            ->where('role', 'hijo')
            ->where('document_number', $documentNumber);

        if (! empty($validated['document_type'])) {
            $query->where('document_type', strtoupper($validated['document_type']));
        }

        $students = $query
            ->get(['id', 'name', 'email', 'role', 'family_id', 'document_type', 'document_number', 'phone', 'birthdate']);

        return response()->json(['data' => $students]);
    }

    public function enrollByDocument(Request $request, School $school): JsonResponse
    {
        if (! $this->assertBelongsToSchool($request->user(), $school)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'document_number' => ['required', 'string', 'max:64'],
            'document_type' => ['nullable', 'string', 'max:32'],
            'school_class_id' => [
                'required',
                'uuid',
                Rule::exists('school_classes', 'id')->where('school_id', $school->id),
            ],
        ]);

        $documentNumber = \App\Support\PersonIdentity::normalizeDocumentNumber($validated['document_number']);

        $studentQuery = User::query()
            ->where('role', 'hijo')
            ->where('document_number', $documentNumber);

        if (! empty($validated['document_type'])) {
            $studentQuery->where('document_type', strtoupper($validated['document_type']));
        }

        $student = $studentQuery->first();

        if ($student === null) {
            return response()->json(['message' => 'Estudiante no encontrado con ese documento'], 404);
        }

        if ($student->family_id === null) {
            return response()->json(['message' => 'El estudiante no tiene familia asociada'], 422);
        }

        $class = SchoolClass::query()->findOrFail($validated['school_class_id']);

        $enrollment = ClassEnrollment::query()->firstOrNew([
            'school_class_id' => $class->id,
            'student_user_id' => $student->id,
        ]);

        if (! $enrollment->exists) {
            $enrollment->id = (string) Str::uuid();
        }

        $enrollment->family_id = $student->family_id;
        $enrollment->enrolled_by = $request->user()->id;
        $enrollment->status = 'active';
        $enrollment->save();

        $enrollment->load(['schoolClass', 'student:id,name,document_type,document_number']);

        return response()->json(['data' => $enrollment], 201);
    }

    public function updateStudentDocument(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_user_id' => ['required', 'integer', 'exists:users,id'],
            'document_type' => ['required', 'string', 'max:32'],
            'document_number' => ['required', 'string', 'max:64'],
        ]);

        $actor = $request->user();
        $student = User::query()->findOrFail($validated['student_user_id']);

        if ($student->role !== 'hijo') {
            return response()->json(['message' => 'El usuario no es un estudiante'], 422);
        }

        $isStaff = TeacherMembership::query()
            ->where('user_id', $actor->id)
            ->where('status', 'active')
            ->exists();

        $isGuardian = $this->guardians->isGuardianOf($actor, (int) $student->id)
            || (
                $actor->family_id !== null
                && (string) $actor->family_id === (string) $student->family_id
                && in_array($actor->role, ['padre', 'madre', 'tutor'], true)
            );

        if (! $isStaff && ! $isGuardian) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $student->update([
            'document_type' => $validated['document_type'],
            'document_number' => $validated['document_number'],
        ]);

        return response()->json([
            'data' => $student->only(['id', 'name', 'document_type', 'document_number']),
        ]);
    }

    public function listGroups(Request $request, School $school): JsonResponse
    {
        if (! $this->assertBelongsToSchool($request->user(), $school)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $groups = SchoolGroup::query()
            ->with(['members.user:id,name,email,role'])
            ->withCount(['activeMembers as members_count'])
            ->where('school_id', $school->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $groups]);
    }

    public function storeGroup(Request $request, School $school): JsonResponse
    {
        if (! $this->assertManagesSchool($request->user(), $school)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'type' => ['required', Rule::in(['students', 'teachers', 'mixed'])],
            'campus_id' => ['nullable', 'uuid', Rule::exists('school_campuses', 'id')->where('school_id', $school->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'exists:users,id'],
            'school_class_id' => [
                'nullable',
                'uuid',
                Rule::exists('school_classes', 'id')->where('school_id', $school->id),
            ],
        ]);

        $group = DB::transaction(function () use ($request, $school, $validated) {
            $group = SchoolGroup::query()->create([
                'id' => (string) Str::uuid(),
                'school_id' => $school->id,
                'campus_id' => $validated['campus_id'] ?? null,
                'name' => $validated['name'],
                'type' => $validated['type'],
                'description' => $validated['description'] ?? null,
                'created_by' => $request->user()->id,
                'is_active' => true,
            ]);

            $memberIds = $validated['member_ids'] ?? [];
            if ($memberIds === [] && ! empty($validated['school_class_id'])) {
                $memberIds = ClassEnrollment::query()
                    ->where('school_class_id', $validated['school_class_id'])
                    ->where('status', 'active')
                    ->pluck('student_user_id')
                    ->all();
            }

            foreach ($memberIds as $memberId) {
                SchoolGroupMember::query()->create([
                    'id' => (string) Str::uuid(),
                    'school_group_id' => $group->id,
                    'user_id' => (int) $memberId,
                    'member_role' => 'member',
                    'status' => 'active',
                ]);
            }

            return $group;
        });

        $group->load(['members.user:id,name,email,role']);

        return response()->json(['data' => $group], 201);
    }

    public function syncGroupMembers(
        Request $request,
        School $school,
        SchoolGroup $group,
        SyncSchoolGroupMembersAction $action,
    ): JsonResponse {
        if (! $this->assertManagesSchool($request->user(), $school)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ((string) $group->school_id !== (string) $school->id) {
            return response()->json(['message' => 'Grupo no pertenece a esta institución'], 404);
        }

        $validated = $request->validate([
            'member_ids' => ['present', 'array'],
            'member_ids.*' => ['integer', 'exists:users,id'],
            'school_class_id' => [
                'nullable',
                'uuid',
                Rule::exists('school_classes', 'id')->where('school_id', $school->id),
            ],
        ]);

        $group = $action->execute(
            $group,
            $validated['member_ids'],
            $validated['school_class_id'] ?? null,
        );

        return response()->json(['data' => $group]);
    }

    public function announce(Request $request, School $school): JsonResponse
    {
        if (! $this->assertBelongsToSchool($request->user(), $school)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'type' => ['required', Rule::in(['announcement', 'no_class', 'activity', 'citation'])],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'campus_id' => ['nullable', 'uuid', Rule::exists('school_campuses', 'id')->where('school_id', $school->id)],
            'school_class_id' => ['nullable', 'uuid', Rule::exists('school_classes', 'id')->where('school_id', $school->id)],
            'school_group_id' => ['nullable', 'uuid', Rule::exists('school_groups', 'id')->where('school_id', $school->id)],
            'target_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $announcement = SchoolAnnouncement::query()->create([
            'id' => (string) Str::uuid(),
            'school_id' => $school->id,
            'campus_id' => $validated['campus_id'] ?? null,
            'school_class_id' => $validated['school_class_id'] ?? null,
            'school_group_id' => $validated['school_group_id'] ?? null,
            'target_user_id' => $validated['target_user_id'] ?? null,
            'created_by' => $request->user()->id,
            'type' => $validated['type'],
            'title' => $validated['title'],
            'body' => $validated['body'] ?? null,
        ]);

        $recipientIds = $this->resolveRecipientUserIds(
            $school,
            $validated['campus_id'] ?? null,
            $validated['school_class_id'] ?? null,
            $validated['school_group_id'] ?? null,
            $validated['target_user_id'] ?? null,
        );

        $this->notifications->notifyUsersAcrossFamilies(
            (int) $request->user()->id,
            $recipientIds,
            'school_'.$validated['type'],
            $validated['title'],
            $validated['body'] ?? $validated['title'],
            [
                'entity_type' => 'school_announcement',
                'entity_id' => $announcement->id,
                'school_id' => $school->id,
            ],
        );

        return response()->json(['data' => $announcement], 201);
    }

    public function storeMeeting(Request $request, School $school): JsonResponse
    {
        if (! $this->assertBelongsToSchool($request->user(), $school)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'campus_id' => ['nullable', 'uuid', Rule::exists('school_campuses', 'id')->where('school_id', $school->id)],
            'school_class_id' => ['nullable', 'uuid', Rule::exists('school_classes', 'id')->where('school_id', $school->id)],
            'school_group_id' => ['nullable', 'uuid', Rule::exists('school_groups', 'id')->where('school_id', $school->id)],
        ]);

        $meeting = DB::transaction(function () use ($request, $school, $validated) {
            $meeting = SchoolMeeting::query()->create([
                'id' => (string) Str::uuid(),
                'school_id' => $school->id,
                'campus_id' => $validated['campus_id'] ?? null,
                'school_class_id' => $validated['school_class_id'] ?? null,
                'school_group_id' => $validated['school_group_id'] ?? null,
                'created_by' => $request->user()->id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'starts_at' => $validated['starts_at'],
                'ends_at' => $validated['ends_at'] ?? null,
                'location' => $validated['location'] ?? null,
                'status' => 'scheduled',
            ]);

            $parentIds = $this->resolveRecipientUserIds(
                $school,
                $validated['campus_id'] ?? null,
                $validated['school_class_id'] ?? null,
                $validated['school_group_id'] ?? null,
                null,
            );

            foreach ($parentIds as $parentId) {
                SchoolMeetingRsvp::query()->create([
                    'id' => (string) Str::uuid(),
                    'school_meeting_id' => $meeting->id,
                    'user_id' => $parentId,
                    'status' => 'pending',
                ]);
            }

            return [$meeting, $parentIds];
        });

        /** @var SchoolMeeting $meetingModel */
        [$meetingModel, $parentIds] = $meeting;

        $this->notifications->notifyUsersAcrossFamilies(
            (int) $request->user()->id,
            $parentIds,
            'school_meeting',
            $validated['title'],
            $validated['description'] ?? 'Nueva reunión escolar',
            [
                'entity_type' => 'school_meeting',
                'entity_id' => $meetingModel->id,
                'school_id' => $school->id,
            ],
        );

        $meetingModel->load('rsvps');

        return response()->json(['data' => $meetingModel], 201);
    }

    public function respondMeeting(Request $request, SchoolMeeting $meeting): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['attending', 'not_attending'])],
            'observation' => [
                Rule::requiredIf(fn () => $request->input('status') === 'not_attending'),
                'nullable',
                'string',
                'min:3',
            ],
        ]);

        $user = $request->user();

        $rsvp = SchoolMeetingRsvp::query()
            ->where('school_meeting_id', $meeting->id)
            ->where('user_id', $user->id)
            ->first();

        if ($rsvp === null) {
            return response()->json(['message' => 'No tienes RSVP para esta reunión'], 403);
        }

        $rsvp->update([
            'status' => $validated['status'],
            'observation' => $validated['observation'] ?? null,
            'responded_at' => now(),
        ]);

        return response()->json(['data' => $rsvp->fresh()]);
    }

    public function myMeetings(Request $request, ListMySchoolMeetingsQuery $query): JsonResponse
    {
        $meetings = $query->handle($request->user());

        return response()->json([
            'data' => $meetings->map(function (SchoolMeeting $m) {
                $mine = $m->rsvps->first();

                return [
                    'id' => $m->id,
                    'title' => $m->title,
                    'description' => $m->description,
                    'starts_at' => $m->starts_at?->toIso8601String(),
                    'ends_at' => $m->ends_at?->toIso8601String(),
                    'location' => $m->location,
                    'status' => $m->status,
                    'school' => $m->school === null ? null : [
                        'id' => $m->school->id,
                        'name' => $m->school->name,
                    ],
                    'creator' => $m->creator === null ? null : [
                        'id' => $m->creator->id,
                        'name' => $m->creator->name,
                    ],
                    'my_rsvp' => $mine === null ? null : [
                        'id' => $mine->id,
                        'status' => $mine->status,
                        'observation' => $mine->observation,
                        'responded_at' => $mine->responded_at?->toIso8601String(),
                    ],
                ];
            })->values(),
        ]);
    }

    public function listMeetings(Request $request, School $school): JsonResponse
    {
        if (! $this->assertBelongsToSchool($request->user(), $school)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $meetings = SchoolMeeting::query()
            ->with(['rsvps.user:id,name,email', 'creator:id,name'])
            ->where('school_id', $school->id)
            ->orderByDesc('starts_at')
            ->get();

        return response()->json(['data' => $meetings]);
    }

    public function storeSchedule(
        StoreSchoolScheduleRequest $request,
        School $school,
        StoreSchoolScheduleAction $action,
    ): JsonResponse {
        if (! $this->assertBelongsToSchool($request->user(), $school)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $schedule = $action->handle($school, $request->user(), $request->validated());

        return response()->json(['data' => $schedule], 201);
    }

    public function updateSchedule(
        UpdateSchoolScheduleRequest $request,
        SchoolSchedule $schedule,
        UpdateSchoolScheduleAction $action,
    ): JsonResponse {
        $user = $request->user();

        if ((int) $schedule->created_by !== (int) $user->id) {
            return response()->json(['message' => 'Solo el creador puede editar este horario'], 403);
        }

        return response()->json([
            'data' => $action->handle($schedule, $request->validated()),
        ]);
    }

    public function shareSchedule(Request $request, SchoolSchedule $schedule): JsonResponse
    {
        $user = $request->user();
        $school = School::query()->findOrFail($schedule->school_id);

        if (! $this->assertBelongsToSchool($user, $school)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ((int) $schedule->created_by !== (int) $user->id && ! $this->assertManagesSchool($user, $school)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'school_group_id' => [
                'nullable',
                'uuid',
                Rule::exists('school_groups', 'id')->where('school_id', $school->id),
                'required_without:user_id',
            ],
            'user_id' => ['nullable', 'integer', 'exists:users,id', 'required_without:school_group_id'],
            'permission' => ['nullable', Rule::in(['view'])],
        ]);

        $share = SchoolScheduleShare::query()->create([
            'id' => (string) Str::uuid(),
            'school_schedule_id' => $schedule->id,
            'school_group_id' => $validated['school_group_id'] ?? null,
            'user_id' => $validated['user_id'] ?? null,
            'permission' => $validated['permission'] ?? 'view',
        ]);

        return response()->json(['data' => $share], 201);
    }

    public function listSchedules(Request $request, School $school): JsonResponse
    {
        if (! $this->assertBelongsToSchool($request->user(), $school)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $user = $request->user();

        $schedules = SchoolSchedule::query()
            ->with('shares')
            ->where('school_id', $school->id)
            ->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                    ->orWhereHas('shares', function ($shareQuery) use ($user) {
                        $shareQuery->where('user_id', $user->id)
                            ->orWhereIn('school_group_id', function ($sub) use ($user) {
                                $sub->select('school_group_id')
                                    ->from('school_group_members')
                                    ->where('user_id', $user->id)
                                    ->where('status', 'active');
                            });
                    });
            })
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $schedules]);
    }

    public function reportSick(Request $request, School $school): JsonResponse
    {
        if (! $this->assertBelongsToSchool($request->user(), $school)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'student_user_id' => ['required', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'phone_contact_failed' => ['required', 'boolean'],
        ]);

        $student = User::query()->findOrFail($validated['student_user_id']);
        if ($student->role !== 'hijo') {
            return response()->json(['message' => 'El usuario no es un estudiante'], 422);
        }

        $alert = SchoolHealthAlert::query()->create([
            'id' => (string) Str::uuid(),
            'school_id' => $school->id,
            'student_user_id' => $student->id,
            'created_by' => $request->user()->id,
            'type' => 'sick',
            'title' => $validated['title'],
            'body' => $validated['body'] ?? null,
            'phone_contact_failed' => $validated['phone_contact_failed'],
            'occurred_at' => now(),
        ]);

        $guardianIds = $this->guardians->guardianIdsOfChild((int) $student->id);

        $this->notifications->notifyUsersAcrossFamilies(
            (int) $request->user()->id,
            $guardianIds,
            'school_sick',
            $validated['title'],
            $validated['body'] ?? $validated['title'],
            [
                'entity_type' => 'school_health_alert',
                'entity_id' => $alert->id,
                'child_id' => $student->id,
                'school_id' => $school->id,
            ],
        );

        return response()->json(['data' => $alert], 201);
    }

    public function citeParents(Request $request, School $school): JsonResponse
    {
        if (! $this->assertBelongsToSchool($request->user(), $school)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'student_user_ids' => ['nullable', 'array', 'required_without_all:school_class_id,school_group_id'],
            'student_user_ids.*' => ['integer', 'exists:users,id'],
            'school_class_id' => [
                'nullable',
                'uuid',
                Rule::exists('school_classes', 'id')->where('school_id', $school->id),
                'required_without_all:student_user_ids,school_group_id',
            ],
            'school_group_id' => [
                'nullable',
                'uuid',
                Rule::exists('school_groups', 'id')->where('school_id', $school->id),
                'required_without_all:student_user_ids,school_class_id',
            ],
        ]);

        $announcement = SchoolAnnouncement::query()->create([
            'id' => (string) Str::uuid(),
            'school_id' => $school->id,
            'school_class_id' => $validated['school_class_id'] ?? null,
            'school_group_id' => $validated['school_group_id'] ?? null,
            'created_by' => $request->user()->id,
            'type' => 'citation',
            'title' => $validated['title'],
            'body' => $validated['body'],
            'data' => [
                'student_user_ids' => $validated['student_user_ids'] ?? [],
            ],
        ]);

        $recipientIds = [];

        if (! empty($validated['student_user_ids'])) {
            foreach ($validated['student_user_ids'] as $studentId) {
                $recipientIds = array_merge(
                    $recipientIds,
                    $this->guardians->guardianIdsOfChild((int) $studentId),
                );
            }
        } else {
            $recipientIds = $this->resolveRecipientUserIds(
                $school,
                null,
                $validated['school_class_id'] ?? null,
                $validated['school_group_id'] ?? null,
                null,
            );
        }

        $recipientIds = array_values(array_unique(array_map('intval', $recipientIds)));

        $this->notifications->notifyUsersAcrossFamilies(
            (int) $request->user()->id,
            $recipientIds,
            'school_citation',
            $validated['title'],
            $validated['body'],
            [
                'entity_type' => 'school_announcement',
                'entity_id' => $announcement->id,
                'school_id' => $school->id,
            ],
        );

        return response()->json(['data' => $announcement], 201);
    }

    public function storeTeacherTask(Request $request, School $school): JsonResponse
    {
        if (! $this->assertBelongsToSchool($request->user(), $school)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'school_group_id' => ['nullable', 'uuid', Rule::exists('school_groups', 'id')->where('school_id', $school->id)],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
        ]);

        $task = SchoolTeacherTask::query()->create([
            'id' => (string) Str::uuid(),
            'school_id' => $school->id,
            'school_group_id' => $validated['school_group_id'] ?? null,
            'created_by' => $request->user()->id,
            'assigned_to' => $validated['assigned_to'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status' => 'pending',
            'due_date' => $validated['due_date'] ?? null,
        ]);

        return response()->json(['data' => $task], 201);
    }

    public function listTeacherTasks(Request $request, School $school): JsonResponse
    {
        if (! $this->assertBelongsToSchool($request->user(), $school)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $tasks = SchoolTeacherTask::query()
            ->where('school_id', $school->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $tasks]);
    }

    public function updateTeacherTask(Request $request, SchoolTeacherTask $task): JsonResponse
    {
        $school = School::query()->findOrFail($task->school_id);

        if (! $this->assertBelongsToSchool($request->user(), $school)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in(['pending', 'in_progress', 'done', 'cancelled'])],
        ]);

        $task->update(['status' => $validated['status']]);

        return response()->json(['data' => $task->fresh()]);
    }

    public function storePsychCase(Request $request, School $school): JsonResponse
    {
        if (! $this->assertBelongsToSchool($request->user(), $school)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'student_user_id' => ['required', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string'],
            'visibility' => ['required', Rule::in(['staff', 'guardians'])],
        ]);

        $student = User::query()->findOrFail($validated['student_user_id']);
        if ($student->role !== 'hijo') {
            return response()->json(['message' => 'El usuario no es un estudiante'], 422);
        }

        $case = SchoolPsychCase::query()->create([
            'id' => (string) Str::uuid(),
            'school_id' => $school->id,
            'student_user_id' => $student->id,
            'created_by' => $request->user()->id,
            'title' => $validated['title'],
            'summary' => $validated['summary'] ?? null,
            'status' => 'open',
            'visibility' => $validated['visibility'],
        ]);

        return response()->json(['data' => $case], 201);
    }

    public function addPsychNote(Request $request, SchoolPsychCase $case): JsonResponse
    {
        $school = School::query()->findOrFail($case->school_id);

        if (! $this->assertBelongsToSchool($request->user(), $school)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1'],
            'notify_guardians' => ['nullable', 'boolean'],
        ]);

        $note = SchoolPsychNote::query()->create([
            'id' => (string) Str::uuid(),
            'school_psych_case_id' => $case->id,
            'created_by' => $request->user()->id,
            'body' => $validated['body'],
            'notify_guardians' => (bool) ($validated['notify_guardians'] ?? false),
        ]);

        if ($note->notify_guardians || $case->visibility === 'guardians') {
            $guardianIds = $this->guardians->guardianIdsOfChild((int) $case->student_user_id);
            $this->notifications->notifyUsersAcrossFamilies(
                (int) $request->user()->id,
                $guardianIds,
                'school_psych_note',
                'Actualización de orientación',
                $validated['body'],
                [
                    'entity_type' => 'school_psych_case',
                    'entity_id' => $case->id,
                    'child_id' => $case->student_user_id,
                    'school_id' => $school->id,
                ],
            );
        }

        return response()->json(['data' => $note], 201);
    }

    public function listPsychCases(Request $request, School $school): JsonResponse
    {
        if (! $this->assertBelongsToSchool($request->user(), $school)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $cases = SchoolPsychCase::query()
            ->with(['student:id,name', 'notes'])
            ->where('school_id', $school->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $cases]);
    }

    public function storeHealthAlert(Request $request, School $school): JsonResponse
    {
        if (! $this->assertBelongsToSchool($request->user(), $school)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'student_user_id' => ['required', 'integer', 'exists:users,id'],
            'type' => ['required', Rule::in(['health', 'sick', 'weekend'])],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'occurred_at' => ['nullable', 'date'],
            'phone_contact_failed' => ['nullable', 'boolean'],
        ]);

        $student = User::query()->findOrFail($validated['student_user_id']);
        if ($student->role !== 'hijo') {
            return response()->json(['message' => 'El usuario no es un estudiante'], 422);
        }

        $alert = SchoolHealthAlert::query()->create([
            'id' => (string) Str::uuid(),
            'school_id' => $school->id,
            'student_user_id' => $student->id,
            'created_by' => $request->user()->id,
            'type' => $validated['type'],
            'title' => $validated['title'],
            'body' => $validated['body'] ?? null,
            'phone_contact_failed' => (bool) ($validated['phone_contact_failed'] ?? false),
            'occurred_at' => $validated['occurred_at'] ?? now(),
        ]);

        $guardianIds = $this->guardians->guardianIdsOfChild((int) $student->id);
        $this->notifications->notifyUsersAcrossFamilies(
            (int) $request->user()->id,
            $guardianIds,
            'school_'.$validated['type'],
            $validated['title'],
            $validated['body'] ?? $validated['title'],
            [
                'entity_type' => 'school_health_alert',
                'entity_id' => $alert->id,
                'child_id' => $student->id,
                'school_id' => $school->id,
            ],
        );

        return response()->json(['data' => $alert], 201);
    }

    public function listHealthAlerts(Request $request, School $school): JsonResponse
    {
        if (! $this->assertBelongsToSchool($request->user(), $school)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $alerts = SchoolHealthAlert::query()
            ->with(['student:id,name'])
            ->where('school_id', $school->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $alerts]);
    }

    public function schoolSubscription(Request $request, School $school): JsonResponse
    {
        if (! $this->assertBelongsToSchool($request->user(), $school)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $subscription = SchoolSubscription::query()->firstOrCreate(
            ['school_id' => $school->id],
            [
                'id' => (string) Str::uuid(),
                'plan_code' => 'school',
                'status' => 'active',
                'billing' => 'monthly',
            ],
        );

        return response()->json([
            'data' => $this->schoolSubscriptionPayload(
                $subscription,
                $school,
                canManage: $this->assertManagesSchool($request->user(), $school),
            ),
        ]);
    }

    public function updateSchoolSubscription(Request $request, School $school): JsonResponse
    {
        if (! $this->assertManagesSchool($request->user(), $school)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $schoolCodes = \App\Support\SubscriptionPlanCatalog::codesForAudience(
            \App\Support\SubscriptionPlanCatalog::AUDIENCE_SCHOOL,
        );

        $validated = $request->validate([
            'plan_code' => ['sometimes', Rule::in($schoolCodes)],
            'status' => ['sometimes', Rule::in(['active', 'trialing', 'past_due', 'canceled', 'inactive'])],
            'billing' => ['sometimes', Rule::in(['monthly', 'yearly'])],
            'renew_months' => ['sometimes', 'integer', 'min:1', 'max:24'],
        ]);

        $subscription = SchoolSubscription::query()->firstOrCreate(
            ['school_id' => $school->id],
            [
                'id' => (string) Str::uuid(),
                'plan_code' => 'school_trial',
                'status' => 'trialing',
                'billing' => 'monthly',
            ],
        );

        if (isset($validated['plan_code'])) {
            $subscription->plan_code = $validated['plan_code'];
            $school->forceFill(['plan' => $validated['plan_code']])->save();
            if ($validated['plan_code'] === 'school_trial') {
                $subscription->status = 'trialing';
            } elseif (in_array($subscription->status, ['inactive', 'canceled', 'trialing'], true)) {
                $subscription->status = 'active';
            }
        }
        if (isset($validated['status'])) {
            $subscription->status = $validated['status'];
        }
        if (isset($validated['billing'])) {
            $subscription->billing = $validated['billing'];
        }
        if (isset($validated['renew_months'])) {
            $base = $subscription->current_period_end !== null && $subscription->current_period_end->isFuture()
                ? $subscription->current_period_end
                : now();
            $subscription->current_period_end = $base->copy()->addMonths((int) $validated['renew_months']);
            if ($subscription->status !== 'trialing') {
                $subscription->status = 'active';
            }
        }

        $subscription->save();

        return response()->json([
            'data' => $this->schoolSubscriptionPayload($subscription->fresh(), $school, canManage: true),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function schoolSubscriptionPayload(
        SchoolSubscription $subscription,
        School $school,
        bool $canManage,
    ): array {
        \App\Support\SubscriptionPlanCatalog::ensureSeeded();
        $planCode = (string) $subscription->plan_code;
        $featuresMap = \App\Support\SubscriptionPlanCatalog::featuresFor($planCode);
        $available = [];
        foreach (\App\Support\SubscriptionPlanCatalog::codesForAudience(
            \App\Support\SubscriptionPlanCatalog::AUDIENCE_SCHOOL,
        ) as $code) {
            $def = \App\Support\SubscriptionPlanCatalog::definitionFor($code);
            if ($def === null) {
                continue;
            }
            $available[] = [
                'code' => $code,
                'name' => $def['name'],
                'price_monthly_cents' => $def['price_monthly_cents'],
                'price_yearly_cents' => $def['price_yearly_cents'],
                'features' => $def['features'],
                'highlights' => $def['features']['highlights'] ?? [],
            ];
        }

        return [
            ...$subscription->toArray(),
            'can_manage' => $canManage,
            'label' => \App\Support\SubscriptionPlanCatalog::labelFor($planCode),
            'features' => $this->subscriptionFeatures($planCode),
            'feature_flags' => $featuresMap,
            'available_plans' => $available,
        ];
    }

    public function overview(Request $request, School $school): JsonResponse
    {
        if (! $this->assertBelongsToSchool($request->user(), $school)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $membership = TeacherMembership::query()
            ->where('user_id', $request->user()->id)
            ->where('school_id', $school->id)
            ->where('status', 'active')
            ->first();

        $subscription = SchoolSubscription::query()->where('school_id', $school->id)->first();

        $classIds = SchoolClass::query()->where('school_id', $school->id)->pluck('id');
        $studentsCount = ClassEnrollment::query()
            ->whereIn('school_class_id', $classIds)
            ->where('status', 'active')
            ->count();

        return response()->json([
            'data' => [
                'school' => $school->only(['id', 'name', 'code', 'city', 'plan', 'is_active']),
                'membership' => $membership === null ? null : [
                    'id' => $membership->id,
                    'role' => $membership->role,
                    'status' => $membership->status,
                ],
                'subscription' => $subscription === null ? null : [
                    'id' => $subscription->id,
                    'plan_code' => $subscription->plan_code,
                    'status' => $subscription->status,
                    'billing' => $subscription->billing,
                    'current_period_end' => $subscription->current_period_end,
                    'label' => $this->subscriptionLabel($subscription->plan_code),
                ],
                'can_manage' => $membership !== null && $membership->role === 'admin',
                'counts' => [
                    'campuses' => SchoolCampus::query()->where('school_id', $school->id)->count(),
                    'staff' => TeacherMembership::query()
                        ->where('school_id', $school->id)
                        ->where('status', 'active')
                        ->count(),
                    'classes' => $classIds->count(),
                    'groups' => SchoolGroup::query()
                        ->where('school_id', $school->id)
                        ->where('is_active', true)
                        ->count(),
                    'meetings' => SchoolMeeting::query()->where('school_id', $school->id)->count(),
                    'students' => $studentsCount,
                    'pending_invites' => SchoolStaffInvite::query()
                        ->where('school_id', $school->id)
                        ->where('status', 'pending')
                        ->count(),
                ],
            ],
        ]);
    }

    private function subscriptionLabel(string $planCode): string
    {
        return \App\Support\SubscriptionPlanCatalog::labelFor($planCode);
    }

    /** @return list<string> */
    private function subscriptionFeatures(string $planCode): array
    {
        $highlights = \App\Support\SubscriptionPlanCatalog::featuresFor($planCode)['highlights'] ?? null;
        if (is_array($highlights) && $highlights !== []) {
            return array_values(array_filter(array_map(
                static fn ($item) => is_string($item) ? $item : null,
                $highlights,
            )));
        }

        return [
            'Panel admin y docentes',
            'Sedes y grupos',
            'Comunicación a familias',
            'Reuniones y horarios',
        ];
    }

    /**
     * Gestión: membresía admin del colegio O administrador de plataforma.
     */
    private function assertManagesSchool(User $user, School $school): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return TeacherMembership::query()
            ->where('user_id', $user->id)
            ->where('school_id', $school->id)
            ->where('status', 'active')
            ->where('role', 'admin')
            ->exists();
    }

    private function assertBelongsToSchool(User $user, School $school): bool
    {
        if ($user->isPlatformAdmin()) {
            return true;
        }

        return TeacherMembership::query()
            ->where('user_id', $user->id)
            ->where('school_id', $school->id)
            ->where('status', 'active')
            ->exists();
    }

    /** @return list<string> */
    private function schoolIdsFor(User $user): array
    {
        if ($user->isPlatformAdmin()) {
            return School::query()->pluck('id')->map(fn ($id) => (string) $id)->all();
        }

        return TeacherMembership::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->pluck('school_id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    /**
     * Resuelve destinatarios de aviso: padres de alumnos afectados,
     * miembros de grupo (no-hijo) o un usuario puntual.
     *
     * @return list<int>
     */
    private function resolveRecipientUserIds(
        School $school,
        ?string $campusId,
        ?string $schoolClassId,
        ?string $schoolGroupId,
        ?int $targetUserId,
    ): array {
        $studentIds = [];
        $directUserIds = [];

        if ($targetUserId !== null) {
            $target = User::query()->find($targetUserId);
            if ($target !== null) {
                if ($target->role === 'hijo') {
                    $studentIds[] = (int) $target->id;
                } else {
                    $directUserIds[] = (int) $target->id;
                }
            }
        }

        if ($schoolGroupId !== null) {
            $memberIds = SchoolGroupMember::query()
                ->where('school_group_id', $schoolGroupId)
                ->where('status', 'active')
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $members = User::query()->whereIn('id', $memberIds)->get(['id', 'role']);
            foreach ($members as $member) {
                if ($member->role === 'hijo') {
                    $studentIds[] = (int) $member->id;
                } else {
                    $directUserIds[] = (int) $member->id;
                }
            }
        }

        if ($schoolClassId !== null) {
            $classStudentIds = ClassEnrollment::query()
                ->where('school_class_id', $schoolClassId)
                ->where('status', 'active')
                ->pluck('student_user_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $studentIds = array_merge($studentIds, $classStudentIds);
        }

        if ($campusId !== null && $schoolClassId === null && $schoolGroupId === null && $targetUserId === null) {
            $classIds = SchoolClass::query()
                ->where('school_id', $school->id)
                ->where('campus_id', $campusId)
                ->pluck('id')
                ->all();

            if ($classIds !== []) {
                $campusStudentIds = ClassEnrollment::query()
                    ->whereIn('school_class_id', $classIds)
                    ->where('status', 'active')
                    ->pluck('student_user_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
                $studentIds = array_merge($studentIds, $campusStudentIds);
            }
        }

        $recipientIds = $directUserIds;
        foreach (array_unique($studentIds) as $studentId) {
            $recipientIds = array_merge(
                $recipientIds,
                $this->guardians->guardianIdsOfChild((int) $studentId),
            );
        }

        return array_values(array_unique(array_map('intval', $recipientIds)));
    }
}
