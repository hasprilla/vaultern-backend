<?php

declare(strict_types=1);

namespace App\Application\Family\Actions;

use App\Models\Family;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\ChildGuardianService;
use App\Services\FamilyNotificationService;
use App\Services\PlanFeatureService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @phpstan-type RegisterChildSuccess array{ok: true, child: User}
 * @phpstan-type RegisterChildFailure array{ok: false, status: int, message: string, code?: string}
 */
final class RegisterChildAction
{
    public function __construct(
        private readonly ChildGuardianService $guardians,
        private readonly PlanFeatureService $planFeatures,
        private readonly FamilyNotificationService $notifications,
    ) {}

    /**
     * @param  array{name: string, guardian_ids?: array<int>|null}  $validated
     * @return RegisterChildSuccess|RegisterChildFailure
     */
    public function execute(User $actor, Family $family, array $validated): array
    {
        $childrenCount = User::query()
            ->where('family_id', $family->id)
            ->where('role', 'hijo')
            ->count();
        $maxChildren = $this->planFeatures->familyFeatureLimit($family, 'max_children', 2);

        if ($childrenCount >= $maxChildren) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => "Tu plan permite hasta {$maxChildren} hijos. Mejora tu plan para agregar más.",
                'code' => 'children_limit_reached',
            ];
        }

        $child = User::query()->create([
            'name' => $validated['name'],
            'email' => 'hijo.'.(string) Str::uuid().'@zumifly.internal',
            'password' => Hash::make(Str::random(32)),
            'role' => 'hijo',
            'family_id' => $family->id,
        ]);

        FamilyMember::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $family->id,
            'user_id' => $child->id,
            'role' => 'hijo',
            'status' => 'active',
        ]);

        $guardianIds = [];
        if ($actor->isFamilyOwner()) {
            $requested = array_map('intval', $validated['guardian_ids'] ?? []);
            if ($requested !== []) {
                $guardianIds = FamilyMember::query()
                    ->where('family_id', $family->id)
                    ->where('status', 'active')
                    ->whereIn('role', ['padre', 'madre', 'tutor'])
                    ->whereIn('user_id', $requested)
                    ->pluck('user_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
            }
        }
        $this->guardians->syncForChild($child, $guardianIds, $actor);
        $child->load('guardians');

        $this->notifications->notifyChildGuardians(
            $actor,
            (int) $child->id,
            'family_child',
            'Nuevo hijo/a registrado',
            "{$actor->name} registró a {$child->name}",
            ['entity_type' => 'user', 'entity_id' => (string) $child->id],
        );

        return ['ok' => true, 'child' => $child];
    }
}
