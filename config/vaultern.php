<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Notificaciones (cPanel)
    |--------------------------------------------------------------------------
    |
    | En hosting compartido el cron puede atrasar la cola. Con sync=true,
    | NotifyFamilyJob corre en el mismo request (FCM + fila en notifications)
    | sin depender de Redis ni de un worker persistente.
    |
    */
    'notifications_sync' => filter_var(
        env('NOTIFICATIONS_SYNC', true),
        FILTER_VALIDATE_BOOL,
    ),

];
