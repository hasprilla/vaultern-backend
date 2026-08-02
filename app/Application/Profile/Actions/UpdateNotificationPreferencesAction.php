<?php

declare(strict_types=1);

namespace App\Application\Profile\Actions;

use App\Models\User;
use App\Support\NotificationPreferences;

final class UpdateNotificationPreferencesAction
{
    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function execute(User $user, array $validated): array
    {
        $merged = NotificationPreferences::merge($user->notification_preferences);
        $updated = array_merge($merged, $validated);
        $user->update(['notification_preferences' => $updated]);

        return $updated;
    }
}
