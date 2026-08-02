<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Support;

use App\Application\Support\Actions\AddSupportMessageAction;
use App\Application\Support\Actions\CreateSupportTicketAction;
use App\Application\Support\Actions\UpdateSupportTicketAction;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesPagination;
use App\Models\SubscriptionPayment;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportController extends Controller
{
    use ResolvesPagination;

    public function __construct(
        private readonly CreateSupportTicketAction $createTicket,
        private readonly AddSupportMessageAction $addMessageAction,
        private readonly UpdateSupportTicketAction $updateTicket,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = SupportTicket::query()
            ->with(['requester:id,name,email', 'assignee:id,name'])
            ->withCount('messages');

        if ($user->canManageSupportTickets()) {
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            if ($request->filled('assigned_to')) {
                $query->where('assigned_to', $request->input('assigned_to'));
            }
        } else {
            $query->where('user_id', $user->id);
        }

        $tickets = $query
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->paginate($this->perPage($request));

        return response()->json($tickets);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', Rule::in(['general', 'account', 'tasks', 'finance', 'technical'])],
            'body' => ['required', 'string', 'max:5000'],
            'priority' => ['nullable', 'string', Rule::in(['low', 'normal', 'high'])],
            'entity_type' => ['nullable', 'string', Rule::in(['subscription_payment'])],
            'entity_id' => ['nullable', 'string', 'max:64', 'required_with:entity_type'],
        ]);

        $result = $this->createTicket->execute($request->user(), $validated);

        if (($result['ok'] ?? false) !== true) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        return response()->json(['data' => $this->ticketPayload($result['ticket'])], 201);
    }

    public function show(Request $request, SupportTicket $ticket): JsonResponse
    {
        if (! $this->canAccessTicket($request->user(), $ticket)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $ticket->load(['requester:id,name,email', 'assignee:id,name', 'messages.author:id,name']);

        return response()->json(['data' => $this->ticketPayload($ticket)]);
    }

    public function addMessage(Request $request, SupportTicket $ticket): JsonResponse
    {
        if (! $this->canAccessTicket($request->user(), $ticket)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $result = $this->addMessageAction->execute($request->user(), $ticket, $validated);

        if (($result['ok'] ?? false) !== true) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        return response()->json(['data' => $result['message']], 201);
    }

    public function update(Request $request, SupportTicket $ticket): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'string', Rule::in(['open', 'in_progress', 'waiting_user', 'resolved', 'closed'])],
            'priority' => ['sometimes', 'string', Rule::in(['low', 'normal', 'high'])],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $result = $this->updateTicket->execute($request->user(), $ticket, $validated);

        if (($result['ok'] ?? false) !== true) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        return response()->json(['data' => $result['ticket']]);
    }

    private function canAccessTicket(User $user, SupportTicket $ticket): bool
    {
        if ($user->canManageSupportTickets()) {
            return true;
        }

        return (int) $ticket->user_id === (int) $user->id;
    }

    /**
     * @return array<string, mixed>
     */
    private function ticketPayload(SupportTicket $ticket): array
    {
        $data = $ticket->toArray();

        if ($ticket->entity_type === 'subscription_payment' && filled($ticket->entity_id)) {
            $payment = SubscriptionPayment::query()->find($ticket->entity_id);
            if ($payment !== null) {
                $data['related_payment'] = [
                    'id' => $payment->id,
                    'payment_reference' => $payment->payment_reference,
                    'amount_cents' => $payment->amount_cents,
                    'currency' => $payment->currency,
                    'status' => $payment->status,
                ];
            }
        }

        return $data;
    }
}
