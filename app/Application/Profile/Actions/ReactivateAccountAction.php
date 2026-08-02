<?php

declare(strict_types=1);

namespace App\Application\Profile\Actions;

use App\Infrastructure\Auth\TokenService;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\FamilyNotificationService;
use Illuminate\Support\Facades\Hash;

/**
 * @phpstan-type ReactivateSuccess array{ok: true, user: User, tokens: array<string, mixed>}
 * @phpstan-type ReactivateFailure array{ok: false, status: int, message: string}
 */
final class ReactivateAccountAction
{
    public function __construct(
        private readonly FamilyNotificationService $notifications,
        private readonly TokenService $tokens,
    ) {}

    /**
     * @param  array{email: string, password: string}  $credentials
     * @return ReactivateSuccess|ReactivateFailure
     */
    public function execute(array $credentials): array
    {
        $user = User::query()->where('email', $credentials['email'])->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            return ['ok' => false, 'status' => 401, 'message' => 'Credenciales inválidas.'];
        }

        if ($user->account_status !== 'deactivated') {
            return ['ok' => false, 'status' => 422, 'message' => 'Esta cuenta no está desactivada.'];
        }

        $user->update([
            'account_status' => 'active',
            'deactivated_at' => null,
        ]);

        FamilyMember::query()
            ->where('user_id', $user->id)
            ->update(['status' => 'active']);

        if ($user->family_id !== null) {
            $this->notifications->notifyFamily(
                $user,
                'profile_updated',
                'Cuenta reactivada',
                "{$user->name} reactivó su cuenta",
                ['entity_type' => 'user', 'entity_id' => (string) $user->id],
            );
        }

        $tokens = $this->tokens->issue($user);

        return [
            'ok' => true,
            'user' => $user->fresh() ?? $user,
            'tokens' => $tokens,
        ];
    }
}
