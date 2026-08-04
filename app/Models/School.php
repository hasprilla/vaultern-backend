<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class School extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'code',
        'plan',
        'city',
        'main_campus_id',
        'created_by',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (School $school) {
            if (empty($school->code)) {
                $school->code = strtoupper(Str::random(8));
            }
        });
    }

    /** @return HasMany<SchoolClass> */
    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    /** @return HasMany<TeacherMembership> */
    public function teachers(): HasMany
    {
        return $this->hasMany(TeacherMembership::class);
    }

    /** @return HasMany<SchoolCampus> */
    public function campuses(): HasMany
    {
        return $this->hasMany(SchoolCampus::class);
    }

    /** @return BelongsTo<SchoolCampus, $this> */
    public function mainCampus(): BelongsTo
    {
        return $this->belongsTo(SchoolCampus::class, 'main_campus_id');
    }

    /** @return HasOne<SchoolSubscription> */
    public function subscription(): HasOne
    {
        return $this->hasOne(SchoolSubscription::class);
    }

    /** @return HasMany<SchoolGroup> */
    public function groups(): HasMany
    {
        return $this->hasMany(SchoolGroup::class);
    }
}
