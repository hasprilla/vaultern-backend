<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Family;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToFamily
{
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    protected static function bootBelongsToFamily(): void
    {
        static::addGlobalScope('family', function (Builder $builder) {
            if ($tenantId = app()->bound('tenant_id') ? app('tenant_id') : null) {
                $builder->where($builder->getModel()->getTable().'.family_id', $tenantId);
            }
        });
    }
}
