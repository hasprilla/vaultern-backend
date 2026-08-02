<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Family;

use App\Application\Family\Actions\CreateParentMessageAction;
use App\Application\Family\Actions\MarkParentMessageReadAction;
use App\Http\Controllers\Controller;
use App\Models\ParentMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParentMessageController extends Controller
{
    public function __construct(
        private readonly CreateParentMessageAction $createParentMessage,
        private readonly MarkParentMessageReadAction $markParentMessageRead,
    ) {}

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

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'priority' => ['nullable', 'in:low,normal,high'],
        ]);

        $result = $this->createParentMessage->execute($request->user(), $family, $validated);

        if (($result['ok'] ?? false) !== true) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        return response()->json(['data' => $result['message']], 201);
    }

    public function markRead(Request $request, string $family, string $message): JsonResponse
    {
        $this->assertFamilyAccess($request, $family);

        $model = ParentMessage::query()
            ->where('family_id', $family)
            ->findOrFail($message);

        $this->markParentMessageRead->execute($request->user(), $family, $model);

        return response()->json(['message' => 'Mensaje marcado como leído.']);
    }

    private function assertFamilyAccess(Request $request, string $familyId): void
    {
        if ($request->user()->family_id !== $familyId) {
            abort(403, 'Forbidden');
        }
    }
}
