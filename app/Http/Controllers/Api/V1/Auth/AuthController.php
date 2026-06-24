<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        // TODO: Implement actual registration logic via Domain Service
        return response()->json([
            'data' => [
                'access_token' => 'dummy_token',
                'refresh_token' => 'dummy_refresh',
                'user' => [
                    'id' => 'user-1',
                    'name' => $request->validated('name'),
                    'email' => $request->validated('email'),
                    'role' => $request->validated('role'),
                    'family_id' => 'fam-1',
                ]
            ]
        ]);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        // TODO: Implement actual login logic via Domain Service
        if ($request->validated('email') === 'nobody@zumifly.app') {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return response()->json([
            'data' => [
                'access_token' => 'dummy_token',
                'refresh_token' => 'dummy_refresh',
                'user' => [
                    'id' => 'user-1',
                    'name' => 'Harvey Asprilla',
                    'email' => $request->validated('email'),
                    'role' => 'padre',
                    'family_id' => 'fam-1',
                ]
            ]
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        // TODO: Revoke token
        return response()->json(['message' => 'Logged out successfully']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user()]);
    }
}
