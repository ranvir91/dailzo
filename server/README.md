# Dailzo server (Laravel + Filament)

PHP rewrite of the Dailzo backend API and admin panel. Replaces `backend/` (NestJS)
and `apps/admin/` (React) once the port is complete. See
[`../docs/php-migration-plan.md`](../docs/php-migration-plan.md).

- **API** — `/api/v1/*`, same contract/envelope as the old NestJS API (the Flutter
  app in `apps/mobile/` is unchanged).
- **Admin** — Filament panel at `/admin`.
- **DB** — MySQL / MariaDB.
- **Auth** — Laravel Sanctum tokens + phone/OTP login.

## Local setup

```bash
cd server
composer install
cp .env.example .env         # already present; edit DB_* if needed
php artisan key:generate     # only if APP_KEY is empty

# Start MySQL (host port 3307, matches .env):
docker compose -f ../docker-compose.yml up -d mysql

php artisan migrate --seed
php artisan serve            # http://localhost:8000
```

Health check: `curl http://localhost:8000/api/v1/health`

## Tests

```bash
docker compose -f ../docker-compose.yml up -d mysql
composer test          # creates dailzo_test if missing, then runs php artisan test
```

`composer test` runs `bin/setup-test-db.sh` first, which creates the isolated
`dailzo_test` database (the compose `init.sql` only runs on a brand-new MySQL
volume, so an existing container needs this). To run the raw suite:
`php artisan test`. To use SQLite instead, set `DB_CONNECTION=sqlite` /
`DB_DATABASE=:memory:` in `phpunit.xml`.

Demo accounts (mobile: phone/OTP — the OTP is returned in the `/auth/send-otp`
response while `OTP_DEBUG=true`):

| Phone | Role | Filament panel |
|---|---|---|
| 9999999999 | CUSTOMER | – |
| 9999999998 | ADMIN | `admin@dailzo.app` / `password` |

## Port status

| Area | API | Filament |
|---|---|---|
| health | ✅ | – |
| auth (OTP + Sanctum) | ✅ | email+password login |
| settings / pincodes | ✅ | ✅ Store settings page + Pincodes |
| products | ✅ | ✅ |
| categories | ✅ | ✅ |
| cart (DB-backed) | ✅ | – |
| addresses | ✅ | (under Users) |
| orders | ✅ | ✅ (view + status action) |
| coupons | ✅ | ✅ |
| users | ✅ | ✅ |
| uploads | ✅ | native file uploads |
| payments / delivery / notifications (mock) | ✅ | Delivery partners |
| dashboard widgets | – | ✅ StoreOverview |

**Done:** legacy `backend/` + `apps/admin/` removed; legacy images copied into
`public/uploads/`; auth rate limiting; feature tests; GoDaddy deploy docs.

**Not yet done:** run `php artisan migrate --seed` + `php artisan test` against a
DB; restore/repoint the Flutter app (`apps/mobile/` is empty — needs
`API_PORT=8000`); real SMS / Razorpay / FCM. Filament login is email + password
(not OTP) — seeded admin `admin@dailzo.app` / `password`.

Deployment to GoDaddy: [../docs/deployment-godaddy.md](../docs/deployment-godaddy.md).

## Deployment

GoDaddy cPanel shared hosting — see the Deployment section of
[`../docs/php-migration-plan.md`](../docs/php-migration-plan.md).
