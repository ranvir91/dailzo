# Dailzo MVP

Dailzo is a mobile-first grocery and daily essentials ordering platform. This repository contains the initial Phase 1 foundation for a modular monolith architecture that can later evolve into a larger distributed platform.

## Current Phase

Phase 1 focuses on:
- repository structure and documentation
- backend scaffold with a health endpoint
- Docker Compose for local PostgreSQL and Redis
- environment variable templates
- placeholder mobile and admin application directories

## Repository Structure

- apps/mobile: Flutter customer app placeholder
- apps/admin: React/Next.js admin placeholder
- backend: NestJS + TypeScript modular monolith
- infrastructure: Docker, AWS, and Terraform placeholders
- docs: architecture and setup documentation

## Quick start

1. Copy .env.example to .env and adjust values.
2. Start local dependencies:
   docker compose up -d postgres redis
3. Install backend dependencies:
   cd backend && npm install
4. Start the backend:
   npm run start:dev
5. Start the admin panel:
   cd apps/admin && npm install && npm run dev

The backend health endpoint will be available at:
- http://localhost:3000/api/v1/health

The admin panel will be available at:
- http://localhost:5173
