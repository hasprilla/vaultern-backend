<?php

declare(strict_types=1);

namespace App\Application\Support\Actions;

use App\Events\SupportTicketChanged;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPaymentEvent;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\FamilyNotificationService;
use App\Support\SchemaCompat;
use Illuminate\Support\Str;
use Throwable;

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
        $linkedPayment = null;

        if ($entityType === 'subscription_payment') {
            if ($entityId === null || $entityId === '') {
                return ['ok' => false, 'status' => 422, 'message' => 'Pago requerido para el reclamo.'];
            }

            $linkedPayment = SubscriptionPayment::query()->find($entityId);
            if (
                $linkedPayment === null
                || (string) $linkedPayment->family_id !== (string) $user->family_id
            ) {
                return ['ok' => false, 'status' => 404, 'message' => 'Pago no encontrado.'];
            }
        } elseif ($entityType !== null) {
            return ['ok' => false, 'status' => 422, 'message' => 'Tipo de entidad no soportado.'];
        }

        try {
            $payload = [
                'id' => (string) Str::uuid(),
                'family_id' => $user->family_id,
                'user_id' => $user->id,
                'subject' => $validated['subject'],
                'category' => $validated['category'] ?? 'general',
                'status' => 'open',
                'priority' => $validated['priority'] ?? 'normal',
                'last_message_at' => now(),
            ];

            // Compat: si aún no corrieron la migración de entity_*, el reclamo igual se crea.
            if (
                $entityType !== null
                && SchemaCompat::hasColumn('support_tickets', 'entity_type')
                && SchemaCompat::hasColumn('support_tickets', 'entity_id')
            ) {
                $payload['entity_type'] = $entityType;
                $payload['entity_id'] = $entityId;
            } elseif ($entityType !== null) {
                report(new \RuntimeException(
                    'support_tickets.entity_type/entity_id ausentes: reclamo creado sin vínculo al pago.',
                ));
            }

            $ticket = SupportTicket::query()->create($payload);

            SupportTicketMessage::query()->create([
                'id' => (string) Str::uuid(),
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'body' => $validated['body'],
                'is_staff' => false,
            ]);
        } catch (Throwable $e) {
            report($e);

            return [
                'ok' => false,
                'status' => 500,
                'message' => 'No se pudo crear el reclamo. Intenta de nuevo en unos segundos.',
            ];
        }

        // Efectos secundarios: no deben tumbar el reclamo si ya se creó.
        if ($linkedPayment !== null) {
            try {
                SubscriptionPaymentEvent::query()->create([
                    'id' => (string) Str::uuid(),
                    'payment_id' => $linkedPayment->id,
                    'user_id' => $user->id,
                    'event_type' => 'claim_opened',
                    'message' => 'Reclamo PQRS abierto: '.$ticket->subject,
                    'payload' => [
                        'ticket_id' => $ticket->id,
                        'payment_reference' => $linkedPayment->payment_reference,
                    ],
                    'created_at' => now(),
                ]);
            } catch (Throwable $e) {
                report($e);
            }
        }

        $ticket->load(['requester:id,name,email', 'assignee:id,name', 'messages.author:id,name']);

        try {
            if ($user->family_id !== null) {
                $this->notifications->notifyFamily(
                    $user,
                    'support_ticket',
                    $linkedPayment !== null ? 'Reclamo de pago' : 'Ticket de soporte',
                    "{$user->name} abrió un ticket: {$ticket->subject}",
                    [
                        'entity_type' => 'support_ticket',
                        'entity_id' => $ticket->id,
                        'related_payment_id' => $linkedPayment?->id,
                    ],
                );
            }

            $this->broadcastTicketToStaff($ticket, 'created', (int) $user->id);
        } catch (Throwable $e) {
            report($e);
        }

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
