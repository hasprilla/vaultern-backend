<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait ResolvesPagination
{
    protected function perPage(Request $request, int $default = 20, int $max = 50): int
    {
        $perPage = (int) $request->input('per_page', $default);

        return max(1, min($perPage, $max));
    }
}
