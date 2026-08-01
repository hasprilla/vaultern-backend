# Vaultern — Zumifly API Backend

API REST para Zumifly Family Hub. **Target de producción: cPanel** (MySQL + file cache + cola `database` + FCM).

## Stack real

| Pieza | Producción (cPanel) | Local opcional |
|---|---|---|
| Framework | Laravel 13 / PHP 8.3+ | igual |
| DB | MySQL | SQLite o MySQL |
| Cache / sesión | `file` / `cookie` | `file` |
| Cola | `database` + cron `schedule:run` | `queue:work` |
| Push | Firebase FCM | FCM off |
| WebSockets | **No** (`BROADCAST_CONNECTION=log`) | Reverb opcional |
| Redis / Horizon | **No** | no usar |

## Deploy cPanel (resumen)

1. Copiar `.env.cpanel.example` → `.env` y completar credenciales.
2. `composer install --no-dev --optimize-autoloader`
3. `php artisan migrate --force`
4. `chmod -R 775 storage bootstrap/cache`
5. Cron cada minuto:
   ```
   cd /home/USER/ruta/vaultern-backend && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
   ```
6. Subir `storage/app/firebase-credentials.json` y `FIREBASE_FCM_ENABLED=true`.

Con eso: notificaciones DB + FCM y jobs (`NotifyFamilyJob`, school broadcast) salen por la cola MySQL. **No hace falta Redis ni Reverb en cPanel.**

## Local

```bash
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
# Cola (si QUEUE_CONNECTION=database):
php artisan queue:work database --tries=3
```

Realtime WebSocket (solo local, opcional):

```bash
# .env → BROADCAST_CONNECTION=reverb + keys REVERB_*
php artisan reverb:start
# Docker con perfil:
docker compose --profile realtime up -d
```

App Flutter en producción: `--dart-define=REALTIME_ENABLED=false` (por defecto fuera de API local). Sync en vivo = FCM + pull/resync.

## API

Prefijo: `/api/v1` — auth Bearer (tokens propios, no Sanctum de sesión).

Health: `GET /api/v1/health`

---

**Frontend:** [zumifly-flutter](https://github.com/hasprilla/zumifly-flutter)
