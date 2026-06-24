<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TenantMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = Auth::user();

        if (!$user || !$user->family_id) {
            return response()->json(['error' => 'Tenant not found'], 403);
        }

        // Bind family_id to all queries automatically
        app()->instance('tenant_id', $user->family_id);

        return $next($request);
    }
}
