<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Family\Entities\FamilyRole;
use App\Support\NotificationPreferences;
use App\Support\SchemaCompat;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'family_id',
        'role',
        'document_type',
        'document_number',
        'phone',
        'birthdate',
        'address',
        'avatar',
        'mfa_enabled',
        'mfa_secret',
        'device_fingerprint',
        'device_secret_hash',
        'security_question_key',
        'security_answer_hash',
        'device_secret_must_rotate',
        'account_status',
        'deactivated_at',
        'notification_preferences',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'mfa_secret',
        'device_secret_hash',
        'security_answer_hash',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'          => 'datetime',
            'deactivated_at'             => 'datetime',
            'birthdate'                  => 'date',
            'password'                   => 'hashed',
            'mfa_enabled'                => 'boolean',
            'mfa_secret'                 => 'encrypted',
            'device_secret_must_rotate'  => 'boolean',
            'notification_preferences'   => 'array',
        ];
    }

    public function isPlatformAdmin(): bool
    {
        return $this->role === FamilyRole::ADMIN->value
            || ($this->familyRoleSafe()?->isPlatformAdmin() ?? false);
    }

    public function familyRoleSafe(): ?FamilyRole
    {
        return FamilyRole::tryFrom((string) $this->role);
    }

    public function hasDeviceRecoveryConfigured(): bool
    {
        // Sin migración aún en cPanel: no forzar ni desafiar.
        if (! SchemaCompat::hasColumn('users', 'device_secret_hash')) {
            return false;
        }

        return is_string($this->device_secret_hash)
            && $this->device_secret_hash !== ''
            && is_string($this->security_question_key)
            && $this->security_question_key !== ''
            && is_string($this->security_answer_hash)
            && $this->security_answer_hash !== '';
    }

    public function mustSetupDeviceRecovery(): bool
    {
        if (! SchemaCompat::hasColumn('users', 'device_secret_hash')) {
            return false;
        }

        return ! $this->hasDeviceRecoveryConfigured();
    }

    public function mustRotateDeviceSecret(): bool
    {
        if (! SchemaCompat::hasColumn('users', 'device_secret_must_rotate')) {
            return false;
        }

        return (bool) $this->device_secret_must_rotate;
    }

    /** @return array<string, bool> */
    public function resolvedNotificationPreferences(): array
    {
        return NotificationPreferences::merge($this->notification_preferences);
    }

    public function isActive(): bool
    {
        return $this->account_status === 'active' && ! $this->trashed();
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function familyMemberships(): HasMany
    {
        return $this->hasMany(FamilyMember::class);
    }

    /** Hijos de los que este padre/madre/tutor es custodio. */
    public function guardedChildren(): HasMany
    {
        return $this->hasMany(ChildGuardian::class, 'parent_user_id');
    }

    /** Padres/madres/tutores vinculados a este hijo. */
    public function guardians(): HasMany
    {
        return $this->hasMany(ChildGuardian::class, 'child_user_id');
    }

    /** @return list<int> */
    public function linkedChildIds(): array
    {
        return $this->guardedChildren()->pluck('child_user_id')->map(fn ($id) => (int) $id)->all();
    }

    public function isGuardianOf(int $childUserId): bool
    {
        return $this->guardedChildren()->where('child_user_id', $childUserId)->exists();
    }

    /** Cache request-scoped para evitar doble query en middleware. */
    private ?bool $activeFamilyMembershipCache = null;

    private ?bool $isFamilyOwnerCache = null;

    /** Dueño de la membresía/familia: ve toda la información y otorga permisos de visibilidad. */
    public function isFamilyOwner(): bool
    {
        if ($this->isFamilyOwnerCache !== null) {
            return $this->isFamilyOwnerCache;
        }

        if ($this->family_id === null) {
            return $this->isFamilyOwnerCache = false;
        }

        $resolveFirstParentId = function () {
            return self::query()
                ->where('family_id', $this->family_id)
                ->whereIn('role', ['padre', 'madre'])
                ->orderBy('id')
                ->value('id');
        };

        // Compat cPanel: columna puede no existir hasta correr migrate.
        if (! SchemaCompat::hasColumn('families', 'owner_user_id')) {
            return $this->isFamilyOwnerCache = (int) $resolveFirstParentId() === (int) $this->id;
        }

        $ownerId = Family::query()
            ->where('id', $this->family_id)
            ->value('owner_user_id');

        if ($ownerId !== null) {
            return $this->isFamilyOwnerCache = (int) $ownerId === (int) $this->id;
        }

        // Columna existe pero está vacía: primer padre/madre es dueño y se auto-asigna.
        $resolved = $resolveFirstParentId();
        $isOwner = $resolved !== null && (int) $resolved === (int) $this->id;
        if ($isOwner) {
            Family::query()
                ->where('id', $this->family_id)
                ->whereNull('owner_user_id')
                ->update(['owner_user_id' => $this->id]);
        }

        return $this->isFamilyOwnerCache = $isOwner;
    }

    /** Membresía activa en su núcleo familiar actual (sin borrar datos al desactivar). */
    public function hasActiveFamilyMembership(): bool
    {
        if ($this->activeFamilyMembershipCache !== null) {
            return $this->activeFamilyMembershipCache;
        }

        if ($this->family_id === null || $this->bypassesFamilyTenant()) {
            return $this->activeFamilyMembershipCache = true;
        }

        if (! SchemaCompat::hasTable('family_members')) {
            return $this->activeFamilyMembershipCache = true;
        }

        return $this->activeFamilyMembershipCache = FamilyMember::query()
            ->where('user_id', $this->id)
            ->where('family_id', $this->family_id)
            ->where('status', 'active')
            ->exists();
    }

    /** True si tiene al menos una membresía activa en cualquier núcleo. */
    public function hasAnyActiveFamilyMembership(): bool
    {
        if ($this->bypassesFamilyTenant()) {
            return true;
        }

        if (! SchemaCompat::hasTable('family_members')) {
            return $this->family_id !== null;
        }

        return FamilyMember::query()
            ->where('user_id', $this->id)
            ->where('status', 'active')
            ->exists();
    }

    /**
     * Si el núcleo actual está inactivo, apunta a otro núcleo activo del usuario.
     * No toca account_status: desactivar un núcleo ≠ desactivar la cuenta.
     *
     * @return bool True si quedó con un núcleo activo (o no aplica tenant).
     */
    public function ensureActiveFamilyContext(): bool
    {
        $this->activeFamilyMembershipCache = null;

        if ($this->bypassesFamilyTenant()) {
            return true;
        }

        if (! SchemaCompat::hasTable('family_members')) {
            return $this->family_id !== null;
        }

        if ($this->hasActiveFamilyMembership()) {
            return true;
        }

        $other = FamilyMember::query()
            ->where('user_id', $this->id)
            ->where('status', 'active')
            ->orderBy('joined_at')
            ->first();

        if ($other === null) {
            return false;
        }

        $this->forceFill([
            'family_id' => $other->family_id,
            'role' => $other->role,
        ])->save();

        $this->activeFamilyMembershipCache = true;

        return true;
    }

    /** Invalida cache de membresía (p. ej. tras desactivar en un núcleo). */
    public function clearFamilyMembershipCache(): void
    {
        $this->activeFamilyMembershipCache = null;
        $this->isFamilyOwnerCache = null;
    }

    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    public function familyRole(): FamilyRole
    {
        return FamilyRole::tryFrom((string) $this->role) ?? FamilyRole::PADRE;
    }

    public function canManageFinances(): bool
    {
        if ($this->isPlatformAdmin()) {
            return true;
        }
        // Techo del plan familiar; el dueño puede bajar permisos por miembro.
        if (! $this->familyPlanAllowsModule('finances')) {
            return false;
        }

        $override = $this->modulePermissionOverride('can_finances');
        if ($override !== null) {
            return $override;
        }

        return $this->familyRole()->canManageFinances();
    }

    public function canManageTasks(): bool
    {
        if ($this->isPlatformAdmin()) {
            return true;
        }
        if (! $this->familyPlanAllowsModule('tasks')) {
            return false;
        }

        $override = $this->modulePermissionOverride('can_tasks');
        if ($override !== null) {
            return $override;
        }

        return $this->familyRole()->canManageTasks();
    }

    /** El plan comprado habilita el módulo; sin familia no hay módulos familiares. */
    private function familyPlanAllowsModule(string $module): bool
    {
        if ($this->family_id === null) {
            return false;
        }

        $family = $this->relationLoaded('family')
            ? $this->family
            : Family::query()->find($this->family_id);

        if ($family === null) {
            return false;
        }

        return app(\App\Services\PlanFeatureService::class)->familyAllowsModule($family, $module);
    }

    /** Override de módulo en family_members (null = usar rol). */
    private function modulePermissionOverride(string $column): ?bool
    {
        if ($this->family_id === null || ! SchemaCompat::hasColumn('family_members', $column)) {
            return null;
        }

        $value = FamilyMember::query()
            ->where('family_id', $this->family_id)
            ->where('user_id', $this->id)
            ->where('status', 'active')
            ->value($column);

        return $value === null ? null : (bool) $value;
    }

    public function canManageSupportTickets(): bool
    {
        return $this->familyRole()->canManageSupportTickets();
    }

    public function canBroadcastSchoolTasks(): bool
    {
        return $this->familyRole()->canBroadcastSchoolTasks();
    }

    public function canManageSchool(): bool
    {
        return $this->isPlatformAdmin() || $this->familyRole()->canManageSchool();
    }

    public function isSupportAgent(): bool
    {
        return $this->isPlatformAdmin() || $this->familyRole()->isSupport();
    }

    public function isSchoolStaff(): bool
    {
        return $this->isPlatformAdmin() || $this->familyRole()->canBroadcastSchoolTasks();
    }

    public function bypassesFamilyTenant(): bool
    {
        return $this->isPlatformAdmin() || $this->familyRole()->bypassesFamilyTenant();
    }

    /** @return HasMany<TeacherMembership> */
    public function teacherMemberships(): HasMany
    {
        return $this->hasMany(TeacherMembership::class);
    }
}
