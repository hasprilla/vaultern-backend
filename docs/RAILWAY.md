# Deploy Vaultern en Railway (+ Docker local)

Stack objetivo (sin reescribir Laravel):

| Servicio | Comando | Rol |
|---|---|---|
| **web** | `web` (entrypoint) | HTTP API + `migrate --force` |
| **worker** | `worker` | `queue:work redis` |
| **scheduler** | `scheduler` | `schedule:work` (suscripciones, etc.) |
| Plugins | MySQL + Redis | datos + cola/cache/sesión |

Realtime WS (Reverb) es **opcional**. MVP: `BROADCAST_CONNECTION=log` + FCM (igual que el diseño cPanel).  
Para activar Reverb en VPS/Railway: ver [VPS_REALTIME.md](./VPS_REALTIME.md).

---

## 1. Probar local con Docker (espejo Railway)

```bash
cd vaultern-backend
cp docker/.env.docker.example .env.docker
make up
make seed   # opcional: hh@yopmail.com / password
curl -s http://127.0.0.1:8000/api/v1/health
```

Servicios: `app`, `mysql`, `redis`, `queue`, `scheduler`.

> Nota: el contenedor `app` usa `php artisan serve --no-reload` para que las variables
> Docker (`DB_*`, `REDIS_*`, `CACHE_STORE=redis`) lleguen al worker PHP. Sin ese flag,
> Laravel solo reenvía una whitelist y el `.env` local (p. ej. `DB_CONNECTION=sqlite`)
> pisa la config del contenedor.

Realtime local:

```bash
make up-realtime
# Flutter:
# --dart-define=REALTIME_ENABLED=true --dart-define=REVERB_HOST=127.0.0.1 ...
```

Flutter contra Docker:

```bash
fvm flutter run --flavor development \
  --dart-define=USE_LOCAL_API=true \
  --dart-define=API_HOST=127.0.0.1 \
  --dart-define=REALTIME_ENABLED=false
```

---

## 2. Railway

1. Nuevo proyecto → **Deploy from GitHub** (`vaultern-backend`).
2. Añadir plugins: **MySQL** + **Redis**.
3. Crear **3 servicios** desde el mismo repo/imagen:
   - **web** — Start command: `web` (o vacío; `CMD` del Dockerfile).
   - **worker** — Start command: `worker`.
   - **scheduler** — Start command: `scheduler`.
4. Variables: copiar `.env.railway.example` y mapear referencias a MySQL/Redis.
5. Generar `APP_KEY` (`php artisan key:generate --show`) y pegarlo.
6. Subir `storage/app/firebase-credentials.json` (volume/secret) y `FIREBASE_FCM_ENABLED=true`.
7. Healthcheck: `/api/v1/health`.
8. Apuntar Flutter prod a `https://TU-SERVICIO.up.railway.app/api/v1`.

Dockerfile: `docker/Dockerfile` stage `production` + `docker/entrypoint.sh`.

---

## 3. Compatibilidad cPanel

Sigue siendo válida: `QUEUE_CONNECTION=database` + cron `schedule:run` + `BROADCAST_CONNECTION=log` + sin Redis.  
Docker/Railway usan Redis porque hay procesos persistentes; cPanel no.

---

## 4. Checklist post-deploy

- [ ] `GET /api/v1/health` → 200 + database ok  
- [ ] Login `hh@yopmail.com` / seed  
- [ ] `GET /families` y `/dashboard/analytics` → 200  
- [ ] Worker consume jobs (`NotifyFamilyJob`)  
- [ ] Scheduler corre `subscriptions:renew`  
- [ ] Flutter apunta a la URL Railway  
