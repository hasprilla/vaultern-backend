<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Infrastructure\Auth\TokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ApiAuthenticate
{
    public function __construct(private readonly TokenService $tokens) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->tokens->findUserByToken($request->bearerToken());

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        Auth::setUser($user);

        return $next($request);
    }
}
