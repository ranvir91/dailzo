# API Overview

The backend (Laravel, in `server/`) exposes a versioned API under `/api/v1`.
Auth is Laravel Sanctum bearer tokens issued by the phone/OTP flow.

## Response format

Success:

```json
{ "success": true, "data": {}, "message": "Request successful" }
```

Error:

```json
{ "success": false, "message": "Validation failed", "errors": [] }
```

## Endpoints

`GET /api/v1/health`

**Auth** — `POST /auth/send-otp`, `/auth/verify-otp`, `/auth/login`, `/auth/refresh-token`

**Public** — `GET /products`, `/products/{id}`, `/categories`, `/coupons`,
`/coupons/{code}`, `/settings/store`, `/serviceable-pincodes`,
`/settings/pincodes/lookup/{pincode}`

**Authenticated** (`Authorization: Bearer <token>`) — `/users/me`, `/addresses`,
`/cart`, `/orders`

**Admin** (token for an `ADMIN` user) — write routes for products, categories,
coupons, users, settings/pincodes, uploads, order status, delivery, plus
`/admin/dashboard`

See `server/routes/api.php` for the full list and `docs/php-migration-plan.md`
for the port from the previous NestJS API.
