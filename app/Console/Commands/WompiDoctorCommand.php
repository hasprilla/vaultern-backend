<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class WompiDoctorCommand extends Command
{
    protected $signature = 'wompi:doctor';

    protected $description = 'Verifica configuración Wompi sin exponer secretos completos';

    public function handle(): int
    {
        $enabled = (bool) config('wompi.enabled');
        $sandbox = (bool) config('wompi.sandbox');
        $public = (string) config('wompi.public_key');
        $private = (string) config('wompi.private_key');
        $integrity = (string) config('wompi.integrity_secret');
        $events = (string) config('wompi.events_secret');
        $apiBase = (string) config('wompi.api_base');
        $appUrl = rtrim((string) config('app.url'), '/');

        $rows = [
            ['WOMPI_ENABLED', $enabled ? 'true' : 'false', $enabled ? 'ok' : 'off'],
            ['WOMPI_SANDBOX', $sandbox ? 'true' : 'false', $sandbox ? 'sandbox' : 'production'],
            ['WOMPI_PUBLIC_KEY', $this->mask($public), $this->checkPublic($public, $sandbox)],
            ['WOMPI_PRIVATE_KEY', $this->mask($private), $this->checkPrivate($private, $sandbox)],
            ['WOMPI_INTEGRITY_SECRET', $this->present($integrity), $integrity !== '' ? 'ok' : 'MISSING'],
            ['WOMPI_EVENTS_SECRET', $this->present($events), $events !== '' ? 'ok' : 'recommended'],
            ['WOMPI_API_BASE', $apiBase, $apiBase !== '' ? 'ok' : 'MISSING'],
            ['APP_URL', $appUrl, str_starts_with($appUrl, 'https://') ? 'ok' : 'use HTTPS'],
            ['Webhook URL', $appUrl.'/api/v1/webhooks/wompi', 'configurar en Comercios Wompi'],
        ];

        $this->table(['Variable', 'Valor', 'Estado'], $rows);

        $blocking = [];
        if (! $enabled) {
            $blocking[] = 'WOMPI_ENABLED=false';
        }
        if ($public === '' || $private === '' || $integrity === '') {
            $blocking[] = 'Faltan PUBLIC/PRIVATE/INTEGRITY';
        }
        if ($sandbox && $public !== '' && ! str_starts_with($public, 'pub_test_')) {
            $blocking[] = 'Sandbox activo pero PUBLIC_KEY no es pub_test_';
        }
        if (! $sandbox && $public !== '' && ! str_starts_with($public, 'pub_prod_')) {
            $blocking[] = 'Producción activa pero PUBLIC_KEY no es pub_prod_';
        }

        if ($blocking === []) {
            $this->info('Wompi listo para smoke test (checkout + webhook).');

            return self::SUCCESS;
        }

        $this->error('Bloqueantes:');
        foreach ($blocking as $item) {
            $this->line('  - '.$item);
        }
        $this->newLine();
        $this->line('Pega las llaves en .env / .env.cpanel y ejecuta: php artisan config:clear');

        return self::FAILURE;
    }

    private function mask(string $value): string
    {
        if ($value === '') {
            return '(vacío)';
        }

        if (strlen($value) <= 12) {
            return substr($value, 0, 4).'…';
        }

        return substr($value, 0, 10).'…'.substr($value, -4);
    }

    private function present(string $value): string
    {
        return $value === '' ? '(vacío)' : '(definido)';
    }

    private function checkPublic(string $value, bool $sandbox): string
    {
        if ($value === '') {
            return 'MISSING';
        }

        $prefix = $sandbox ? 'pub_test_' : 'pub_prod_';

        return str_starts_with($value, $prefix) ? 'ok' : 'prefix mismatch';
    }

    private function checkPrivate(string $value, bool $sandbox): string
    {
        if ($value === '') {
            return 'MISSING';
        }

        $prefix = $sandbox ? 'prv_test_' : 'prv_prod_';

        return str_starts_with($value, $prefix) ? 'ok' : 'prefix mismatch';
    }
}
