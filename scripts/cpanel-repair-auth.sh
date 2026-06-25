#!/bin/bash
# Ejecutar en Terminal de cPanel, dentro de la carpeta del backend.
set -euo pipefail

echo "== Vaultern: reparar auth (login/registro) =="

php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

echo ""
echo "Probando conexión a MySQL..."
php artisan db:show 2>&1 || {
  echo ""
  echo "ERROR: No hay conexión a la base de datos."
  echo "Revisa .env → DB_HOST=localhost, DB_DATABASE, DB_USERNAME, DB_PASSWORD entre comillas."
  exit 1
}

echo ""
echo "Migraciones pendientes:"
php artisan migrate:status

echo ""
echo "Listo. Prueba: curl -s https://apivaulternbackend.haaspes.space/api/v1/health"
