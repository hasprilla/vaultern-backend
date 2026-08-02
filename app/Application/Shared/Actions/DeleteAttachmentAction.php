<?php

declare(strict_types=1);

namespace App\Application\Shared\Actions;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class DeleteAttachmentAction
{
    public function execute(User $actor, Attachment $attachment): void
    {
        if ((string) $attachment->family_id !== (string) $actor->family_id) {
            throw ValidationException::withMessages([
                'attachment' => 'No tienes permiso para eliminar este archivo.',
            ]);
        }

        $attachment->deleteFile();
        $attachment->delete();
    }
}
