<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\JoinFamilyRequest;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\Api\V1\SessionResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Infrastructure\Auth\TokenService;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(private readonly TokenService $tokens) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $family = Family::query()->create([
            'id'   => (string) Str::uuid(),
            'name' => $request->validated('name').' Family',
            'plan' => 'free',
        ]);

        $user = User::query()->create([
            'name'      => $request->validated('name'),
            'email'     => $request->validated('email'),
            'password'  => $request->validated('password'),
            'role'      => $request->validated('role'),
            'family_id' => $family->id,
        ]);

        FamilyMember::query()->create([
            'id'        => (string) Str::uuid(),
            'family_id' => $family->id,
            'user_id'   => $user->id,
            'role'      => $user->role,
            'status'    => 'active',
        ]);

        $tokenData = $this->tokens->issue($user);

        return response()->json([
            'data' => new SessionResource([
                ...$tokenData,
                'user' => $user,
            ]),
        ], 201);
    }

    public function join(JoinFamilyRequest $request): JsonResponse
    {
        $family = Family::query()
            ->where('invite_code', strtoupper($request->validated('invite_code')))
            ->first();

        if ($family === null) {
            return response()->json(['message' => 'Código de invitación inválido'], 422);
        }

        $user = User::query()->create([
            'name'      => $request->validated('name'),
            'email'     => $request->validated('email'),
            'password'  => $request->validated('password'),
            'role'      => $request->validated('role'),
            'family_id' => $family->id,
        ]);

        FamilyMember::query()->create([
            'id'        => (string) Str::uuid(),
            'family_id' => $family->id,
            'user_id'   => $user->id,
            'role'      => $request->validated('role'),
            'status'    => 'active',
        ]);

        $tokenData = $this->tokens->issue($user);

        return response()->json([
            'data' => new SessionResource([
                ...$tokenData,
                'user' => $user,
            ]),
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        if ($user === null || ! Hash::check($request->validated('password'), $user->password)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($request->validated('device_id')) {
            $user->update(['device_fingerprint' => $request->validated('device_id')]);
        }

        $tokenData = $this->tokens->issue($user);

        return response()->json([
            'data' => new SessionResource([
                ...$tokenData,
                'user' => $user,
            ]),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->tokens->revoke($request->user());

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => new UserResource($request->user())]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $refresh = $request->input('refresh_token');

        if (! is_string($refresh)) {
            return response()->json(['message' => 'Refresh token required'], 422);
        }

        $tokenData = $this->tokens->refresh($refresh);

        if ($tokenData === null) {
            return response()->json(['message' => 'Invalid refresh token'], 401);
        }

        $user = $this->tokens->findUserByToken($tokenData['access_token']);

        return response()->json([
            'data' => new SessionResource([
                ...$tokenData,
                'user' => $user,
            ]),
        ]);
    }
}
