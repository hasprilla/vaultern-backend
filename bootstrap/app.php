<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        apiPrefix: 'api',
        then: function (): void {
            Route::get('/', function () {
                return response()->json([
                    'app'    => 'Vaultern API',
                    'status' => 'ok',
                    'health' => url('/api/v1/health'),
                ]);
            });

            Route::get('/up', fn () => response()->json(['status' => 'up']));
        },
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['api', 'api.auth'], 'prefix' => 'api'],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'api.auth' => \App\Http\Middleware\ApiAuthenticate::class,
            'tenant'   => \App\Http\Middleware\TenantMiddleware::class,
        ]);

        $middleware->appendToGroup('api', [
            \App\Http\Middleware\ForceEncryptedTransport::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })
    ->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void {
        // Cada hora: reintentos multi-tarjeta durante gracia de 48h (sin solapar ejecuciones).
        $schedule->command('subscriptions:renew')
            ->hourly()
            ->withoutOverlapping(55);

        // cPanel (QUEUE_CONNECTION=database): cron cada minuto drena jobs y sale.
        // Docker/Railway (redis): el servicio `worker` mantiene queue:work; no hace falta esto.
        if (config('queue.default') === 'database') {
            $schedule->command('queue:work database --stop-when-empty --tries=3 --max-time=50')
                ->everyMinute()
                ->withoutOverlapping(5);
        }
    })->create();
