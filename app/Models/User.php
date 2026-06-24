<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Family\Entities\FamilyRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

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
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'mfa_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'mfa_enabled'       => 'boolean',
            'mfa_secret'        => 'encrypted',
            'device_fingerprint'=> 'encrypted',
        ];
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function familyMemberships(): HasMany
    {
        return $this->hasMany(FamilyMember::class);
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
}
