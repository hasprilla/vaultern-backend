# Mercado Pago (Checkout Pro)

Checkout oficial de Mercado Pago vía WebView en Flutter. Los cobros reales usan la cuenta Colombia (`APP_USR-…`).

## Variables de entorno

```env
MERCADOPAGO_ENABLED=true
MERCADOPAGO_ACCESS_TOKEN=APP_USR-...
MERCADOPAGO_PUBLIC_KEY=APP_USR-...
MERCADOPAGO_WEBHOOK_SECRET=   # opcional
```

Solo en `.env` / cPanel. Nunca en git.

## Endpoints

| Método | Ruta | Auth |
|--------|------|------|
| GET | `/api/v1/subscriptions/checkout-config` | sí |
| POST | `/api/v1/subscriptions/checkout/mp` | sí |
| POST | `/api/v1/subscriptions/payments/{id}/mp-sync` | sí |
| POST | `/api/v1/webhooks/mercadopago` | no |
| GET | `/api/v1/subscriptions/mp/return` | no |

`POST /subscriptions/checkout` (tarjeta simulada) queda bloqueado si `MERCADOPAGO_ENABLED=true`.

## Despliegue cPanel

1. Subir código backend.
2. Setear `MERCADOPAGO_*` en `.env`.
3. `php artisan config:clear`
4. En [Mercado Pago Developers](https://www.mercadopago.com.co/developers) → tu aplicación → Webhooks:
   - URL: `https://apivaulternbackend.haaspes.space/api/v1/webhooks/mercadopago`
   - Eventos: Pagos (`payment`)
5. Rebuild e instalar la app Flutter (flavor production).

## Notas

- Planes del catálogo en **COP**.
- `back_urls` y `notification_url` usan `APP_URL` (debe ser HTTPS en producción).
- Credenciales `APP_USR` son de **producción**: los cobros son reales.
- **Tarjetas de prueba (APRO / OTHE / …) no aprueban con `APP_USR`.** Para QA sin cobro real:
  1. En cPanel pon Access Token + Public Key `TEST-…` de la app.
  2. Inicia sesión en Checkout Pro con un **usuario comprador de prueba**.
  3. Usa tarjeta `4013 5406 8274 6260`, titular `APRO`, doc `123456789`, CVV `123`, vence `11/30`.
- Tras el WebView, la app refresca plan + historial; la activación definitiva llega por webhook.
