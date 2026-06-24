<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Notification;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate(30);

        return response()->json($notifications);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $model = AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($notification);

        $model->update(['read' => true, 'read_at' => now()]);

        return response()->json(['data' => $model]);
    }

    public function store(Request $request): JsonResponse
    {
        if (! $request->user()->familyRole()->isParent()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'title'   => ['required', 'string', 'max:120'],
            'body'    => ['required', 'string'],
            'type'    => ['nullable', 'string', 'max:50'],
        ]);

        $notification = AppNotification::query()->create([
            'id'        => (string) Str::uuid(),
            'family_id' => $request->user()->family_id,
            'user_id'   => $validated['user_id'],
            'type'      => $validated['type'] ?? 'general',
            'title'     => $validated['title'],
            'body'      => $validated['body'],
        ]);

        return response()->json(['data' => $notification], 201);
    }
}
