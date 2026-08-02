<?php

declare(strict_types=1);

namespace App\Application\Support\Actions;

use App\Events\SupportTicketChanged;
use App\Models\SubscriptionPayment;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\FamilyNotificationService;
use Illuminate\Support\Str;

/**
 * @phpstan-type CreateSuccess array{ok: true, ticket: SupportTicket}
 * @phpstan-type CreateFailure array{ok: false, status: int, message: string}
 */
final class CreateSupportTicketAction
{
    public function __construct(
        private readonly FamilyNotificationService $notifications,
    ) {}

    /**
     * @param  array{subject: string, body: string, category?: string|null, priority?: string|null, entity_type?: string|null, entity_id?: string|null}  $validated
     * @return CreateSuccess|CreateFailure
     */
    public function execute(User $user, array $validated): array
    {
        if ($user->canManageSupportTickets()) {
            return [
                'ok' => false,
                'status' => 403,
                'message' => 'Los agentes de soporte no pueden crear tickets.',
            ];
        }

        $entityType = $validated['entity_type'] ?? null;
        $entityId = $validated['entity_id'] ?? null;

        if ($entityType === 'subscription_payment') {
            if ($entityId === null || $entityId === '') {
                return ['ok' => false, 'status' => 422, 'message' => 'Pago requerido para el reclamo.'];
            }

            $payment = SubscriptionPayment::query()->find($entityId);
            if ($payment === null || $payment->family_id !== $user->family_id) {
                return ['ok' => false, 'status' => 404, 'message' => 'Pago no encontrado.'];
            }
        } elseif ($entityType !== null) {
            return ['ok' => false, 'status' => 422, 'message' => 'Tipo de entidad no soportado.'];
        }

        $ticket = SupportTicket::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $user->family_id,
            'user_id' => $user->id,
            'subject' => $validated['subject'],
            'category' => $validated['category'] ?? 'general',
            'status' => 'open',
            'priority' => $validated['priority'] ?? 'normal',
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'last_message_at' => now(),
        ]);

        SupportTicketMessage::query()->create([
            'id' => (string) Str::uuid(),
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'body' => $validated['body'],
            'is_staff' => false,
        ]);

        $ticket->load(['requester:id,name,email', 'assignee:id,name', 'messages.author:id,name']);

        if ($user->family_id !== null) {
            $this->notifications->notifyFamily(
                $user,
                'support_ticket',
                'Ticket de soporte',
                "{$user->name} abrió un ticket: {$ticket->subject}",
                ['entity_type' => 'support_ticket', 'entity_id' => $ticket->id],
            );
        }

        $this->broadcastTicketToStaff($ticket, 'created', (int) $user->id);

        return ['ok' => true, 'ticket' => $ticket];
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
