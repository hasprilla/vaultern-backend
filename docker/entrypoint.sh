#!/usr/bin/env bash
set -euo pipefail

ROLE="${1:-web}"
PORT="${PORT:-8000}"

# Railway / Docker inyectan vars; limpiar cache de config en boot.
php artisan config:clear >/dev/null 2>&1 || true

case "$ROLE" in
  web)
    echo "[vaultern] Migrating..."
    php artisan migrate --force --no-interaction
    echo "[vaultern] Serving on 0.0.0.0:${PORT}"
    # --no-reload: pasa env de Railway/Docker al worker (DB/REDIS/CACHE).
    exec php artisan serve --host=0.0.0.0 --port="${PORT}" --no-reload
    ;;
  worker)
    echo "[vaultern] Queue worker (redis)..."
    exec php artisan queue:work "${QUEUE_CONNECTION:-redis}" \
      --sleep=1 \
      --tries=3 \
      --timeout=90 \
      --max-time=3600
    ;;
  scheduler)
    echo "[vaultern] Scheduler..."
    exec php artisan schedule:work
    ;;
  reverb)
    echo "[vaultern] Reverb..."
    exec php artisan reverb:start --host=0.0.0.0 --port="${REVERB_SERVER_PORT:-8080}"
    ;;
  *)
    exec "$@"
    ;;
esac
