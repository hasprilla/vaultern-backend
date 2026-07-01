<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Application\Auth\DeviceRegistrationService;
use App\Application\Auth\EmailVerificationService;
use App\Application\Auth\PasswordResetService;
use App\Application\Family\FamilyJoinRequestService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\Auth\JoinFamilyRequest;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Requests\Api\V1\Auth\ResendVerificationRequest;
use App\Http\Requests\Api\V1\Auth\ResetPasswordRequest;
use App\Http\Requests\Api\V1\Auth\VerifyEmailRequest;
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
    public function __construct(
        private readonly TokenService $tokens,
        private readonly DeviceRegistrationService $devices,
        private readonly FamilyJoinRequestService $joinRequests,
        private readonly EmailVerificationService $emailVerification,
        private readonly PasswordResetService $passwordReset,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $email = $request->validated('email');
        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null && $existing->email_verified_at === null) {
            $existing->update([
                'name'     => $request->validated('name'),
                'password' => $request->validated('password'),
                'role'     => $request->validated('role'),
            ]);

            FamilyMember::query()
                ->where('user_id', $existing->id)
                ->update(['role' => $existing->role]);

            $this->registerDevice($request, $existing);
            $this->emailVerification->send($existing);

            return response()->json([
                'message' => 'Te enviamos un código de verificación por notificación push.',
                'data'    => [
                    'requires_verification' => true,
                    'email'                 => $existing->email,
                ],
            ], 201);
        }

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

        $this->registerDevice($request, $user);
        $this->emailVerification->send($user);

        return response()->json([
            'message' => 'Te enviamos un código de verificación por notificación push.',
            'data'    => [
                'requires_verification' => true,
                'email'                 => $user->email,
            ],
        ], 201);
    }

    public function verifyEmail(VerifyEmailRequest $request): JsonResponse
    {
        $user = $this->emailVerification->verify(
            $request->validated('email'),
            $request->validated('code'),
        );

        $tokenData = $this->tokens->issue($user);

        return response()->json([
            'data' => new SessionResource([
                ...$tokenData,
                'user' => $user,
            ]),
        ]);
    }

    public function resendVerification(ResendVerificationRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        if ($user === null) {
            return response()->json(['message' => 'Si el email existe, enviaremos un nuevo código.']);
        }

        if ($user->email_verified_at !== null) {
            return response()->json(['message' => 'Esta cuenta ya está verificada.']);
        }

        $this->registerDevice($request, $user);
        $this->emailVerification->send($user);

        return response()->json(['message' => 'Código reenviado por notificación push.']);
    }

    public function join(JoinFamilyRequest $request): JsonResponse
    {
        $family = Family::query()
            ->where('invite_code', strtoupper($request->validated('invite_code')))
            ->first();

        if ($family === null) {
            return response()->json(['message' => 'Código de invitación inválido'], 422);
        }

        $inviter = User::query()->findOrFail($request->validated('invited_by'));

        $joinRequest = $this->joinRequests->submit(
            $family,
            $inviter,
            $request->validated('name'),
            $request->validated('email'),
            $request->validated('password'),
            $request->validated('role'),
        );

        return response()->json([
            'message' => 'Solicitud enviada. El padre o madre que te invitó debe aprobarla.',
            'data'    => [
                'request_id' => $joinRequest->id,
                'status'     => 'pending',
                'inviter'    => $inviter->only(['id', 'name']),
            ],
        ], 202);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->passwordReset->send($request->validated('email'));

        return response()->json([
            'message' => 'Si el email existe, enviaremos un código para restablecer tu contraseña.',
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $this->passwordReset->reset(
            $validated['email'],
            $validated['code'],
            $validated['password'],
        );

        return response()->json([
            'message' => 'Contraseña restablecida. Ya puedes iniciar sesión.',
        ]);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        if ($user === null || ! Hash::check($request->validated('password'), $user->password)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($user->role === 'hijo') {
            return response()->json([
                'message' => 'Los hijos no acceden a la app. Solo padres y madres gestionan la familia.',
            ], 403);
        }

        if ($user->email_verified_at === null) {
            return response()->json([
                'message' => 'Confirma tu correo con el código enviado al registrarte.',
                'code'    => 'email_not_verified',
            ], 403);
        }

        if ($user->account_status === 'deactivated') {
            return response()->json([
                'message' => 'Tu cuenta está desactivada temporalmente. Puedes reactivarla desde el inicio de sesión.',
                'code'    => 'account_deactivated',
            ], 403);
        }

        if ($user->trashed() || $user->account_status === 'deleted') {
            return response()->json(['message' => 'Esta cuenta fue eliminada.'], 403);
        }

        if ($request->validated('device_id')) {
            $this->devices->register(
                $user,
                $request->validated('device_id'),
                $request->validated('platform'),
                $request->validated('fcm_token'),
            );
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

    private function registerDevice(RegisterRequest|ResendVerificationRequest $request, User $user): void
    {
        $deviceId = $request->validated('device_id');
        if (! is_string($deviceId) || $deviceId === '') {
            return;
        }

        $this->devices->register(
            $user,
            $deviceId,
            $request->validated('platform'),
            $request->validated('fcm_token'),
        );
    }
}
