# Local development

## Prerequisites

- Docker Desktop or Docker Engine
- Node.js 20+
- npm 10+

## Start dependencies

```bash
docker compose up -d postgres redis
```

## Backend

```bash
cd backend
cp .env.example .env
npm install
npx prisma generate
npm run prisma:migrate
npm run prisma:seed
npm run start:dev
```

The API will be available at http://localhost:3000/api/v1/health.
