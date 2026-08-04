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
     * @param  array{name: string, guardian_ids?: array<int>|null, document_type?: string, document_number?: string, phone?: string|null, birthdate?: string|null, address?: string|null}  $validated
     * @return RegisterChildSuccess|RegisterChildFailure
     */
    public function execute(User $actor, Family $family, array $validated): array
    {
        $person = \App\Support\PersonIdentity::extract($validated);

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

        if (empty($person['document_number']) || empty($person['document_type'])) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'El tipo y número de documento son obligatorios para matricular y buscar al alumno.',
                'code' => 'document_required',
            ];
        }

        $requested = array_values(array_unique(array_map('intval', $validated['guardian_ids'] ?? [])));
        $guardianIds = [];
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

        // Si hay mamás en el núcleo, al menos una debe quedar como custodia
        // (o el actor ya es la mamá). Así no se notifican cosas de hijos ajenos.
        $motherIds = FamilyMember::query()
            ->where('family_id', $family->id)
            ->where('status', 'active')
            ->where('role', 'madre')
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $effectiveIds = array_values(array_unique(array_merge(
            $guardianIds,
            [(int) $actor->id],
        )));
        $hasMotherAssigned = count(array_intersect($effectiveIds, $motherIds)) > 0;
        $otherMothers = array_values(array_filter(
            $motherIds,
            static fn (int $id): bool => $id !== (int) $actor->id,
        ));

        if ($otherMothers !== [] && ! $hasMotherAssigned) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'Debes indicar la mamá de este hijo para asignar la custodia correctamente.',
                'code' => 'mother_required',
            ];
        }

        $childPayload = [
            'name' => $validated['name'],
            'email' => 'hijo.'.(string) Str::uuid().'@zumifly.internal',
            'password' => Hash::make(Str::random(32)),
            'role' => 'hijo',
            'family_id' => $family->id,
            'document_type' => $person['document_type'],
            'document_number' => $person['document_number'],
        ];
        if ($person['phone'] !== null) {
            $childPayload['phone'] = $person['phone'];
        }
        if ($person['birthdate'] !== null) {
            $childPayload['birthdate'] = $person['birthdate'];
        }
        if ($person['address'] !== null) {
            $childPayload['address'] = $person['address'];
        }

        $child = User::query()->create($childPayload);

        FamilyMember::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $family->id,
            'user_id' => $child->id,
            'role' => 'hijo',
            'status' => 'active',
        ]);

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
