# Deployment

## Production with Docker Compose

```bash
# 1. Compose settings
cp .env.example .env
#    APP_PORT=80, DB_ROOT_PASSWORD=<strong>, DB_USERNAME=eversoft, DB_PASSWORD=<strong>, IMAGE_TAG=<version>

# 2. Laravel settings
cp backend/.env.example backend/.env
#    APP_ENV=production, APP_DEBUG=false, APP_URL/FRONTEND_URL=https://crm.eversoft.uz,
#    DB_USERNAME / DB_PASSWORD = the dedicated user above (never root),
#    MAIL_* for password-reset and notification e-mail, LOG_LEVEL=info

# 3. Build and start (images contain the code; no source is mounted)
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build

# 4. One time: application key, then migrations (later deploys migrate automatically, AUTO_MIGRATE=true)
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec backend php artisan key:generate --show
#    put the key in backend/.env as APP_KEY=..., then:
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

Do **not** run `db:seed` in production. It creates demo tenants with known passwords. Create the
first platform administrator explicitly:

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml exec backend php artisan tinker --execute='
$u = App\Models\User::create(["name" => "Platform Admin", "email" => "admin@eversoft.uz", "password" => "<strong password>", "status" => "active"]);
$u->forceFill(["platform_role" => "platform_super_admin"])->save();'
```

Then sign in, open **Organizations** and create tenants. Each tenant gets its system roles, a default
pipeline, a default work schedule and its owner account.

### What production mode changes

- **PHP image:** `composer install --no-dev`, optimized autoloader, OPcache with timestamps off and
  JIT. It runs as `www-data` and contains no `.env`, `vendor` or VCS data from the host
  (`.dockerignore`).
- **Container start:** `config:cache`, `route:cache`, `view:cache` and `event:cache` run on startup.
- **Nginx image:** serves the built SPA, caches `/assets/*` for a year (immutable) and never caches
  `index.html`.
- **Network exposure:** MySQL and PHP-FPM are not published; Nginx is the only entry point.
- **Storage:** uploads and logs live in the `backend-storage` volume.

## Domains and TLS

- Point `crm.eversoft.uz` and `*.crm.eversoft.uz` (for tenant subdomains) at the server.
- **Terminate TLS in Nginx:** mount certificates at `/etc/nginx/certs` (for example from certbot; the
  wildcard needs a DNS-01 challenge), uncomment the `443` server in `nginx/production.conf`, enable
  HSTS in `nginx/snippets/security-headers.conf`, and add the HTTP→HTTPS redirect.
- **Or terminate TLS upstream** (load balancer or Cloudflare). Laravel trusts `X-Forwarded-*`
  (`trustProxies('*')`), and Nginx passes the scheme through to PHP.
- **Tenant hosts:** set `TENANT_BASE_DOMAIN=crm.eversoft.uz`. Requests on `acme.crm.eversoft.uz` are
  accepted only for users of the organization with slug `acme`. Custom domains are set per
  organization (`organizations.domain`) by platform admins.

## Scheduler and queue

These run as their own containers (`scheduler`: `schedule:work`; `queue`: `queue:work` on Redis).

| Schedule | Task |
|---|---|
| every 5 min | `attendance:sync --queue` (one unique job per device) |
| every 10 min | `attendance:process-pending` |
| hourly at :15 | `attendance:close-day` (each organization's own "yesterday") |
| every 5 min | `activities:remind-due` |
| every minute | scheduler heartbeat (shown on **System health**) |
| daily | prune expired tokens, prune old failed jobs |

Scale the queue with `docker compose … up -d --scale queue=3`. Device jobs are unique per device.

## Storage (S3-compatible)

Attachments use `DOCUMENTS_DISK`. To move them to S3, MinIO or DigitalOcean Spaces:

```bash
docker compose exec backend composer require league/flysystem-aws-s3-v3
# backend/.env: DOCUMENTS_DISK=s3, AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_DEFAULT_REGION, AWS_BUCKET, AWS_ENDPOINT
```

Only metadata is kept in MySQL. File paths are namespaced per tenant.

## Backups

```bash
# Database (consistent, no locks for InnoDB)
docker compose exec -T mysql sh -c 'mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines eversoft_crm' | gzip > backup-$(date +%F).sql.gz
# Files (uploads)
docker run --rm -v crmeversoftuz_backend-storage:/s -v "$PWD":/b alpine tar czf /b/storage-$(date +%F).tgz -C /s app
```

Keep `APP_KEY` safe. Device passwords are encrypted with it and are unreadable without it.

## Observability

- **Logs** are in `storage/logs`:
  - `laravel-*.log`
  - `attendance-*.log` (JSON)
  - `hikvision-*.log` (JSON)
  - `security-*.log` (JSON: failed logins, rejected bridge tokens, tenant host mismatches)
- **Health:**
  - `GET /api/v1/ping` is a public liveness probe for load balancers.
  - **Settings → System health** (tenant) and **Platform → System health** show the database, cache,
    queue (pending and failed), scheduler heartbeat and devices.
- **Sentry:** `composer require sentry/sentry-laravel` and set `SENTRY_LARAVEL_DSN`. Exceptions
  already go through Laravel's reporter.
- **Prometheus/Grafana:** export PHP-FPM status (`/fpm-status` is enabled inside the container)
  with php-fpm_exporter, and Nginx with nginx-prometheus-exporter.

## Upgrades

```bash
git pull
IMAGE_TAG=<new version> docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

Migrations run automatically on the backend container. Restart the queue so workers load the new
code: `docker compose … restart queue`.

## Troubleshooting

| Symptom | Fix |
|---|---|
| 502 from Nginx | `docker compose ps`: is `backend` healthy? `docker compose logs backend`. The backend waits for MySQL on start. |
| `APP_KEY is missing` in the logs | Generate a key and put it in `backend/.env` |
| Permission denied on `storage/` (development) | PHP runs as `www-data` mapped to UID 1000; build with `UID=$(id -u) GID=$(id -g) docker compose build backend` |
| Scheduler shows "Needs attention" | The `scheduler` container is not running, or Redis/cache is down |
| Queue "pending" keeps growing | Check the `queue` container logs; scale workers |
| Attendance looks shifted by hours | Organization timezone, terminal clock and timezone; see [ATTENDANCE.md](ATTENDANCE.md) |
| Tests wiped data | Impossible by design: `phpunit.xml` forces `eversoft_crm_test`, and the base test case refuses any database not ending in `_test` |
