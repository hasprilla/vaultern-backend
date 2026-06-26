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

        if ($user->trashed() || $user->account_status === 'deleted') {
            return response()->json(['message' => 'Esta cuenta fue eliminada.'], 403);
        }

        if ($user->account_status === 'deactivated') {
            return response()->json([
                'message' => 'Tu cuenta está desactivada temporalmente.',
                'code'    => 'account_deactivated',
            ], 403);
        }

        Auth::setUser($user);

        return $next($request);
    }
}
