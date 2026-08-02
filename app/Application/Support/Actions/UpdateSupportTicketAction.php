<?php

declare(strict_types=1);

namespace App\Application\Support\Actions;

use App\Events\SupportTicketChanged;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\FamilyNotificationService;

/**
 * @phpstan-type UpdateSuccess array{ok: true, ticket: SupportTicket}
 * @phpstan-type UpdateFailure array{ok: false, status: int, message: string}
 */
final class UpdateSupportTicketAction
{
    public function __construct(
        private readonly FamilyNotificationService $notifications,
    ) {}

    /**
     * @param  array{status?: string, priority?: string, assigned_to?: int|null}  $validated
     * @return UpdateSuccess|UpdateFailure
     */
    public function execute(User $actor, SupportTicket $ticket, array $validated): array
    {
        if (! $actor->canManageSupportTickets()) {
            return ['ok' => false, 'status' => 403, 'message' => 'Forbidden'];
        }

        $ticket->update($validated);
        $ticket->load(['requester:id,name,email', 'assignee:id,name']);

        if ($ticket->family_id !== null) {
            $this->notifications->notifyFamilyById(
                $ticket->family_id,
                (int) $actor->id,
                'support_ticket',
                'Ticket actualizado',
                "El estado de «{$ticket->subject}» cambió a {$ticket->status}",
                ['entity_type' => 'support_ticket', 'entity_id' => $ticket->id],
                [(int) $ticket->user_id],
            );
        }

        event(new SupportTicketChanged(
            recipientUserId: (int) $ticket->user_id,
            ticketId: (string) $ticket->id,
            action: 'updated',
            status: $ticket->status,
            subject: $ticket->subject,
            actorId: (int) $actor->id,
        ));

        return ['ok' => true, 'ticket' => $ticket];
    }
}
