<?php

declare(strict_types=1);

namespace App\Application\Support\Actions;

use App\Events\SupportTicketChanged;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\FamilyNotificationService;
use Illuminate\Support\Str;

/**
 * @phpstan-type MessageSuccess array{ok: true, message: SupportTicketMessage}
 * @phpstan-type MessageFailure array{ok: false, status: int, message: string}
 */
final class AddSupportMessageAction
{
    public function __construct(
        private readonly FamilyNotificationService $notifications,
    ) {}

    /**
     * @param  array{body: string}  $validated
     * @return MessageSuccess|MessageFailure
     */
    public function execute(User $user, SupportTicket $ticket, array $validated): array
    {
        if ($ticket->status === 'closed' && ! $user->canManageSupportTickets()) {
            return ['ok' => false, 'status' => 422, 'message' => 'Este ticket está cerrado.'];
        }

        $isStaff = $user->canManageSupportTickets();

        $message = SupportTicketMessage::query()->create([
            'id' => (string) Str::uuid(),
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'body' => $validated['body'],
            'is_staff' => $isStaff,
        ]);

        $updates = ['last_message_at' => now()];

        if ($isStaff && $ticket->status === 'open') {
            $updates['status'] = 'in_progress';
        }

        if (! $isStaff && in_array($ticket->status, ['waiting_user', 'resolved'], true)) {
            $updates['status'] = 'in_progress';
        }

        if ($isStaff && $ticket->assigned_to === null) {
            $updates['assigned_to'] = $user->id;
        }

        $ticket->update($updates);
        $message->load('author:id,name');
        $ticket->refresh();

        if ($isStaff && $ticket->family_id !== null) {
            $this->notifications->notifyFamilyById(
                $ticket->family_id,
                (int) $user->id,
                'support_message',
                'Respuesta de soporte',
                "Soporte respondió en «{$ticket->subject}»",
                ['entity_type' => 'support_ticket', 'entity_id' => $ticket->id],
                [(int) $ticket->user_id],
            );
            event(new SupportTicketChanged(
                recipientUserId: (int) $ticket->user_id,
                ticketId: (string) $ticket->id,
                action: 'message',
                status: $ticket->status,
                subject: $ticket->subject,
                actorId: (int) $user->id,
            ));
        } elseif (! $isStaff && $user->family_id !== null) {
            $this->notifications->notifyFamily(
                $user,
                'support_message',
                'Mensaje en ticket',
                "{$user->name} respondió en «{$ticket->subject}»",
                ['entity_type' => 'support_ticket', 'entity_id' => $ticket->id],
            );
            $this->broadcastTicketToStaff($ticket, 'message', (int) $user->id);
        }

        return ['ok' => true, 'message' => $message];
    }

    private function broadcastTicketToStaff(SupportTicket $ticket, string $action, int $actorId): void
    {
        $staffIds = User::query()
            ->where('role', 'soporte')
            ->pluck('id')
            ->all();

        foreach ($staffIds as $staffId) {
            event(new SupportTicketChanged(
                recipientUserId: (int) $staffId,
                ticketId: (string) $ticket->id,
                action: $action,
                status: $ticket->status,
                subject: $ticket->subject,
                actorId: $actorId,
            ));
        }
    }
}
