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
        $userId = $request->user()->id;

        $query = AppNotification::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at');

        if ($request->boolean('unread')) {
            $query->where('read', false);
        }

        $category = $request->input('category');
        if (is_string($category) && $category !== '' && $category !== 'all') {
            $prefixes = match ($category) {
                'tasks' => ['task_'],
                'finance' => ['finance_', 'transaction_', 'budget_'],
                'family' => ['family_', 'join_'],
                'school' => ['school_', 'ocr_'],
                default => [],
            };

            if ($prefixes !== []) {
                $query->where(function ($builder) use ($prefixes) {
                    foreach ($prefixes as $prefix) {
                        $builder->orWhere('type', 'like', $prefix.'%');
                    }
                });
            }
        }

        $notifications = $query->paginate($perPage);

        $payload = $notifications->toArray();
        $payload['unread_count'] = AppNotification::query()
            ->where('user_id', $userId)
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
