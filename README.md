# ⚙️ Vaultern — Zumifly API Backend

> Laravel 12 + PHP 8.4. API REST para Zumifly Family Hub.

[![CI Pipeline](https://github.com/hasprilla/vaultern-backend/actions/workflows/ci.yml/badge.svg)](https://github.com/hasprilla/vaultern-backend/actions)
[![Laravel](https://img.shields.io/badge/Laravel-12.x-red.svg)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.4-blue.svg)](https://php.net)

## 🚀 Stack Tecnológico

| Categoría | Tecnología |
|---|---|
| Framework | Laravel 12 |
| Runtime | PHP 8.4 |
| Base de Datos | MySQL 8.0 |
| Cache/Queue | Redis 7 |
| Auth | Laravel Sanctum |
| Queue Worker | Laravel Horizon |
| WebSockets | Laravel Reverb |
| Container | Docker + Docker Compose |
| Orquestación | Kubernetes Ready |
| Cloud | AWS (ECS/EKS + S3 + RDS) |

## 📁 Arquitectura — DDD + Clean Architecture

```
app/
├── Domains/         # Capa de Dominio (Entidades, Value Objects, Eventos)
├── Application/     # Capa de Aplicación (CQRS: Commands, Handlers, Queries)
├── Infrastructure/  # Repositorios Eloquent, Cache, Storage, OCR
└── Http/            # Controladores API REST v1 versionada
```

## ⚙️ Setup con Docker

```bash
cp .env.example .env
docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

## 📡 API Endpoints

```
POST   /api/v1/auth/register
POST   /api/v1/auth/login
POST   /api/v1/auth/mfa/verify
GET    /api/v1/families
POST   /api/v1/tasks
POST   /api/v1/ocr/invoice
GET    /api/v1/finance/reports/monthly
GET    /api/v1/dashboard/analytics
```

## 🔒 Seguridad
- OWASP API Security Top 10
- Argon2id password hashing
- Multi-tenant por familia (family_id scoping)
- Rate Limiting por Redis
- Audit Logs de todos los cambios
- AES-256 para datos sensibles

---
**Frontend:** [zumifly-flutter](https://github.com/hasprilla/zumifly-flutter)
