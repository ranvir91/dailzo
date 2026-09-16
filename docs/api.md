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

**Delivery-partner app** — a separate client, separate auth principal
(`DeliveryPartner`, not `User`), namespaced under `/partner/*`:

- `POST /partner/login` — `{phone_number, password}` → `{token, refreshToken, partnerId, name}`
- `GET /partner/orders` — `?date=YYYY-MM-DD&status=ALL|MY_ORDERS|PENDING|COMPLETED|OUT_FOR_DELIVERY`
- `PATCH /partner/orders/{orderId}/status` — `{status: "COMPLETED"|"OUT_FOR_DELIVERY", comment?}`
- `POST /partner/orders/{orderId}/comments` — `{comment}` (incident notes)
- `GET /partner/search` — `?query=` (partners for reassignment; empty result below 3 chars)
- `POST /partner/orders/{orderId}/reassign` — `{target_partner_id, reason}`

All require `Authorization: Bearer <token>` from `/partner/login` (rejected for
customer/admin tokens, and vice versa). See `server/README.md` for why this
isn't at the same paths as the customer app's `/auth/login` and `/orders`.

See `server/routes/api.php` for the full list and `docs/php-migration-plan.md`
for the port from the previous NestJS API.
