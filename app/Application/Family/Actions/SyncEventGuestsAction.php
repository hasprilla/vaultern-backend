<?php

declare(strict_types=1);

namespace App\Application\Family\Actions;

use App\Models\FamilyEvent;
use App\Models\FamilyEventGuest;
use App\Models\User;
use App\Services\FamilyNotificationService;
use Illuminate\Support\Facades\DB;

final class SyncEventGuestsAction
{
    public function __construct(
        private readonly UpsertEventGuestsAction $upsert,
        private readonly FamilyNotificationService $notifications,
    ) {}

    /**
     * @param  list<array{user_id?: int|null, name: string, email?: string|null, phone?: string|null}>  $guests
     */
    public function execute(User $actor, FamilyEvent $event, array $guests): FamilyEvent
    {
        abort_if((string) $actor->family_id !== (string) $event->family_id, 403);

        DB::transaction(function () use ($event, $guests) {
            $keepIds = $this->upsert->execute($event, $guests);
            FamilyEventGuest::query()
                ->where('event_id', $event->id)
                ->when($keepIds !== [], fn ($q) => $q->whereNotIn('id', $keepIds))
                ->delete();
        });

        $event = $event->fresh(['creator:id,name', 'guests']);
        $ids = $event->guests->pluck('user_id')->filter()->unique()->values()->all();
        if ($ids !== []) {
            $this->notifications->notifyUsers(
                $actor,
                $ids,
                'family_event',
                'Invitación a evento',
                "Te invitaron a: {$event->title}",
                [
                    'entity_type' => 'family_event',
                    'entity_id' => $event->id,
                    'family_event_id' => $event->id,
                ],
            );
        }

        return $event;
    }
}
