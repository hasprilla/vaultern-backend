<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Family;

use App\Events\ParentMessageRead;
use App\Events\ParentMessageSent;
use App\Http\Controllers\Controller;
use App\Models\ParentMessage;
use App\Services\FamilyNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ParentMessageController extends Controller
{
    public function __construct(private readonly FamilyNotificationService $notifications) {}

    public function index(Request $request, string $family): JsonResponse
    {
        $this->assertFamilyAccess($request, $family);

        if (! in_array($request->user()->role, ['padre', 'madre'], true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $messages = ParentMessage::query()
            ->with('sender:id,name,role')
            ->where('family_id', $family)
            ->orderByDesc('created_at')
            ->paginate(30);

        return response()->json($messages);
    }

    public function store(Request $request, string $family): JsonResponse
    {
        $this->assertFamilyAccess($request, $family);

        if (! in_array($request->user()->role, ['padre', 'madre'], true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'message'  => ['required', 'string', 'max:2000'],
            'priority' => ['nullable', 'in:low,normal,high'],
        ]);

        $message = ParentMessage::query()->create([
            'id'        => (string) Str::uuid(),
            'family_id' => $family,
            'sender_id' => $request->user()->id,
            'message'   => $validated['message'],
            'priority'  => $validated['priority'] ?? 'normal',
            'read'      => false,
        ]);

        $message->load('sender:id,name,role');

        $this->notifications->notifyFamily(
            $request->user(),
            'family_message',
            'Mensaje familiar',
            "{$request->user()->name}: ".Str::limit($validated['message'], 120),
            ['entity_type' => 'parent_message', 'entity_id' => $message->id],
        );

        event(new ParentMessageSent($message));

        return response()->json(['data' => $message], 201);
    }

    public function markRead(Request $request, string $family, string $message): JsonResponse
    {
        $this->assertFamilyAccess($request, $family);

        $model = ParentMessage::query()
            ->where('family_id', $family)
            ->findOrFail($message);

        $model->update(['read' => true]);

        event(new ParentMessageRead(
            familyId: $family,
            messageId: (string) $model->id,
            readerId: (int) $request->user()->id,
        ));

        return response()->json(['message' => 'Mensaje marcado como leído.']);
    }

    private function assertFamilyAccess(Request $request, string $familyId): void
    {
        if ($request->user()->family_id !== $familyId) {
            abort(403, 'Forbidden');
        }
    }
}
