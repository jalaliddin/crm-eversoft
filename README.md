# Eversoft CRM

Multi-tenant SaaS CRM + HR for **crm.eversoft.uz**. On the CRM side it covers customers, leads, deals,
activities and a timeline. On the HR side it covers employees, work schedules, biometric attendance
(Hikvision), penalties and payroll. Every organization is a fully isolated tenant.

| Part | Stack |
|---|---|
| `backend/` | Laravel 12, PHP 8.3, MySQL 8, Redis, Sanctum, queues, scheduler |
| `frontend/` | React 19, TypeScript (strict), Mantine 9, React Router 7, TanStack Query, React Hook Form + Zod, Recharts |
| `hik-bridge/` | Node.js agent for terminals on a private network (optional) |
| `browser-bridge/` | Chrome/Edge extension: an office browser with the CRM open reads the terminal (optional) |
| `docker/`, `nginx/` | Docker Compose stack and Nginx reverse proxy |

## Quick start (Docker)

```bash
cp .env.example .env                  # compose settings (APP_PORT=8080 by default)
cp backend/.env.example backend/.env  # Laravel settings
docker compose up -d --build
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan migrate --seed
```

Open http://localhost:8080 and sign in with a demo account. Every demo password is `password`.

| Account | Sees |
|---|---|
| `owner@alpha.test` | Everything in tenant **Alpha Trade LLC** |
| `hr@alpha.test` | HR, attendance, penalties, payroll calculation |
| `sales@alpha.test` / `agent@alpha.test` | CRM (the agent sees only their own leads and deals) |
| `employee@alpha.test` | Own attendance |
| `owner@beta.test` | Tenant **Beta Services**. Never sees Alpha data |
| `admin@eversoft.uz` | Platform admin: manages organizations, sees no tenant data |

Seed data is prefixed per tenant ("Alpha Customer A…" vs "Beta Customer A…"), so isolation is easy
to check. The seeder generates 45 days of attendance through the real pipeline: simulated terminal →
raw events → attendance engine → penalties → payroll.

Useful commands:

```bash
docker compose logs -f
docker compose exec backend php artisan migrate
docker compose exec backend php artisan db:seed
docker compose exec backend php artisan test
docker compose exec backend php artisan attendance:sync          # pull terminals now
docker compose exec backend php artisan attendance:close-day     # mark yesterday's absences
```

PHP containers run as `www-data`, mapped to your host UID in development, so files that artisan
creates stay editable.

## Local development without Docker

Requirements: PHP 8.3 (pdo_mysql, intl, gd, zip, bcmath), Composer, Node 22, MySQL 8.

```bash
cd backend
cp .env.example .env    # then set DB_HOST=127.0.0.1, CACHE_STORE=database,
                        # QUEUE_CONNECTION=database, SESSION_DRIVER=array
composer install && php artisan key:generate
mysql -uroot -p -e "CREATE DATABASE eversoft_crm; CREATE DATABASE eversoft_crm_test;"
php artisan migrate --seed
php artisan serve --port=8000        # API on :8000
php artisan queue:work               # separate terminal
php artisan schedule:work            # separate terminal

cd ../frontend
cp .env.example .env.local
npm install
npm run dev                          # http://localhost:5173, proxies /api to :8000
```

## Quality gates

```bash
cd backend && php artisan test        # tenancy, RBAC, CRM, attendance math, Hikvision, payroll
cd backend && ./vendor/bin/pint --test
cd frontend && npm run lint           # oxlint (zero warnings) + strict TypeScript
cd frontend && npm run build
cd hik-bridge && npm test
cd browser-bridge && npm test
```

Tests always run against a `*_test` database and refuse to start otherwise.

## Documentation

| Document | Covers |
|---|---|
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Modules, layers, request flow, future modules |
| [docs/MULTI_TENANCY.md](docs/MULTI_TENANCY.md) | Tenant isolation design and guarantees |
| [docs/CRM.md](docs/CRM.md) | Customers, leads, deals, pipelines, activities, timeline, custom fields |
| [docs/HR.md](docs/HR.md) | Employees, structure, schedules, penalties, payroll |
| [docs/ATTENDANCE.md](docs/ATTENDANCE.md) | Calculation rules, timezone strategy, day closing, corrections |
| [docs/HIKVISION.md](docs/HIKVISION.md) | Device integration, bridge agent, on-device verification checklist |
| [docs/API.md](docs/API.md) | REST API conventions and endpoint reference |
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | Production deployment, TLS, backups, troubleshooting |
