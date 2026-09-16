# Dailzo — PHP Migration Plan

Move the backend API and admin panel from **NestJS + Prisma + PostgreSQL** and the
**React/Vite admin** to a single **Laravel 11 + Filament v3** application on **MySQL**.
The Flutter app (`apps/mobile/`) is unchanged and must keep working against the same
`/api/v1` contract.

## Confirmed decisions

| # | Decision |
|---|---|
| 1 | New Laravel app lives in `server/` |
| 2 | Cart is **persisted in the database** (Cart / CartItem tables), replacing the current in-memory cart |
| 3 | Auth uses **Laravel Sanctum** personal access tokens (opaque strings — transparent to clients) |
| 4 | **Fresh MySQL database**, seeded — no data migration from the current Postgres DB |
| 5 | **Redis is dropped** — no code uses it. Cache/session/queue use file/database/sync drivers |
| 6 | Deploy target: **GoDaddy cPanel shared hosting** (see Deployment section) |

## Target architecture

One Laravel app serves:

- `/api/v1/*` — REST API for the Flutter app, byte-compatible with today's contract
- `/admin` — Filament v3 panel (replaces the React SPA)
- `/uploads/*` — uploaded media, served from a real directory under the web root

## Repo layout

```
dailzo/
├── apps/
│   ├── mobile/                  # unchanged; only app_env.dart base URL changes at cutover
│   └── admin/                   # deleted after cutover
├── backend/                     # deleted after cutover (reference during the port)
├── server/                      # NEW — Laravel + Filament
│   ├── app/
│   │   ├── Models/
│   │   ├── Http/Controllers/Api/V1/
│   │   ├── Http/Requests/                 # replaces class-validator DTOs
│   │   ├── Http/Resources/                # emits the exact camelCase JSON clients expect
│   │   ├── Http/Middleware/AdminOnly.php
│   │   ├── Filament/Resources/
│   │   ├── Filament/Pages/StoreSettings.php
│   │   ├── Filament/Widgets/
│   │   ├── Services/                      # OrderService, CouponService, OtpService, ...
│   │   └── Support/ApiResponse.php
│   ├── routes/api.php                     # Route::prefix('v1')
│   ├── database/migrations/
│   ├── database/seeders/
│   └── public/uploads/                    # real writable dir (no symlink needed)
├── docker-compose.yml           # postgres/redis removed; mysql added for local dev
└── docs/
```

## Contract-preservation rules

1. Base path `/api/v1`; response envelope `{success, data, message, errors}`; status codes
   match (400 / 401 / 403).
2. **UUID primary keys stay UUIDs** (`HasUuids`) — client-side ids and stored sessions stay valid.
3. **JSON keys stay camelCase** (`discountedPrice`, `minOrderValueEnabled`, `orderNumber`,
   `iconUrl`, `isActive`, `createdAt`, …). DB columns are snake_case; `Http/Resources/*`
   map between the two.
4. Uploads stay at `/uploads/<file>` — an `uploads` filesystem disk rooted at
   `public_path('uploads')`, `url => '/uploads'`. Existing DB image paths keep resolving.
5. Tokens stay opaque — Sanctum plain-text tokens. `/auth/send-otp` keeps returning the dev
   OTP in the body while `APP_DEBUG=true`.

## Schema translation

| Prisma / Postgres | Laravel / MySQL |
|---|---|
| `id String @default(uuid())` | `$table->uuid('id')->primary()` + `HasUuids` |
| `userNumber` / `orderNumber` autoincrement unique | uuid PK kept; `user_number` / `order_number` = `unsignedInteger unique`, assigned in a model `creating` hook inside a transaction (`max()+1`, base 100000). MVP-scale, effectively serialized on shared hosting — documented tradeoff |
| `images String[]`, `paymentMethods String[]` | `json` column + `$casts => 'array'` |
| `Decimal @db.Decimal(10,2)` | `decimal(col, 10, 2)` + cast `decimal:2` |
| `deletedAt` | `SoftDeletes` trait |
| `createdAt` / `updatedAt` | standard `created_at` / `updated_at`; Resources emit camelCase |
| Coupon IST date parsing (`parseCouponDate`) | ported verbatim into `CouponService` — `YYYY-MM-DD` → IST midnight / 23:59:59.999 |
| Order `STATUS_TRANSITIONS` | `OrderStatus` enum + `canTransitionTo()` |

Entities: User, RefreshToken, Address, Category, Product, StoreSetting, ServicePincode,
Cart, CartItem, Order, OrderItem, Payment, Coupon, CouponUsage, DeliveryPartner,
DeliveryAssignment, Notification.

## Auth (Sanctum + OTP)

- `POST /auth/send-otp` — 4-digit OTP, stored in cache keyed by phone, 300s TTL. Returns
  `{phone, otp, expiresIn: 300}` when `APP_DEBUG`, `{phone, expiresIn: 300}` otherwise.
- `POST /auth/verify-otp` and `POST /auth/login` — verify OTP, find-or-create user
  (`role = CUSTOMER`, `name = 'New Customer'`), issue Sanctum token, create a `refresh_tokens`
  row (random token, sha256-hashed at rest, `expires_at = now +7d`). Response `data`:
  `{ user, accessToken, refreshToken }`.
- `POST /auth/refresh-token` — look up the hashed refresh token, issue a new access token.
- API auth middleware: `auth:sanctum`. Admin-only routes add `AdminOnly` (checks
  `user->role === 'ADMIN'`). Filament panel access = same role check.
- Real SMS (MSG91 / Twilio) is post-cutover; dev behaviour stays until then.

## Module port map

| Nest module | Laravel API | Filament | Logic |
|---|---|---|---|
| health | `HealthController` | — | trivial |
| auth | `AuthController` | phone+OTP login | Sanctum |
| products | `ProductController` (CRUD, `:id` GET/PATCH/DELETE) | `ProductResource` | real |
| categories | `CategoryController` (+ `:id/status`) | `CategoryResource` | real |
| cart | `CartController` (GET, items POST/PATCH/DELETE) | — | now DB-backed |
| addresses | `AddressController` | under UserResource | default-address handling |
| orders | `OrderController` (+ cancel, `:id/status`) | `OrderResource` + status actions | state machine |
| coupons | `CouponController` (admin / active / `:code`) | `CouponResource` | IST dates + validation |
| settings | `SettingController` (store, maintenance-mode, min-order-value, pincodes CRUD) | `StoreSettings` page + `PincodeResource` | singleton row |
| serviceable-pincodes | `ServiceablePincodeController` | — | active list for mobile |
| users | `UserController` (+ `/users/me`) | `UserResource` | soft delete, role |
| uploads | `UploadController` | Filament native uploads | `uploads` disk, 8 MB, `<ts>-<rand>.<ext>` |
| payments | `PaymentController` | — | keep mock shape; Razorpay post-cutover |
| delivery | `DeliveryController` | `DeliveryPartnerResource` | keep mock shape |
| notifications | `NotificationController` | — | keep mock shape; FCM post-cutover |
| admin/dashboard | — | dashboard widgets | replace hardcoded numbers with real queries |

## Phased execution

1. **Scaffold** `server/` — Laravel 11, Filament v3, Sanctum. `.env` / `.env.example`.
   `docker-compose.yml`: drop postgres+redis, add mysql 8 for local dev.
2. **Schema** — migrations + models + factories + a seeder mirroring
   `backend/src/prisma/seed.ts`. Verify parity against the Prisma schema.
3. **Core** — `ApiResponse` helper, exception handler → envelope, CORS, `/api/v1` group,
   `health`, the `uploads` disk.
4. **Auth** — Sanctum + OTP; Flutter and Filament login working against Laravel.
5. **Public read modules** — settings/store, products, categories, serviceable-pincodes,
   coupons(active). Point a dev Flutter build at Laravel; smoke test.
6. **User-scoped modules** — users/me, addresses, orders, cart.
7. **Admin API + mock stubs** — remaining admin endpoints + payments/delivery/notifications
   with identical response shapes.
8. **Filament** — all resources above + dashboard widgets + StoreSettings page.
9. **Cutover** — switch `apps/mobile/lib/config/app_env.dart` base URL; copy
   `backend/uploads/*` → `server/public/uploads/`; delete `backend/` and `apps/admin/`;
   update root `README.md`, `docs/`, `docker-compose.yml`.
10. **Hardening** — real SMS, Razorpay, FCM, rate limiting, Pest tests.

## Status (2026-09-08)

Phases 1–8 built in `server/` and boot-verified (`php artisan serve`, `route:list`,
`route:cache`, container boot). **Not yet run against a database** — do
`php artisan migrate --seed` and smoke-test each endpoint.

- ✅ Scaffold: Laravel 11 + Filament v3 + Sanctum, MySQL, `docker-compose` (mysql on 3307)
- ✅ Schema: 17 migrations + models (`HasUuids`, `AssignsSequentialNumber`, `SerializesToCamelCase`)
- ✅ Core: `ApiResponse`, envelope exception handler, `ForceJsonResponse` + `admin` middleware, `uploads`/`web` disks
- ✅ API — every NestJS route ported: auth (OTP + Sanctum), products, categories,
  cart (DB-backed), addresses, orders (status machine), coupons (IST dates),
  users (+ real `/users/me`), uploads, settings/pincodes, payments/delivery/
  notifications mocks, admin dashboard
- ✅ Filament: resources for Product, Category, Coupon, Order, User,
  ServicePincode, DeliveryPartner; Store settings page; `StoreOverview` dashboard widget
- ⚠️ Filament login is **email + password**, not OTP (seeded admin
  `admin@dailzo.app` / `password`). Revisit if OTP panel login is wanted.
- ✅ Cutover cleanup: `backend/` and `apps/admin/` deleted; 26 legacy images copied
  to `server/public/uploads/`; stale docs removed/updated
- ✅ Rate limiting (`throttle:otp` on auth, `throttle:api` global); force-HTTPS in production
- ✅ PHPUnit feature tests + factories (`server/tests/Feature/*`); `phpunit.xml` → `dailzo_test`
- ✅ `docs/deployment-godaddy.md` + `server/bin/deploy.sh`
- ⬜ Run `php artisan migrate --seed` and `php artisan test` (needs a DB)
- ⬜ Restore/repoint the Flutter app (`apps/mobile/` is currently an empty dir);
  it needs `API_PORT=8000`
- ⬜ Hardening: real SMS (MSG91/Twilio), Razorpay, FCM

## Deployment — GoDaddy cPanel shared hosting

**Preflight (do this first):**
- cPanel → *Select PHP Version* → set **8.2 or 8.3** (Laravel 11 requires ≥ 8.2).
- Enable extensions: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `mbstring`, `openssl`,
  `pdo_mysql`, `tokenizer`, `xml`, `zip`, `gd`, `intl`.
- Confirm whether SSH is available (GoDaddy Deluxe/Ultimate — opt-in in cPanel).

**Layout:** upload `server/` to `~/dailzo-server/` (outside `public_html`). Create a
subdomain `api.<domain>` and set its **document root to `~/dailzo-server/public`**. If a
subdomain isn't an option, use the primary domain and place a modified `index.php` +
`.htaccess` in `public_html/` pointing at `../dailzo-server/`.

**Database:** cPanel → *MySQL Databases* → create DB + user, grant all. Put creds in
`.env` (`DB_CONNECTION=mysql`, host `localhost`).

**Composer:**
- With SSH: `composer install --no-dev --optimize-autoloader` on the host.
- Without SSH: run locally with `--no-dev`, upload `vendor/` via SFTP or cPanel Git.

**Deploy config:**
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.<domain>
CACHE_STORE=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=uploads
```
Force HTTPS in `AppServiceProvider::boot()` when `app()->environment('production')`.

**On each deploy (SSH or a protected artisan route):**
```
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan filament:optimize
```
If no SSH: commit Filament's published assets (`php artisan filament:assets`,
`vendor:publish --tag=filament-*`) so `public/` is deploy-ready without host commands.

**Scheduler (cPanel → Cron Jobs, every minute):**
```
/usr/local/bin/php ~/dailzo-server/artisan schedule:run >> /dev/null 2>&1
```

**Uploads:** `public/uploads` is a real writable directory under the web root — no
`storage:link` symlink needed (symlinks are often disabled on shared hosting). Raise
`upload_max_filesize` / `post_max_size` to ≥ 8 MB via cPanel *MultiPHP INI Editor* or
`.user.ini`.

## Known issue — coupon date storage (found 2026-09-15)

`config/database.php`'s `mysql` connection has no `timezone` override, so Carbon
writes/reads datetime columns using whatever timezone the Carbon instance itself
carries — no conversion happens. With `APP_TIMEZONE=Asia/Kolkata`, `now()` and every
auto-set `created_at`/`updated_at` are stored as **IST wall-clock digits** (verified
against the dev DB).

`CouponService::parseDate()` explicitly converts a bare date to **UTC** before storing
it in `coupons.starts_at` / `expires_at`. That's a mismatch: the column ends up 5h30m
*earlier* than every other timestamp in the database (which are IST, unconverted), so
`Coupon::scopeActive()` compares apples to oranges — a coupon meant to start "on the
15th" actually keys off midnight IST minus 5:30, i.e. becomes active during the previous
evening, and one meant to expire "at the end of the 15th" expires 5:30 early too.

**Fix:** drop the `->utc()` call in `CouponService::parseDate()` (mirror
`App\Support\IstDate`, added for the delivery-partner API, which deliberately does
*not* convert). Not fixed here to avoid touching passing, unrelated coupon tests as a
side effect of an unrelated task — needs its own pass (re-verify `CouponService`'s
existing tests still express the intended behavior once the conversion is removed).
