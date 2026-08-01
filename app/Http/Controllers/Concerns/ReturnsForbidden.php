<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

trait ReturnsForbidden
{
    /** Mantiene el contrato API `{ "message": "Forbidden" }` con status 403. */
    protected function forbidUnlessAuthorized(string $ability, mixed $arguments = []): ?JsonResponse
    {
        if (Gate::allows($ability, $arguments)) {
            return null;
        }

        return response()->json(['message' => 'Forbidden'], 403);
    }
}
