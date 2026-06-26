<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Notification;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\User;
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

        $wasUnread = ! $model->read;

        if ($wasUnread) {
            $model->update(['read' => true, 'read_at' => now()]);
            $this->notifyActorOfReadReceipt($request->user(), $model->fresh());
        }

        return response()->json(['data' => $model->fresh()]);
    }

    /**
     * Avisa al familiar que originó la acción cuando su pareja lee la alerta.
     */
    private function notifyActorOfReadReceipt(User $reader, AppNotification $notification): void
    {
        $actorId = (int) ($notification->data['actor_id'] ?? 0);
        if ($actorId <= 0 || $actorId === (int) $reader->id) {
            return;
        }

        $actor = User::query()->find($actorId);
        if ($actor === null || $actor->family_id !== $reader->family_id) {
            return;
        }

        AppNotification::query()->create([
            'id'        => (string) Str::uuid(),
            'family_id' => $reader->family_id,
            'user_id'   => $actorId,
            'type'      => 'alert_read',
            'title'     => 'Alerta vista',
            'body'      => "{$reader->name} vio tu alerta: {$notification->title}",
            'data'      => [
                'actor_id'              => $reader->id,
                'actor_name'            => $reader->name,
                'original_notification' => $notification->id,
                'read_at'               => $notification->read_at?->toIso8601String(),
            ],
        ]);
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
