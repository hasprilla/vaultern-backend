# Notas de rendimiento (ola refactor)

## Flutter

- `RealtimeService`: un solo timer de reconnect (evita storms).
- `AppShell`: `RepaintBoundary` en el body; prefetch de `subscriptionPlansProvider`.
- Checkout Wompi: sync único en WebView; la pantalla padre no re-invalida payments/server.
- DI `@riverpod` keepAlive en repositorios calientes (auth, family, tasks…).

## Laravel

- Cache 5 min de planes activos (`subscription_plans.active.v1`).
- Índices `provider+status` y `family_id+provider+created_at` en `subscription_payments`.
- Listados de pagos ya usan eager load de `events`.
- Wompi en Application/Infrastructure; controllers finos.

## Verificar

```bash
# Flutter
cd zumifly-flutter && flutter test test/unit/features/subscription/

# Laravel
cd vaultern-backend && php artisan migrate --force
php artisan route:list --path=wompi
```
