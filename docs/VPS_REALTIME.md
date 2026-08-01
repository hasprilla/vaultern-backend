# Tiempo real en VPS (sin reescribir código)

Guía para activar Laravel Reverb + colas fuera de cPanel.  
cPanel sigue válido con `BROADCAST_CONNECTION=log` + FCM + poll condicional en Flutter.

## Arquitectura

```
Flutter ──REST──► Laravel API ──event()──► Queue ──► Reverb ──WS──► Flutter
                      │
                      └── FCM (fallback / background)
```

## Procesos en el VPS (supervisor/systemd)

| Proceso | Comando | Rol |
|---|---|---|
| web | php-fpm / nginx → `public/` | API HTTP + `/broadcasting/auth` |
| worker | `php artisan queue:work redis --tries=3` | Jobs + broadcasts encolados |
| scheduler | cron `* * * * * php artisan schedule:run` | renewals + (si queue=database) drain |
| reverb | `php artisan reverb:start --host=0.0.0.0 --port=8080` | WebSockets |

Proxy nginx (WSS en 443 → Reverb 8080):

```nginx
location /app {
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_pass http://127.0.0.1:8080;
}
```

## Variables `.env` (VPS)

```env
QUEUE_CONNECTION=redis
CACHE_STORE=redis
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=vaultern
REVERB_APP_KEY=tu-app-key
REVERB_APP_SECRET=tu-app-secret
REVERB_HOST=0.0.0.0
REVERB_PORT=8080
REVERB_SCHEME=http

REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
```

## Flutter (build prod con WS)

```bash
fvm flutter build apk --flavor production \
  --dart-define=REALTIME_ENABLED=true \
  --dart-define=REVERB_HOST=apivaulternbackend.haaspes.space \
  --dart-define=REVERB_PORT=443 \
  --dart-define=REVERB_SCHEME=wss \
  --dart-define=REVERB_APP_KEY=tu-app-key
```

Sin esas flags: la app usa FCM + soft refresh (modo cPanel).

## Escalabilidad (Fase 4)

1. **Redis** para queue + cache (analytics versionados por familia).
2. **Reverb scaling**: `REVERB_SCALING_ENABLED=true` + Redis pub/sub si hay >1 nodo Reverb.
3. **No Presence** en MVP (canales privados bastan).
4. Migración cPanel → VPS: mismo código; solo cambia `.env` y procesos.

## Checklist

- [ ] `GET /api/v1/health` OK
- [ ] `POST /api/broadcasting/auth` con Bearer → 200
- [ ] `reverb:start` escuchando
- [ ] `queue:work` consumiendo
- [ ] 2 dispositivos: mutar tarea/mensaje/finance → UI sin pull manual
- [ ] App background: FCM sigue actualizando
