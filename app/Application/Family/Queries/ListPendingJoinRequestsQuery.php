<?php

declare(strict_types=1);

namespace App\Application\Family\Queries;

use App\Models\FamilyJoinRequest;

final class ListPendingJoinRequestsQuery
{
    /**
     * @return list<array{
     *   id: mixed,
     *   name: mixed,
     *   email: mixed,
     *   role: mixed,
     *   status: mixed,
     *   invited_by_user_id: mixed,
     *   created_at: string|null
     * }>
     */
    public function execute(string $familyId): array
    {
        $requests = FamilyJoinRequest::query()
            ->where('family_id', $familyId)
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->get();

        return $requests->map(fn (FamilyJoinRequest $r) => [
            'id' => $r->id,
            'name' => $r->name,
            'email' => $r->email,
            'role' => $r->role,
            'status' => $r->status,
            'invited_by_user_id' => $r->invited_by_user_id,
            'created_at' => $r->created_at?->toIso8601String(),
        ])->all();
    }
}
