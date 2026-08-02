<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Notification;

use App\Application\Notification\Actions\MarkNotificationReadAction;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesPagination;
use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use ResolvesPagination;

    public function __construct(
        private readonly MarkNotificationReadAction $markNotificationRead,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = $this->perPage($request, 30);

        $notifications = AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $payload = $notifications->toArray();
        $payload['unread_count'] = AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->where('read', false)
            ->count();

        return response()->json($payload);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $model = AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($notification);

        $model = $this->markNotificationRead->execute($request->user(), $model);

        return response()->json(['data' => $model]);
    }

    /**
     * Las alertas se generan automáticamente ante acciones familiares.
     */
    public function store(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Las alertas se envían automáticamente cuando un familiar realiza una acción.',
        ], 410);
    }
}
