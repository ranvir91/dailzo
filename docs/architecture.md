# Architecture notes

Dailzo has two parts:

- **`server`** — a Laravel 11 application that serves both the REST API
  (`/api/v1`, consumed by the mobile app) and the admin panel (`/admin`, built
  with Filament v3). MySQL is the source of truth. Auth is Laravel Sanctum
  tokens issued through a phone/OTP flow; the admin panel uses email + password.
  Code is organised by domain: `app/Http/Controllers/Api/V1`, `app/Models`,
  `app/Services`, `app/Filament`.
- **`apps/mobile`** — the Flutter customer app, a separate frontend that talks to
  the API over REST.

The backend and admin were originally a NestJS/Prisma API plus a React SPA; they
were rewritten to PHP — see [php-migration-plan.md](php-migration-plan.md).

Placeholder integrations (payments, delivery, push notifications) return stub
responses and are wired to real providers post-launch.
