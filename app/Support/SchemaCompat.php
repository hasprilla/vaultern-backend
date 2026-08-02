<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Compatibilidad cPanel: el código puede desplegarse antes de correr migraciones.
 *
 * No cacheamos en estáticos de proceso PHP-FPM: tras un migrate, workers viejos
 * seguirían creyendo que faltan columnas (o al revés) hasta reiniciar FPM.
 */
final class SchemaCompat
{
    public static function hasColumn(string $table, string $column): bool
    {
        return Schema::hasColumn($table, $column);
    }

    public static function hasTable(string $table): bool
    {
        return Schema::hasTable($table);
    }
}
