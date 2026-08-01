<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Infrastructure\Auth\TokenService;
use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Support\Str;

trait AuthenticatesUsers
{
    protected function createUserWithFamily(array $userAttrs = [], array $familyAttrs = []): array
    {
        $family = Family::query()->create([
            'id'   => (string) Str::uuid(),
            'name' => 'Familia Test',
            'plan' => 'free',
            ...$familyAttrs,
        ]);

        $user = User::factory()->create([
            'family_id' => $family->id,
            'role'      => 'padre',
            ...$userAttrs,
        ]);

        if ($family->owner_user_id === null) {
            $family->update(['owner_user_id' => $user->id]);
            $family->refresh();
        }

        FamilyMember::query()->create([
            'id'        => (string) Str::uuid(),
            'family_id' => $family->id,
            'user_id'   => $user->id,
            'role'      => $user->role,
            'status'    => 'active',
        ]);

        $tokens = app(TokenService::class)->issue($user);

        return compact('user', 'family', 'tokens');
    }

    protected function authHeaders(array $tokens): array
    {
        return ['Authorization' => 'Bearer '.$tokens['access_token']];
    }
}
