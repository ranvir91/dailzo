# Dailzo implementation plan

## Phase 1 goals

- Create the monorepo structure for the mobile app, admin app, backend, infrastructure, docs, and scripts.
- Scaffold a NestJS backend with a health endpoint and versioned API prefix.
- Add Docker Compose services for PostgreSQL and Redis.
- Provide environment configuration and setup documentation.
- Prepare a foundation for later authentication, orders, inventory, payments, notifications, and admin workflows.

## Proposed structure

- apps/mobile: Flutter customer application
- apps/admin: React or Next.js admin application
- backend: NestJS modular monolith
- infrastructure: Docker, AWS, and Terraform placeholders
- docs: architecture and operations documentation

## Design decisions

- Use a modular monolith backend so modules can later be extracted.
- Keep all secrets in environment variables.
- Use PostgreSQL as the source of truth and Redis for local support and future caching.
- Delay full payment, notification, and admin feature implementation until core domain modules are in place.

## Ambiguities to resolve later

- The exact payment provider for production will be selected once credentials are available.
- Firebase Cloud Messaging configuration will be added once a real project is provisioned.
- The final admin UI framework can be React or Next.js; for now a placeholder structure is included.
