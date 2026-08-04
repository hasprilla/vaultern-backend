<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use App\Support\PersonIdentity;
use App\Support\SchemaCompat;
use Illuminate\Support\Str;

final class RegisterUserAction
{
    public function __construct(
        private readonly DeviceRegistrationService $devices,
        private readonly EmailVerificationService $emailVerification,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{email: string, delivery: array{code: string, delivered: bool, channels: array{push: bool, mail: bool}}}
     */
    public function execute(array $input): array
    {
        $email = $input['email'];
        $person = PersonIdentity::extract($input);
        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null && $existing->email_verified_at === null) {
            $existing->update(array_merge([
                'name' => $input['name'],
                'password' => $input['password'],
                'role' => $input['role'],
            ], $this->personAttrs($person)));

            FamilyMember::query()
                ->where('user_id', $existing->id)
                ->update(['role' => $existing->role]);

            $this->registerDevice($input, $existing);
            $delivery = $this->emailVerification->send($existing);

            return ['email' => $existing->email, 'delivery' => $delivery];
        }

        $family = Family::query()->create([
            'id' => (string) Str::uuid(),
            'name' => $input['name'].' Family',
            'plan' => 'free',
        ]);

        $user = User::query()->create(array_merge([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'role' => $input['role'],
            'family_id' => $family->id,
        ], $this->personAttrs($person)));

        if (SchemaCompat::hasColumn('families', 'owner_user_id')) {
            $family->update(['owner_user_id' => $user->id]);
        }

        FamilyMember::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $family->id,
            'user_id' => $user->id,
            'role' => $user->role,
            'status' => 'active',
        ]);

        $this->registerDevice($input, $user);
        $delivery = $this->emailVerification->send($user);

        return ['email' => $user->email, 'delivery' => $delivery];
    }

    /**
     * @param  array{document_type: ?string, document_number: ?string, phone: ?string, birthdate: ?string, address: ?string}  $person
     * @return array<string, mixed>
     */
    private function personAttrs(array $person): array
    {
        $attrs = [];
        foreach (['document_type', 'document_number', 'phone', 'birthdate', 'address'] as $key) {
            if (! SchemaCompat::hasColumn('users', $key)) {
                continue;
            }
            if ($person[$key] !== null) {
                $attrs[$key] = $person[$key];
            }
        }

        return $attrs;
    }

    /**
     * @param  array{device_id?: string|null, platform?: string|null, fcm_token?: string|null}  $input
     */
    private function registerDevice(array $input, User $user): void
    {
        $deviceId = $input['device_id'] ?? null;
        if (! is_string($deviceId) || $deviceId === '') {
            return;
        }

        $this->devices->register(
            $user,
            $deviceId,
            $input['platform'] ?? null,
            $input['fcm_token'] ?? null,
        );
    }
}
