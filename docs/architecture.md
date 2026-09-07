# Architecture notes

Dailzo uses a modular monolith backend in NestJS that is organized by domain modules such as auth, products, cart, orders, payments, and notifications. The mobile Flutter app and admin web app are separated frontends that communicate with the shared backend over REST APIs.

The initial Phase 1 implementation focuses on the core scaffold, health endpoint, and local development environment so the project can be run and extended safely.
