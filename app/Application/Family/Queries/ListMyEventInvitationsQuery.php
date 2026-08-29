<?php

declare(strict_types=1);

namespace App\Application\Family\Queries;

use App\Models\FamilyEventGuest;
use App\Models\User;
use Illuminate\Support\Collection;

final class ListMyEventInvitationsQuery
{
    /** @return Collection<int, FamilyEventGuest> */
    public function execute(User $actor): Collection
    {
        return FamilyEventGuest::query()
            ->where(function ($q) use ($actor) {
                $q->where('user_id', $actor->id);
                if ($actor->email) {
                    $q->orWhere('email', $actor->email);
                }
            })
            ->with(['event.creator:id,name', 'event.guests'])
            ->orderByDesc('invited_at')
            ->get();
    }
}
