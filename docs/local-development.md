# Local development

## Prerequisites

- PHP 8.2+ with extensions: `bcmath ctype curl dom fileinfo intl mbstring openssl pdo_mysql tokenizer xml zip gd`
- Composer 2
- Docker (for local MySQL) — or an existing MySQL/MariaDB server
- Flutter 3.x (for the mobile app)

## Start the database

```bash
docker compose up -d mysql
```

MySQL is exposed on host port **3307** (to avoid clashing with a system MySQL on
3306). Credentials: `dailzo` / `dailzo`, database `dailzo`.

## Backend API + admin panel (`server/`)

```bash
cd server
composer install
cp .env.example .env          # adjust DB_* if not using the docker MySQL
php artisan key:generate      # only if APP_KEY is empty
php artisan migrate --seed
php artisan serve
```

- API: http://localhost:8000/api/v1/health
- Admin: http://localhost:8000/admin

Demo accounts use phone/OTP login; while `OTP_DEBUG=true` the OTP is returned in
the `/api/v1/auth/send-otp` response.

| Phone | Role |
|---|---|
| 9999999999 | CUSTOMER |
| 9999999998 | ADMIN |

## Mobile app (`apps/mobile/`)

```bash
cd apps/mobile
flutter pub get
flutter run --dart-define=API_HOST=10.0.2.2 --dart-define=API_PORT=8000
```

(`10.0.2.2` is the host machine from the Android emulator; use your LAN IP for a
physical device.)

## Admin panel

http://localhost:8000/admin — log in with `admin@dailzo.app` / `password`
(seeded). Create more admins with `php artisan make:filament-user` or by setting
`role = ADMIN` + a password on a user in the User resource.
