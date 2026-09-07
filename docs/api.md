# API Overview

The backend exposes a versioned API namespace under /api/v1.

## Health

- GET /api/v1/health

## Response format

Successful responses follow this format:

```json
{
  "success": true,
  "data": {},
  "message": "Request successful"
}
```

Error responses follow this format:

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": []
}
```
