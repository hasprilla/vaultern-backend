<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ChildGuardian;
use App\Models\User;
use App\Support\SchemaCompat;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ChildGuardianService
{
    /**
     * @param  list<int|string>  $parentIds
     */
    public function syncForChild(User $child, array $parentIds, ?User $ensureParent = null): void
    {
        if ($child->role !== 'hijo' || $child->family_id === null) {
            throw ValidationException::withMessages(['child' => 'Usuario no es un hijo válido.']);
        }

        if (! SchemaCompat::hasTable('child_guardians')) {
            throw ValidationException::withMessages([
                'guardian_ids' => 'La custodia aún no está disponible. Ejecuta las migraciones pendientes en el servidor.',
            ]);
        }

        $ids = array_values(array_unique(array_map('intval', $parentIds)));
        if ($ensureParent !== null) {
            $ids[] = (int) $ensureParent->id;
            $ids = array_values(array_unique($ids));
        }

        if ($ids === []) {
            throw ValidationException::withMessages([
                'guardian_ids' => 'Debes asignar al menos un padre, madre o tutor.',
            ]);
        }

        $parents = User::query()
            ->where('family_id', $child->family_id)
            ->whereIn('id', $ids)
            ->whereIn('role', ['padre', 'madre', 'tutor'])
            ->get();

        if ($parents->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'guardian_ids' => 'Uno o más custodios no pertenecen a esta familia o no tienen rol válido.',
            ]);
        }

        ChildGuardian::query()->where('child_user_id', $child->id)->delete();

        foreach ($parents as $parent) {
            ChildGuardian::query()->create([
                'id'             => (string) Str::uuid(),
                'family_id'      => $child->family_id,
                'child_user_id'  => $child->id,
                'parent_user_id' => $parent->id,
                'relation'       => $parent->role,
            ]);
        }
    }

    /** @return list<int> */
    public function childIdsFor(User $parent): array
    {
        if (! in_array($parent->role, ['padre', 'madre', 'tutor'], true)) {
            return [];
        }

        if ($parent->family_id === null) {
            return [];
        }

        // Dueño (o fallback pre-migración) ve todos los hijos.
        if ($parent->isFamilyOwner()) {
            return $this->allChildIdsInFamily($parent->family_id);
        }

        // Compat cPanel: sin tabla, todos los padres ven a todos los hijos del núcleo.
        if (! SchemaCompat::hasTable('child_guardians')) {
            return $this->allChildIdsInFamily($parent->family_id);
        }

        return ChildGuardian::query()
            ->where('parent_user_id', $parent->id)
            ->pluck('child_user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @return list<int> */
    public function parentIdsFor(User $child): array
    {
        if (! SchemaCompat::hasTable('child_guardians')) {
            if ($child->family_id === null) {
                return [];
            }

            return User::query()
                ->where('family_id', $child->family_id)
                ->whereIn('role', ['padre', 'madre', 'tutor'])
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return ChildGuardian::query()
            ->where('child_user_id', $child->id)
            ->pluck('parent_user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function isGuardianOf(User $parent, int $childId): bool
    {
        if ($parent->isFamilyOwner() && $parent->family_id !== null) {
            return User::query()
                ->where('id', $childId)
                ->where('family_id', $parent->family_id)
                ->where('role', 'hijo')
                ->exists();
        }

        if (! SchemaCompat::hasTable('child_guardians')) {
            return User::query()
                ->where('id', $childId)
                ->where('family_id', $parent->family_id)
                ->where('role', 'hijo')
                ->exists();
        }

        return ChildGuardian::query()
            ->where('parent_user_id', $parent->id)
            ->where('child_user_id', $childId)
            ->exists();
    }

    /** @return list<int> */
    public function guardianIdsOfChild(int $childId): array
    {
        if (! SchemaCompat::hasTable('child_guardians')) {
            $child = User::query()->find($childId);
            if ($child === null || $child->family_id === null) {
                return [];
            }

            return User::query()
                ->where('family_id', $child->family_id)
                ->whereIn('role', ['padre', 'madre', 'tutor'])
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return ChildGuardian::query()
            ->where('child_user_id', $childId)
            ->pluck('parent_user_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @return list<int> */
    private function allChildIdsInFamily(string $familyId): array
    {
        return User::query()
            ->where('family_id', $familyId)
            ->where('role', 'hijo')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
