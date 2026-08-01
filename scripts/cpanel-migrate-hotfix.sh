#!/bin/bash
# Ejecutar en Terminal de cPanel, dentro de la carpeta del backend.
# Corrige el 500 de /families y /dashboard (owner_user_id + child_guardians).
set -euo pipefail

echo "== Vaultern: migrate hotfix Home/Familia (sin Redis) =="

php artisan config:clear
php artisan cache:clear
php artisan route:clear

echo ""
echo "Migraciones pendientes:"
php artisan migrate:status || true

echo ""
echo "Aplicando migraciones..."
php artisan migrate --force

echo ""
echo "Optimizando..."
php artisan config:cache
php artisan route:cache

echo ""
echo "Listo. Prueba:"
echo "  curl -s https://apivaulternbackend.haaspes.space/api/v1/health"
echo "  Luego reinicia sesión en la app (Home y Familia deben cargar)."
