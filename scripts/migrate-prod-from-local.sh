#!/bin/bash
# Corre migraciones contra MySQL de producción desde tu Mac.
# Requiere acceso remoto MySQL habilitado en cPanel (Remote MySQL → tu IP).
#
# Uso:
#   export DB_PASSWORD='tu_password_mysql'
#   export DB_HOST='haaspes.space'   # o IP del servidor (Remote MySQL)
#   ./scripts/migrate-prod-from-local.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [[ -z "${DB_PASSWORD:-}" ]]; then
  echo "Falta DB_PASSWORD."
  echo "En cPanel → MySQL Databases copia la contraseña del usuario haaspess_vaultern-backend"
  echo "luego:"
  echo "  export DB_PASSWORD='...'"
  echo "  export DB_HOST='haaspes.space'"
  echo "  $0"
  exit 1
fi

# IP del servidor (Remote MySQL). Alternativa: haaspes.space si resuelve bien.
DB_HOST="${DB_HOST:-148.113.168.25}"
DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="${DB_DATABASE:-haaspess_vaultern-backend}"
DB_USERNAME="${DB_USERNAME:-haaspess_vaultern-backend}"

BACKUP=".env.local.bak.$$"
cp .env "$BACKUP"
cleanup() { mv "$BACKUP" .env 2>/dev/null || true; }
trap cleanup EXIT

cat > .env <<EOF
APP_NAME=Vaultern
APP_ENV=production
APP_KEY=base64:KFvJn8VKEVlLtn2P8vSkCV6fAzi1pNh++//UyoIC9Os=
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
  echo "2) export DB_HOST=haaspes.space (o IP del servidor)"
  echo "Alternativa (más simple): Terminal cPanel →"
  echo "  cd ~/ruta/al/backend && php artisan migrate --force"
  exit 1
fi

echo "Ejecutando migraciones..."
php artisan migrate --force
php artisan migrate:status | tail -40
echo "Listo. .env local se restaura automáticamente."
