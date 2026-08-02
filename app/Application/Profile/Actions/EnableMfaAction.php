<?php

declare(strict_types=1);

namespace App\Application\Profile\Actions;

use App\Models\User;
use App\Services\Mfa\TotpService;

/**
 * @phpstan-type EnableSuccess array{ok: true}
 * @phpstan-type EnableFailure array{ok: false, status: int, message: string}
 */
final class EnableMfaAction
{
    public function __construct(
        private readonly TotpService $totp,
    ) {}

    /**
     * @return EnableSuccess|EnableFailure
     */
    public function execute(User $user, string $code): array
    {
        if ($user->mfa_secret === null) {
            return ['ok' => false, 'status' => 422, 'message' => 'Primero configura MFA con setup.'];
        }

        if (! $this->totp->verify($user->mfa_secret, $code)) {
            return ['ok' => false, 'status' => 422, 'message' => 'Código incorrecto.'];
        }

        $user->update(['mfa_enabled' => true]);

        return ['ok' => true];
    }
}
