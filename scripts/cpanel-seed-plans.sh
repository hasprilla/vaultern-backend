#!/bin/bash
# Ejecutar en Terminal de cPanel, dentro de la carpeta del backend.
# Siembra/actualiza planes de suscripción (necesario para checkout + historial).
set -euo pipefail

echo "== Vaultern: seed subscription plans =="
php artisan db:seed --class=SubscriptionPlanSeeder --force
php artisan config:clear
echo "Listo. Prueba GET /api/v1/subscriptions/plans con sesión."
