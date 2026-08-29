<?php

declare(strict_types=1);

namespace App\Application\Family\Actions;

use App\Models\FamilyEventGuest;
use App\Models\User;

final class RsvpEventGuestAction
{
    public function execute(User $actor, FamilyEventGuest $guest, string $status, ?string $note = null): FamilyEventGuest
    {
        $allowed = in_array($status, ['pending', 'attending', 'declined', 'maybe'], true);
        abort_unless($allowed, 422, 'Estado RSVP inválido');

        $owns = (int) $guest->user_id === (int) $actor->id
            || ($guest->email !== null && $actor->email !== null
                && strcasecmp($guest->email, $actor->email) === 0);

        $sameFamily = $guest->event
            && (string) $guest->event->family_id === (string) $actor->family_id;

        abort_unless($owns || $sameFamily, 403);

        $guest->status = $status;
        $guest->note = $note;
        $guest->responded_at = now();
        if ($guest->user_id === null) {
            $guest->user_id = $actor->id;
        }
        $guest->save();

        return $guest->load('event');
    }
}
