<?php

declare(strict_types=1);

namespace App\Application\Profile\Actions;

use App\Models\User;
use App\Services\Mfa\TotpService;

/**
 * @phpstan-type SetupSuccess array{ok: true, secret: string, provisioning_uri: string}
 * @phpstan-type SetupFailure array{ok: false, status: int, message: string}
 */
final class SetupMfaAction
{
    public function __construct(
        private readonly TotpService $totp,
    ) {}

    /**
     * @return SetupSuccess|SetupFailure
     */
    public function execute(User $user): array
    {
        if ($user->mfa_enabled) {
            return ['ok' => false, 'status' => 422, 'message' => 'MFA ya está activo.'];
        }

        $secret = $this->totp->generateSecret();
        $user->update(['mfa_secret' => $secret]);

        return [
            'ok' => true,
            'secret' => $secret,
            'provisioning_uri' => $this->totp->provisioningUri($secret, $user->email),
        ];
    }
}
