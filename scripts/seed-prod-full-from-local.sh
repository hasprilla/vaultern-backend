#!/bin/bash
# Temporary: full DatabaseSeeder against prod MySQL.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

DB_PASSWORD="$(php -r '$t=file_get_contents(".env.cpanel");if(preg_match("/^DB_PASSWORD=(.*)$/m",$t,$m))echo trim($m[1]," \t\"'\''");')"
APP_KEY="$(php -r '$t=file_get_contents(".env.cpanel");if(preg_match("/^APP_KEY=(.*)$/m",$t,$m))echo trim($m[1]);')"

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
DB_HOST=148.113.168.25
DB_PORT=3306
DB_DATABASE=haaspess_vaultern-backend
DB_USERNAME=haaspess_vaultern-backend
DB_PASSWORD="${DB_PASSWORD}"
CACHE_STORE=file
SESSION_DRIVER=cookie
QUEUE_CONNECTION=database
BROADCAST_CONNECTION=log
EOF

php artisan config:clear >/dev/null
echo "Sembrando PROD (DatabaseSeeder)..."
php artisan db:seed --force --no-interaction
echo "Listo."
