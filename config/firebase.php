<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('FIREBASE_FCM_ENABLED', false),

    'project_id' => env('FIREBASE_PROJECT_ID', 'pruebasflutter-b4e52'),

    /** Ruta al JSON de cuenta de servicio (Firebase Console → Project settings → Service accounts). */
    'credentials' => env('FIREBASE_CREDENTIALS', storage_path('app/firebase-credentials.json')),
];
