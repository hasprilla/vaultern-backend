<?php

declare(strict_types=1);

namespace App\Support;

final class NotificationPreferences
{
    /** @return array<string, bool> */
    public static function defaults(): array
    {
        return [
            'push_enabled' => true,
            'tasks'        => true,
            'finance'      => true,
            'family'       => true,
            'reminders'    => true,
        ];
    }

    /** @param array<string, mixed>|null $stored */
    public static function merge(?array $stored): array
    {
        return array_merge(self::defaults(), array_intersect_key(
            is_array($stored) ? $stored : [],
            self::defaults(),
        ));
    }
}
