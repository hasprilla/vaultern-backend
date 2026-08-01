# Vaultern — Zumifly API Backend

API REST para Zumifly Family Hub.

| Entorno | Cache / Cola | Realtime | Notas |
|---|---|---|---|
| **cPanel (prod actual)** | file + MySQL `jobs` | FCM (`BROADCAST=log`) | `https://apivaulternbackend.haaspes.space` |
| **Docker local / Railway** | Redis | FCM (+ Reverb opcional) | Evolución futura |

## Stack

- Laravel 13 / PHP 8.3+
- MySQL 8
- Redis 7 (Docker/Railway)
- Cola: `redis` (Docker/Railway) o `database` (cPanel)
- Push: Firebase FCM
- WebSockets: Laravel Reverb **opcional** (perfil `realtime`)

Auth: tokens propios (`TokenService` / `api_tokens`), no Sanctum de sesión.

---

## Docker local (espejo Railway)

```bash
cd vaultern-backend
cp docker/.env.docker.example .env.docker
make up
make seed   # hh@yopmail.com / password
curl -s http://127.0.0.1:8000/api/v1/health
```

Servicios: `app`, `mysql`, `redis`, `queue`, `scheduler`.

Realtime WS local:

```bash
make up-realtime
```

Guía Railway: [docs/RAILWAY.md](docs/RAILWAY.md)

---

## cPanel (sin Redis)

1. `.env.cpanel.example` → `.env`
2. `composer install --no-dev --optimize-autoloader` (o vendor trackeado)
3. `php artisan migrate --force`
4. Cron cada minuto: `php artisan schedule:run`
5. `QUEUE_CONNECTION=database`, `CACHE_STORE=file`, `BROADCAST_CONNECTION=log`
6. Hotfix Home/Familia: `bash scripts/cpanel-migrate-hotfix.sh`

---

## API

Prefijo: `/api/v1` — Bearer token.

Health: `GET /api/v1/health`

**Frontend:** [zumifly-flutter](https://github.com/hasprilla/zumifly-flutter)
