<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CurriculumBlock extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'curriculum_profile_id',
        'sort_order',
        'start_time',
        'end_time',
        'kind',
        'label',
    ];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    /** @return BelongsTo<CurriculumProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(CurriculumProfile::class, 'curriculum_profile_id');
    }
}
