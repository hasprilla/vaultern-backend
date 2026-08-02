<?php

declare(strict_types=1);

namespace App\Application\Profile\Actions;

use App\Models\User;
use App\Services\FamilyNotificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * @phpstan-type AvatarSuccess array{ok: true, user: User}
 * @phpstan-type AvatarFailure array{ok: false, status: int, message: string}
 */
final class UpdateAvatarAction
{
    public function __construct(
        private readonly FamilyNotificationService $notifications,
    ) {}

    /**
     * @return AvatarSuccess|AvatarFailure
     */
    public function execute(User $user, ?UploadedFile $file): array
    {
        if ($file === null) {
            return ['ok' => false, 'status' => 422, 'message' => 'No se recibió ninguna imagen.'];
        }

        $previous = $user->avatar;
        $path = $file->store('avatars/'.$user->id, 'public');
        $user->update(['avatar' => $path]);

        if (is_string($previous)
            && $previous !== ''
            && ! str_starts_with($previous, 'http://')
            && ! str_starts_with($previous, 'https://')
            && $previous !== $path
        ) {
            Storage::disk('public')->delete($previous);
        }

        if ($user->family_id !== null) {
            $this->notifications->notifyFamily(
                $user,
                'profile_updated',
                'Foto de perfil actualizada',
                "{$user->name} cambió su foto de perfil",
                ['entity_type' => 'user', 'entity_id' => (string) $user->id],
            );
        }

        return ['ok' => true, 'user' => $user->fresh() ?? $user];
    }
}
