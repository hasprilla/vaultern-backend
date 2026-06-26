<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
}
