<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Application\Auth\DeviceRegistrationService;
use App\Application\Auth\EmailVerificationService;
use App\Application\Auth\JoinFamilyAction;
use App\Application\Auth\LoginUserAction;
use App\Application\Auth\PasswordResetService;
use App\Application\Auth\RegisterUserAction;
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
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly TokenService $tokens,
        private readonly DeviceRegistrationService $devices,
        private readonly EmailVerificationService $emailVerification,
        private readonly PasswordResetService $passwordReset,
        private readonly LoginUserAction $loginUser,
        private readonly RegisterUserAction $registerUser,
        private readonly JoinFamilyAction $joinFamily,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->registerUser->execute($request->validated());

        return response()->json(
            $this->verificationResponse($result['email'], $result['delivery']),
            201,
        );
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
        $delivery = $this->emailVerification->send($user);
        $payload = $this->verificationResponse($user->email, $delivery);
        $payload['message'] = $delivery['delivered']
            ? 'Código reenviado por push y por correo electrónico.'
            : 'No pudimos enviar push ni correo. Usa el código que te mostramos en la app.';

        return response()->json($payload);
    }

    public function join(JoinFamilyRequest $request): JsonResponse
    {
        $result = $this->joinFamily->execute($request->validated());

        if (($result['ok'] ?? false) !== true) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        return response()->json([
            'message' => 'Solicitud enviada. El padre o madre que te invitó debe aprobarla.',
            'data' => [
                'request_id' => $result['joinRequest']->id,
                'status' => 'pending',
                'inviter' => $result['inviter']->only(['id', 'name']),
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
        $result = $this->loginUser->execute($request->validated());

        if (($result['ok'] ?? false) !== true) {
            $payload = ['message' => $result['message']];
            if (isset($result['code'])) {
                $payload['code'] = $result['code'];
            }
            if (isset($result['data'])) {
                $payload['data'] = $result['data'];
            }

            return response()->json($payload, $result['status']);
        }

        return response()->json([
            'data' => new SessionResource([
                ...$result['tokens'],
                'user' => $result['user'],
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

    /**
     * @param  array{code: string, delivered: bool, channels: array{push: bool, mail: bool}}  $delivery
     * @return array{message: string, data: array<string, mixed>}
     */
    private function verificationResponse(string $email, array $delivery): array
    {
        $data = [
            'requires_verification' => true,
            'email'                 => $email,
            'delivery'              => $delivery['channels'],
        ];

        // Si push y correo fallan, devolvemos el OTP para no bloquear el onboarding (cPanel sin SMTP/FCM).
        if (! $delivery['delivered']) {
            $data['otp'] = $delivery['code'];
            $data['otp_fallback'] = true;
        }

        return [
            'message' => $delivery['delivered']
                ? 'Te enviamos un código por notificación push y por correo electrónico.'
                : 'No pudimos enviar el código por push ni correo. Úsalo en pantalla para continuar.',
            'data'    => $data,
        ];
    }
}
