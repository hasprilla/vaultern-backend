<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Compatibilidad cPanel: el código puede desplegarse antes de correr migraciones.
 * Cachea hasTable/hasColumn por request/proceso PHP-FPM.
 */
final class SchemaCompat
{
    /** @var array<string, bool> */
    private static array $columns = [];

    /** @var array<string, bool> */
    private static array $tables = [];

    public static function hasColumn(string $table, string $column): bool
    {
        $key = $table.'.'.$column;
        if (! array_key_exists($key, self::$columns)) {
            self::$columns[$key] = Schema::hasColumn($table, $column);
        }

        return self::$columns[$key];
    }

    public static function hasTable(string $table): bool
    {
        if (! array_key_exists($table, self::$tables)) {
            self::$tables[$table] = Schema::hasTable($table);
        }

        return self::$tables[$table];
    }
}
