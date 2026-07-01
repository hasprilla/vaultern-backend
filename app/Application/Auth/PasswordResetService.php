<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Mail\ResetPasswordMail;
use App\Models\PasswordResetCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class PasswordResetService
{
    public function send(string $email): void
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null || $user->trashed()) {
            return;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        PasswordResetCode::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'code_hash'  => Hash::make($code),
                'expires_at' => now()->addMinutes(30),
            ],
        );

        try {
            Mail::to($user->email)->send(new ResetPasswordMail($user->name, $code));
        } catch (\Throwable $e) {
            Log::error('No se pudo enviar correo de recuperación', [
                'user_id' => $user->id,
                'email'   => $user->email,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    public function reset(string $email, string $code, string $password): void
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            throw ValidationException::withMessages(['email' => 'No encontramos una cuenta con este email.']);
        }

        $record = PasswordResetCode::query()->where('user_id', $user->id)->first();

        if ($record === null || $record->expires_at->isPast()) {
            throw ValidationException::withMessages(['code' => 'El código expiró. Solicita uno nuevo.']);
        }

        if (! Hash::check($code, $record->code_hash)) {
            throw ValidationException::withMessages(['code' => 'Código incorrecto.']);
        }

        $user->update(['password' => $password]);
        $record->delete();
    }
}
