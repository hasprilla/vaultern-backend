<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceEncryptedTransport
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('app.allow_insecure_http')) {
            return $next($request);
        }

        if (! $request->secure() && ! $this->isForwardedSecure($request)) {
            return response()->json([
                'message' => 'HTTPS required. All API traffic must use encrypted transport.',
            ], 426);
        }

        return $next($request);
    }

    private function isForwardedSecure(Request $request): bool
    {
        return $request->header('X-Forwarded-Proto') === 'https';
    }
}
