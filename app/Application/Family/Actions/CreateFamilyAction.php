<?php

declare(strict_types=1);

namespace App\Application\Family\Actions;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * @phpstan-type CreateFamilySuccess array{ok: true, family: Family}
 * @phpstan-type CreateFamilyFailure array{ok: false, status: int, message: string}
 */
final class CreateFamilyAction
{
    /**
     * @param  array{name: string, plan?: string|null}  $validated
     * @return CreateFamilySuccess|CreateFamilyFailure
     */
    public function execute(User $user, array $validated): array
    {
        if ($user->family_id !== null) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'User already belongs to a family',
            ];
        }

        $family = Family::query()->create([
            'id' => (string) Str::uuid(),
            'name' => $validated['name'],
            'plan' => $validated['plan'] ?? 'free',
            'owner_user_id' => $user->id,
        ]);

        FamilyMember::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $family->id,
            'user_id' => $user->id,
            'role' => $user->role,
            'status' => 'active',
        ]);

        $user->update(['family_id' => $family->id]);

        return ['ok' => true, 'family' => $family];
    }
}
