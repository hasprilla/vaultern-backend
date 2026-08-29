<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\FamilyEvent;
use Illuminate\Http\Request;

trait FindsFamilyEvent
{
    private function findForFamily(Request $request, string $eventId): FamilyEvent
    {
        $user = $request->user();
        abort_if($user->family_id === null, 403);

        return FamilyEvent::query()
            ->where('family_id', $user->family_id)
            ->with(['creator:id,name', 'guests.user:id,name,email'])
            ->findOrFail($eventId);
    }
}
