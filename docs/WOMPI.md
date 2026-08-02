# Wompi (Web Checkout)

Checkout oficial de Wompi vía WebView en Flutter. Cobros en COP.

## Capas (Clean Architecture)

| Capa | Ubicación |
|------|-----------|
| Domain contract | `app/Domains/Subscription/Contracts/PaymentGatewayClient.php` |
| Infrastructure | `app/Infrastructure/Wompi/WompiHttpClient.php` |
| Application | `app/Application/Subscription/*Action.php`, `WompiCheckoutService.php` |
| HTTP | Controllers + `StartWompiCheckoutRequest` |

Los controllers solo orquestan Request → Action → JSON (mismo shape de respuesta).

## Variables de entorno

```env
WOMPI_ENABLED=true
WOMPI_SANDBOX=true
WOMPI_PUBLIC_KEY=pub_test_...
WOMPI_PRIVATE_KEY=prv_test_...
WOMPI_INTEGRITY_SECRET=test_integrity_...
WOMPI_EVENTS_SECRET=test_events_...   # recomendado en prod (X-Event-Checksum)
```

Solo en `.env` / cPanel. Nunca en git.

Sandbox: `pub_test_` / `prv_test_` + `WOMPI_SANDBOX=true`.  
Producción: `pub_prod_` / `prv_prod_` + `WOMPI_SANDBOX=false`.

## Endpoints

| Método | Ruta | Auth |
|--------|------|------|
| GET | `/api/v1/subscriptions/checkout-config` | sí |
| POST | `/api/v1/subscriptions/checkout/wompi` | sí |
| POST | `/api/v1/subscriptions/payments/{id}/wompi-sync` | sí |
| POST | `/api/v1/webhooks/wompi` | no |
| GET | `/api/v1/subscriptions/wompi/pay/{payment}` | no |
| GET | `/api/v1/subscriptions/wompi/return` | no |

`POST /subscriptions/checkout` (tarjeta simulada) queda bloqueado si `WOMPI_ENABLED=true`.

## Flujo

1. App → `POST /checkout/wompi` → crea `SubscriptionPayment` (`pending`, provider `wompi`) + firma de integridad.
2. App abre WebView con `checkout_url` (`/wompi/pay/{id}`), que auto-envía el formulario a `https://checkout.wompi.co/p/`.
3. Tras pagar, Wompi redirige a `/wompi/return?payment_id=…&id={transaction_id}`.
4. Webhook `transaction.updated` (o sync) consulta la API y, si `APPROVED`, llama `activateFromSuccessfulPayment`.

## Despliegue cPanel

1. Subir código backend.
2. Setear `WOMPI_*` en `.env`.
3. `php artisan config:clear`
4. En [Comercios Wompi](https://comercios.wompi.co) → Desarrolladores → URL de eventos:
   - `https://apivaulternbackend.haaspes.space/api/v1/webhooks/wompi`
5. Rebuild e instalar la app Flutter (flavor production).

## Checklist de deploy (sandbox → prod)

### A. Preparación (sandbox)

- [ ] Cuenta comercio en Wompi con llaves `pub_test_` / `prv_test_`.
- [ ] Copiar en cPanel (`.env` o `.env.cpanel` aplicado):
  - `WOMPI_ENABLED=true`
  - `WOMPI_SANDBOX=true`
  - `WOMPI_PUBLIC_KEY=pub_test_…`
  - `WOMPI_PRIVATE_KEY=prv_test_…`
  - `WOMPI_INTEGRITY_SECRET=…` (firma del Web Checkout)
  - `WOMPI_EVENTS_SECRET=…` (checksum de eventos; recomendado)
- [ ] `APP_URL` apunta a HTTPS público (`https://apivaulternbackend.haaspes.space`).
- [ ] `php artisan config:clear` (y `config:cache` si el hosting lo usa en prod).
- [ ] Verificar: `php artisan wompi:doctor` → sin bloqueantes.
- [ ] URL de eventos en Comercios Wompi:
  - `https://apivaulternbackend.haaspes.space/api/v1/webhooks/wompi`
- [ ] Migraciones al día (`php artisan migrate --force` si aplica).

### B. Smoke test sandbox

- [ ] `GET /api/v1/subscriptions/checkout-config` → `use_wompi: true` (o equivalente) y plan en COP.
- [ ] Desde la app: elegir plan → WebView Wompi abre sin error de firma.
- [ ] Pagar con [tarjeta de prueba Wompi](https://docs.wompi.co/docs/colombia/tarjetas-de-prueba/).
- [ ] Return URL carga y la app hace `wompi-sync` (pago `approved` / suscripción activa).
- [ ] Webhook llega (logs Laravel / tabla `subscription_payments` actualizada) aunque se cierre el WebView.
- [ ] Reintentar sync de un pago ya aprobado no duplica activación.

### C. Corte a producción

- [ ] Llaves `pub_prod_` / `prv_prod_` + secrets de producción.
- [ ] `WOMPI_SANDBOX=false`.
- [ ] Misma URL de eventos (o la de prod si el dominio cambia).
- [ ] `php artisan config:clear`.
- [ ] Un cobro real de monto bajo o plan mínimo; verificar webhook `APPROVED`.
- [ ] Rebuild Flutter production e instalar (sin hardcodear llaves en la app).

### D. Si falla

| Síntoma | Revisar |
|---------|---------|
| Firma inválida / checkout no abre | `WOMPI_INTEGRITY_SECRET`, montos en centavos, `reference` |
| Webhook 401/ignorado | `WOMPI_EVENTS_SECRET`, URL exacta, HTTPS |
| Pago OK pero plan no activa | logs `HandleWompiWebhook` / `SyncWompiPayment`, estado del payment |
| App sigue en modo simulado | `WOMPI_ENABLED` y respuesta de `checkout-config` |

## Notas

- Planes del catálogo en **COP** (`amount_cents` = pesos × 100).
- `redirect-url` y la página de pay usan `APP_URL` (HTTPS en producción).
- La activación definitiva llega por webhook; el return + `wompi-sync` son fallback.
- Tarjetas de prueba: solo con llaves `pub_test_` / sandbox.
