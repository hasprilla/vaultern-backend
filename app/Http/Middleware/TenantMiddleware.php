<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TenantMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user === null || $user->family_id === null) {
            return response()->json(['error' => 'Tenant not found'], 403);
        }

        app()->instance('tenant_id', $user->family_id);

        return $next($request);
    }
}
