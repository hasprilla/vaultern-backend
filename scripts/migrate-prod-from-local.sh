#!/bin/bash
# Corre migraciones contra MySQL de producción desde tu Mac.
# Requiere acceso remoto MySQL habilitado en cPanel (Remote MySQL → tu IP).
#
# Uso:
#   ./scripts/migrate-prod-from-local.sh          # lee .env.cpanel
#   export DB_PASSWORD='...'
#   export DB_HOST='148.113.168.25'
#   ./scripts/migrate-prod-from-local.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [[ -z "${DB_PASSWORD:-}" && -f .env.cpanel ]]; then
  DB_PASSWORD="$(php -r '$t=file_get_contents(".env.cpanel");if(preg_match("/^DB_PASSWORD=(.*)$/m",$t,$m))echo trim($m[1]," \t\"'\''");')"
fi
if [[ -z "${APP_KEY:-}" && -f .env.cpanel ]]; then
  APP_KEY="$(php -r '$t=file_get_contents(".env.cpanel");if(preg_match("/^APP_KEY=(.*)$/m",$t,$m))echo trim($m[1]);')"
fi
if [[ -z "${DB_PASSWORD:-}" ]]; then
  echo "Falta DB_PASSWORD (export o .env.cpanel)."
  exit 1
fi
if [[ -z "${APP_KEY:-}" ]]; then
  echo "Falta APP_KEY (export o .env.cpanel)."
  exit 1
fi

DB_HOST="${DB_HOST:-148.113.168.25}"
DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="${DB_DATABASE:-haaspess_vaultern-backend}"
DB_USERNAME="${DB_USERNAME:-haaspess_vaultern-backend}"

BACKUP=".env.local.bak.$$"
cp .env "$BACKUP"
cleanup() { mv "$BACKUP" .env 2>/dev/null || true; php artisan config:clear >/dev/null 2>&1 || true; }
trap cleanup EXIT

cat > .env <<EOF
APP_NAME=Vaultern
APP_ENV=production
APP_KEY=${APP_KEY}
APP_DEBUG=false
APP_URL=https://apivaulternbackend.haaspes.space
APP_ALLOW_HTTP=false
LOG_CHANNEL=stack
LOG_LEVEL=error
DB_CONNECTION=mysql
DB_HOST=${DB_HOST}
DB_PORT=${DB_PORT}
DB_DATABASE=${DB_DATABASE}
DB_USERNAME=${DB_USERNAME}
DB_PASSWORD="${DB_PASSWORD}"
CACHE_STORE=file
SESSION_DRIVER=cookie
QUEUE_CONNECTION=database
BROADCAST_CONNECTION=log
EOF

php artisan config:clear >/dev/null

echo "Probando conexión a ${DB_USERNAME}@${DB_HOST}:${DB_PORT}/${DB_DATABASE} ..."
if ! php artisan db:show 2>&1 | head -20; then
  echo ""
  echo "No se pudo conectar."
  echo "1) cPanel → Remote MySQL → añade tu IP pública"
  echo "2) export DB_HOST=148.113.168.25"
  echo "Alternativa (más simple): Terminal cPanel →"
  echo "  cd ~/ruta/al/backend && php artisan migrate --force"
  exit 1
fi

echo "Ejecutando migraciones..."
php artisan migrate --force --no-interaction
php artisan migrate:status | tail -40
echo "Listo. .env local se restaura automáticamente."
