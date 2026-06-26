<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Support;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesPagination;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SupportController extends Controller
{
    use ResolvesPagination;

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
        if ($request->user()->canManageSupportTickets()) {
            return response()->json(['message' => 'Los agentes de soporte no pueden crear tickets.'], 403);
        }

        $validated = $request->validate([
            'subject'  => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', Rule::in(['general', 'account', 'tasks', 'finance', 'technical'])],
            'body'     => ['required', 'string', 'max:5000'],
            'priority' => ['nullable', 'string', Rule::in(['low', 'normal', 'high'])],
        ]);

        $user = $request->user();

        $ticket = SupportTicket::query()->create([
            'id'              => (string) Str::uuid(),
            'family_id'       => $user->family_id,
            'user_id'         => $user->id,
            'subject'         => $validated['subject'],
            'category'        => $validated['category'] ?? 'general',
            'status'          => 'open',
            'priority'        => $validated['priority'] ?? 'normal',
            'last_message_at' => now(),
        ]);

        SupportTicketMessage::query()->create([
            'id'        => (string) Str::uuid(),
            'ticket_id' => $ticket->id,
            'user_id'   => $user->id,
            'body'      => $validated['body'],
            'is_staff'  => false,
        ]);

        $ticket->load(['requester:id,name,email', 'assignee:id,name', 'messages.author:id,name']);

        return response()->json(['data' => $ticket], 201);
    }

    public function show(Request $request, SupportTicket $ticket): JsonResponse
    {
        if (! $this->canAccessTicket($request->user(), $ticket)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $ticket->load(['requester:id,name,email', 'assignee:id,name', 'messages.author:id,name']);

        return response()->json(['data' => $ticket]);
    }

    public function addMessage(Request $request, SupportTicket $ticket): JsonResponse
    {
        if (! $this->canAccessTicket($request->user(), $ticket)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($ticket->status === 'closed' && ! $request->user()->canManageSupportTickets()) {
            return response()->json(['message' => 'Este ticket está cerrado.'], 422);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $user = $request->user();
        $isStaff = $user->canManageSupportTickets();

        $message = SupportTicketMessage::query()->create([
            'id'        => (string) Str::uuid(),
            'ticket_id' => $ticket->id,
            'user_id'   => $user->id,
            'body'      => $validated['body'],
            'is_staff'  => $isStaff,
        ]);

        $updates = ['last_message_at' => now()];

        if ($isStaff && $ticket->status === 'open') {
            $updates['status'] = 'in_progress';
        }

        if (! $isStaff && $ticket->status === 'waiting_user') {
            $updates['status'] = 'in_progress';
        }

        if (! $isStaff && $ticket->status === 'resolved') {
            $updates['status'] = 'in_progress';
        }

        if ($isStaff && $ticket->assigned_to === null) {
            $updates['assigned_to'] = $user->id;
        }

        $ticket->update($updates);

        $message->load('author:id,name');

        return response()->json(['data' => $message], 201);
    }

    public function update(Request $request, SupportTicket $ticket): JsonResponse
    {
        if (! $request->user()->canManageSupportTickets()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'status'      => ['sometimes', 'string', Rule::in(['open', 'in_progress', 'waiting_user', 'resolved', 'closed'])],
            'priority'    => ['sometimes', 'string', Rule::in(['low', 'normal', 'high'])],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $ticket->update($validated);
        $ticket->load(['requester:id,name,email', 'assignee:id,name']);

        return response()->json(['data' => $ticket]);
    }

    private function canAccessTicket(\App\Models\User $user, SupportTicket $ticket): bool
    {
        if ($user->canManageSupportTickets()) {
            return true;
        }

        return (int) $ticket->user_id === (int) $user->id;
    }
}
