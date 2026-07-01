<?php

declare(strict_types=1);

/**
 * Resuelve la ruta del JSON de Firebase sin depender del cwd de Apache/cPanel.
 * Basta con subir el archivo a storage/app/firebase-credentials.json
 */
$credentialsPath = (function (): string {
    $configured = env('FIREBASE_CREDENTIALS');
    $candidates = [];

    if (is_string($configured) && $configured !== '') {
        $candidates[] = $configured;

        if (! str_starts_with($configured, '/')) {
            $candidates[] = base_path($configured);
            $candidates[] = storage_path('app/'.basename($configured));
        }
    }

    $candidates[] = storage_path('app/firebase-credentials.json');

    foreach ($candidates as $path) {
        if (is_string($path) && is_file($path)) {
            return $path;
        }
    }

    return storage_path('app/firebase-credentials.json');
})();

return [
    'enabled' => (bool) env('FIREBASE_FCM_ENABLED', false),

    'project_id' => env('FIREBASE_PROJECT_ID', 'pruebasflutter-b4e52'),

    'credentials' => $credentialsPath,
];
