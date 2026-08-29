<?php

declare(strict_types=1);

namespace App\Application\Family\Actions;

use App\Models\FamilyEvent;
use App\Models\FamilyEventGuest;
use Illuminate\Support\Str;

/** Upsert guests for a family event (no notifications). */
final class UpsertEventGuestsAction
{
    /**
     * @param  list<array{user_id?: int|null, name: string, email?: string|null, phone?: string|null}>  $guests
     * @return list<string>
     */
    public function execute(FamilyEvent $event, array $guests): array
    {
        $keepIds = [];
        foreach ($guests as $row) {
            $email = isset($row['email']) && $row['email'] !== ''
                ? strtolower(trim((string) $row['email']))
                : null;
            $userId = isset($row['user_id']) ? (int) $row['user_id'] : null;
            $existing = $this->findExisting($event->id, $userId, $email);
            if ($existing) {
                $existing->fill([
                    'name' => $row['name'],
                    'email' => $email,
                    'phone' => $row['phone'] ?? $existing->phone,
                    'user_id' => $userId ?? $existing->user_id,
                ])->save();
                $keepIds[] = $existing->id;
                continue;
            }
            $keepIds[] = FamilyEventGuest::query()->create([
                'id' => (string) Str::uuid(),
                'event_id' => $event->id,
                'user_id' => $userId,
                'name' => $row['name'],
                'email' => $email,
                'phone' => $row['phone'] ?? null,
                'status' => 'pending',
                'invited_at' => now(),
            ])->id;
        }

        return $keepIds;
    }

    private function findExisting(string $eventId, ?int $userId, ?string $email): ?FamilyEventGuest
    {
        if ($userId) {
            return FamilyEventGuest::query()
                ->where('event_id', $eventId)->where('user_id', $userId)->first();
        }
        if ($email) {
            return FamilyEventGuest::query()
                ->where('event_id', $eventId)->where('email', $email)->first();
        }

        return null;
    }
}
