<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Obliga a configurar o rotar la clave de dispositivo antes del resto de la API.
 */
class EnsureDeviceRecovery
{
    /** Rutas permitidas mientras falta setup/rotación. */
    private const ALLOWED = [
        'api/v1/auth/logout',
        'api/v1/auth/me',
        'api/v1/auth/device/recovery',
        'api/v1/auth/device/security-questions',
        'api/v1/auth/refresh',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null || $user->bypassesFamilyTenant()) {
            return $next($request);
        }

        $path = trim($request->path(), '/');
        if (in_array($path, self::ALLOWED, true)) {
            return $next($request);
        }

        if ($user->mustSetupDeviceRecovery()) {
            return response()->json([
                'message' => 'Configura tu clave secreta y pregunta de seguridad para proteger el cambio de dispositivo.',
                'code' => 'requires_device_recovery_setup',
            ], 403);
        }

        if ($user->mustRotateDeviceSecret()) {
            return response()->json([
                'message' => 'Tras el cambio de dispositivo debes actualizar tu clave secreta.',
                'code' => 'requires_device_secret_rotation',
            ], 403);
        }

        return $next($request);
    }
}
