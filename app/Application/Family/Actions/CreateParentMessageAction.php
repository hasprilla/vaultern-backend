<?php

declare(strict_types=1);

namespace App\Application\Family\Actions;

use App\Events\ParentMessageSent;
use App\Models\ParentMessage;
use App\Models\User;
use App\Services\FamilyNotificationService;
use Illuminate\Support\Str;

/**
 * @phpstan-type CreateSuccess array{ok: true, message: ParentMessage}
 * @phpstan-type CreateFailure array{ok: false, status: int, message: string}
 */
final class CreateParentMessageAction
{
    public function __construct(
        private readonly FamilyNotificationService $notifications,
    ) {}

    /**
     * @param  array{message: string, priority?: string|null}  $validated
     * @return CreateSuccess|CreateFailure
     */
    public function execute(User $actor, string $familyId, array $validated): array
    {
        if (! in_array($actor->role, ['padre', 'madre'], true)) {
            return ['ok' => false, 'status' => 403, 'message' => 'Forbidden'];
        }

        $message = ParentMessage::query()->create([
            'id' => (string) Str::uuid(),
            'family_id' => $familyId,
            'sender_id' => $actor->id,
            'message' => $validated['message'],
            'priority' => $validated['priority'] ?? 'normal',
            'read' => false,
        ]);

        $message->load('sender:id,name,role');

        $this->notifications->notifyFamily(
            $actor,
            'family_message',
            'Mensaje familiar',
            "{$actor->name}: ".Str::limit($validated['message'], 120),
            ['entity_type' => 'parent_message', 'entity_id' => $message->id],
        );

        event(new ParentMessageSent($message));

        return ['ok' => true, 'message' => $message];
    }
}
