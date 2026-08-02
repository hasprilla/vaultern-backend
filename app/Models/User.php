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
        'avatar',
        'mfa_enabled',
        'mfa_secret',
        'device_fingerprint',
        'account_status',
        'deactivated_at',
        'notification_preferences',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'mfa_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'        => 'datetime',
            'deactivated_at'           => 'datetime',
            'password'                 => 'hashed',
            'mfa_enabled'              => 'boolean',
            'mfa_secret'               => 'encrypted',
            'notification_preferences' => 'array',
        ];
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

    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    public function familyRole(): FamilyRole
    {
        return FamilyRole::from($this->role);
    }

    public function canManageFinances(): bool
    {
        return $this->familyRole()->canManageFinances();
    }

    public function canManageTasks(): bool
    {
        return $this->familyRole()->canManageTasks();
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
        return $this->familyRole()->canManageSchool();
    }

    public function isSupportAgent(): bool
    {
        return $this->familyRole()->isSupport();
    }

    public function isSchoolStaff(): bool
    {
        return $this->familyRole()->canBroadcastSchoolTasks();
    }

    public function bypassesFamilyTenant(): bool
    {
        return $this->familyRole()->bypassesFamilyTenant();
    }

    /** @return HasMany<TeacherMembership> */
    public function teacherMemberships(): HasMany
    {
        return $this->hasMany(TeacherMembership::class);
    }
}
