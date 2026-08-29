<?php

declare(strict_types=1);

namespace App\Application\Family\Actions;

use App\Models\FamilyEvent;
use App\Models\FamilyEventGuest;
use App\Models\User;
use Illuminate\Support\Str;

final class AddEventGuestAction
{
    /** @param array<string, mixed> $data */
    public function execute(User $actor, FamilyEvent $event, array $data): FamilyEventGuest
    {
        abort_if((string) $actor->family_id !== (string) $event->family_id, 403);

        $email = isset($data['email']) && $data['email'] !== ''
            ? strtolower(trim((string) $data['email']))
            : null;

        return FamilyEventGuest::query()->create([
            'id' => (string) Str::uuid(),
            'event_id' => $event->id,
            'user_id' => $data['user_id'] ?? null,
            'name' => $data['name'],
            'email' => $email,
            'phone' => $data['phone'] ?? null,
            'guest_kind' => ($data['guest_kind'] ?? 'adult') === 'child' ? 'child' : 'adult',
            'status' => 'pending',
            'invited_at' => now(),
        ]);
    }
}
