<?php

declare(strict_types=1);

namespace App\Application\Notification\Actions;

use App\Models\AppNotification;
use App\Models\User;
use App\Services\FamilyNotificationService;

final class MarkNotificationReadAction
{
    public function __construct(
        private readonly FamilyNotificationService $notifications,
    ) {}

    public function execute(User $reader, AppNotification $notification): AppNotification
    {
        $wasUnread = ! $notification->read;

        if ($wasUnread) {
            $notification->update(['read' => true, 'read_at' => now()]);
            $this->notifyActorOfReadReceipt($reader, $notification->fresh() ?? $notification);
        }

        return $notification->fresh() ?? $notification;
    }

    private function notifyActorOfReadReceipt(User $reader, AppNotification $notification): void
    {
        $actorId = (int) ($notification->data['actor_id'] ?? 0);
        if ($actorId <= 0 || $actorId === (int) $reader->id || $reader->family_id === null) {
            return;
        }

        $actor = User::query()->find($actorId);
        if ($actor === null || $actor->family_id !== $reader->family_id) {
            return;
        }

        $this->notifications->notifyUsers(
            $reader,
            [$actorId],
            'alert_read',
            'Alerta vista',
            "{$reader->name} vio tu alerta: {$notification->title}",
            [
                'actor_id' => $reader->id,
                'actor_name' => $reader->name,
                'original_notification' => $notification->id,
                'read_at' => $notification->read_at?->toIso8601String(),
            ],
        );
    }
}
