#!/bin/bash
# Resetea cuentas QA en MySQL de producción desde tu Mac.
# Requiere: cPanel → Remote MySQL → tu IP pública autorizada.
#
# Uso:
#   # Lee password de .env.cpanel si no pasas DB_PASSWORD
#   ./scripts/seed-qa-prod-from-local.sh
#
#   export DB_PASSWORD='...'
#   export DB_HOST='148.113.168.25'   # o haaspes.space
#   ./scripts/seed-qa-prod-from-local.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [[ -z "${DB_PASSWORD:-}" && -f .env.cpanel ]]; then
  DB_PASSWORD="$(php -r '$t=file_get_contents(".env.cpanel");if(preg_match("/^DB_PASSWORD=(.*)$/m",$t,$m))echo trim($m[1]," \t\"'\''");')"
fi
if [[ -z "${DB_PASSWORD:-}" ]]; then
  echo "Falta DB_PASSWORD (export o .env.cpanel)."
  exit 1
fi

if [[ -z "${APP_KEY:-}" && -f .env.cpanel ]]; then
  APP_KEY="$(php -r '$t=file_get_contents(".env.cpanel");if(preg_match("/^APP_KEY=(.*)$/m",$t,$m))echo trim($m[1]);')"
fi
if [[ -z "${APP_KEY:-}" ]]; then
  echo "Falta APP_KEY."
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

echo "Conectando a ${DB_USERNAME}@${DB_HOST}:${DB_PORT}/${DB_DATABASE} ..."
php artisan db:show | head -20

echo ""
echo "Sembrando QaUsersSeeder (PROD)..."
php artisan db:seed --class=Database\\Seeders\\QaUsersSeeder --force --no-interaction

echo ""
echo "Listo. .env local se restaura automáticamente."
