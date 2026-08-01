<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Mail\VerifyEmailMail;
use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Services\Fcm\FcmPushService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class EmailVerificationService
{
    public function __construct(private readonly FcmPushService $fcm) {}

    public function send(User $user): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        EmailVerificationCode::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'code_hash'  => Hash::make($code),
                'expires_at' => now()->addMinutes(30),
            ],
        );

        $this->fcm->sendVerificationCode($user, $code);

        // Docker/local: sin FCM/SMTP reales el OTP solo queda en logs.
        if (app()->environment(['local', 'testing']) || (bool) config('app.debug')) {
            Log::info('OTP verificación (dev)', [
                'email' => $user->email,
                'code'  => $code,
            ]);
        }

        try {
            Mail::to($user->email)->send(new VerifyEmailMail($user->name, $code));
        } catch (\Throwable $e) {
            Log::error('No se pudo enviar correo de verificación', [
                'user_id' => $user->id,
                'email'   => $user->email,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    public function verify(string $email, string $code): User
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            throw ValidationException::withMessages(['email' => 'No encontramos una cuenta con este email.']);
        }

        if ($user->email_verified_at !== null) {
            return $user;
        }

        $record = EmailVerificationCode::query()->where('user_id', $user->id)->first();

        if ($record === null || $record->expires_at->isPast()) {
            throw ValidationException::withMessages(['code' => 'El código expiró. Solicita uno nuevo.']);
        }

        if (! Hash::check($code, $record->code_hash)) {
            throw ValidationException::withMessages(['code' => 'Código incorrecto.']);
        }

        $user->forceFill(['email_verified_at' => now()])->save();
        $record->delete();

        return $user->fresh();
    }
}
