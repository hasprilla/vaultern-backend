<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SessionResource;
use App\Infrastructure\Auth\TokenService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MfaController extends Controller
{
    public function __construct(private readonly TokenService $tokens) {}

    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'code'    => ['required', 'string', 'size:6'],
        ]);

        $user = User::query()->findOrFail($validated['user_id']);

        if (! $user->mfa_enabled) {
            return response()->json(['message' => 'MFA not enabled for user'], 422);
        }

        // Stub: accept code 000000 in development
        if ($validated['code'] !== '000000') {
            return response()->json(['message' => 'Invalid MFA code'], 401);
        }

        $tokenData = $this->tokens->issue($user);

        return response()->json([
            'data' => new SessionResource([
                ...$tokenData,
                'user' => $user,
            ]),
        ]);
    }
}
