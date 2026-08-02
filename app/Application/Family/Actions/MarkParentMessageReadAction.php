<?php

declare(strict_types=1);

namespace App\Application\Family\Actions;

use App\Events\ParentMessageRead;
use App\Models\ParentMessage;
use App\Models\User;

final class MarkParentMessageReadAction
{
    public function execute(User $reader, string $familyId, ParentMessage $message): void
    {
        $message->update(['read' => true]);

        event(new ParentMessageRead(
            familyId: $familyId,
            messageId: (string) $message->id,
            readerId: (int) $reader->id,
        ));
    }
}
