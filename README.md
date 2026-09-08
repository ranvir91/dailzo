# Dailzo MVP

Dailzo is a mobile-first grocery and daily essentials ordering platform.

## Repository structure

- `apps/mobile` — Flutter customer app
- `server` — Laravel 11 + Filament backend: REST API (`/api/v1`) and admin panel (`/admin`), MySQL
- `docs` — architecture, API and migration docs
- `infrastructure` — deployment placeholders

The backend + admin were rewritten from NestJS/React to PHP —
see [docs/php-migration-plan.md](docs/php-migration-plan.md).

## Quick start

```bash
# 1. Local MySQL (host port 3307)
docker compose up -d mysql

# 2. Backend API + admin panel
cd server
composer install
cp .env.example .env
php artisan key:generate     # only if APP_KEY is empty
php artisan migrate --seed
php artisan serve            # http://localhost:8000

# 3. Mobile app
cd ../apps/mobile
flutter run --dart-define=API_PORT=8000
```

- API health: http://localhost:8000/api/v1/health
- Admin panel: http://localhost:8000/admin — `admin@dailzo.app` / `password`

See [server/README.md](server/README.md) for details and demo accounts.
