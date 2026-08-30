<?php

declare(strict_types=1);

namespace Database\Seeders;

/** Iteración estándar para seeders QA de volumen. */
final class QaBulkSupport
{
    public const N = 100;

    public static function each(callable $fn): void
    {
        for ($i = 1; $i <= self::N; $i++) {
            $fn($i);
        }
    }
}
