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

        if ($user === null) {
            return response()->json(['error' => 'Tenant not found'], 403);
        }

        if ($user->bypassesFamilyTenant()) {
            app()->instance('tenant_id', null);
            app()->instance('is_support_agent', $user->isSupportAgent());
            app()->instance('is_school_staff', $user->isSchoolStaff());

            return $next($request);
        }

        if ($user->family_id === null) {
            return response()->json(['error' => 'Tenant not found'], 403);
        }

        app()->instance('tenant_id', $user->family_id);

        return $next($request);
    }
}
