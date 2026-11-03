# BillFlow — Docker Deployment & Supervisor Queue Worker Guide

This document details the production-oriented Docker deployment architecture, local development workflow, Supervisor-managed queue processing, and operational runbooks for **BillFlow SaaS** (Laravel 13 + Vue 3 / Vuetify 3 / Vite monorepo).

---

## 1. Repository & Monorepo Architecture

BillFlow is structured as a unified monorepo where the Laravel 13 backend and Vue 3 frontend coexist seamlessly:

```
├── app/                      # Laravel application core (Models, Jobs, Http, Services)
│   ├── Jobs/                 # Queue jobs (SendInvoiceEmailJob, ProcessStripeWebhookJob)
│   └── Services/             # Domain logic (Stripe Connect, Invoicing, Subscriptions)
├── bootstrap/                # Application bootstrap & providers (bootstrap/app.php)
├── config/                   # Configuration files (queue, database, sanctum, logging)
├── database/                 # Migrations, seeders, and model factories
├── docker/                   # Container configuration files
│   ├── entrypoint.sh         # App/Queue/Scheduler entrypoint & readiness checker
│   ├── nginx/
│   │   ├── default.conf      # Nginx server block (SPA routing, asset caching, fastcgi)
│   │   └── Dockerfile        # Standalone Nginx build recipe
│   ├── php/
│   │   ├── php.ini           # Production PHP runtime directives
│   │   ├── opcache.ini       # Production OPcache optimization directives
│   │   └── php-fpm.conf      # PHP-FPM pool configuration (ports, health endpoints)
│   └── supervisor/
│       ├── supervisord.conf  # Supervisor daemon configuration
│       ├── queue-worker.conf # Laravel queue worker process manager configuration
│       └── start-queue.sh    # Dynamic queue worker startup script
├── docs/
│   └── docker-deployment.md  # This documentation file
├── public/                   # Web root (Vite manifests and public assets)
│   └── build/                # Compiled Vue 3 frontend production bundles
├── resources/
│   ├── css/                  # Frontend styles
│   ├── js/                   # Vue 3 SPA (TypeScript, Vuetify 3, Pinia, Vue Router)
│   └── views/                # Blade views (app.blade.php SPA entrypoint)
├── routes/                   # Routing (api.php, web.php, console.php)
├── scripts/
│   └── deploy.sh             # Zero/minimal downtime production deployment script
├── storage/                  # Persistent runtime storage (logs, uploads, framework cache)
├── .dockerignore             # Excludes secrets, vendor, node_modules from images
├── .env.docker.example       # Template environment for Docker containers
├── docker-compose.yml        # Development and standard Docker Compose services
├── docker-compose.prod.yml   # Production Compose overrides (port security, restart rules)
└── Dockerfile                # Multi-stage production build (Node build + PHP 8.3 FPM)
```

---

## 2. Docker Architecture

The setup separates concerns cleanly across specialized container services communicating over a private bridge network (`billflow-network`):

```
                     Internet / Client Requests
                                 │
                                 ▼
                     ┌───────────────────────┐
                     │   billflow_web        │ (Port 80/8000)
                     │   (Nginx 1.27)        │
                     └───────────┬───────────┘
                                 │
           ┌─────────────────────┼─────────────────────┐
           │ Static /build/      │ /api/* & .php       │ /up Healthcheck
           │ Assets directly     │ FastCGI:9000        │
           ▼                     ▼                     ▼
     (Disk Cache)      ┌──────────────────┐    ┌──────────────────┐
                       │   billflow_app   │    │   billflow_web   │
                       │ (PHP 8.3-FPM)    │    │ (Nginx /up 200)  │
                       └─────────┬────────┘    └──────────────────┘
                                 │
                                 ▼
               Shared Database & Cache Network
            ┌────────────────────┴────────────────────┐
            ▼                                         ▼
  ┌───────────────────┐                     ┌───────────────────┐
  │    billflow_db    │                     │  billflow_redis   │
  │    (MySQL 8.4)    │                     │  (Redis 7 Alpine) │
  └─────────▲─────────┘                     └─────────▲─────────┘
            │                                         │
            └────────────────────┬────────────────────┘
                                 │
                 ┌───────────────┴───────────────┐
                 │                               │
                 ▼                               ▼
       ┌──────────────────┐            ┌──────────────────┐
       │  billflow_queue  │            │billflow_scheduler│
       │ (Supervisor:     │            │(php artisan      │
       │  queue:work)     │            │ schedule:work)   │
       └──────────────────┘            └──────────────────┘
```

### Services Summary

| Service | Image | Role | Healthcheck |
| :--- | :--- | :--- | :--- |
| `web` | `billflow-web:latest` | Nginx web server; serves Vue SPA static assets with 1-year immutable cache; proxies PHP requests to `app:9000`. | `wget -qO- http://127.0.0.1/up` |
| `app` | `billflow-app:latest` | PHP 8.3-FPM application process pool. | `php -r '@fsockopen("127.0.0.1", 9000)'` |
| `queue` | `billflow-app:latest` | Dedicated Supervisor container running Laravel queue workers (`php artisan queue:work`). | `supervisorctl status queue-worker:*` |
| `scheduler`| `billflow-app:latest` | Dedicated task scheduler container running `php artisan schedule:work`. | Process alive |
| `db` | `mysql:8.4` | MySQL relational database with persistent storage volume (`db_data`). | `mysqladmin ping` |
| `redis` | `redis:7-alpine` | In-memory store for cache, sessions, and optional Redis queues (`redis_data`). | `redis-cli ping` |

---

## 3. Required Environment Variables

Copy `.env.docker.example` to `.env`:

```bash
cp .env.docker.example .env
```

Key environment configuration variables:

| Variable | Recommended / Default | Description |
| :--- | :--- | :--- |
| `APP_ENV` | `local` (dev) / `production` | Application execution environment |
| `APP_DEBUG` | `true` (dev) / `false` (prod) | Stack trace display toggle |
| `APP_KEY` | `base64:...` | 32-character application encryption key (`php artisan key:generate`) |
| `APP_URL` | `http://localhost:8000` | Fully-qualified public application URL |
| `APP_PORT` | `8000` | Host port mapped to Nginx port 80 |
| `DB_CONNECTION` | `mysql` | Laravel database connection driver |
| `DB_HOST` | `db` (local) or RDS hostname | Database server hostname |
| `DB_PORT` | `3306` | Database server port |
| `DB_DATABASE` | `billflow` | Database name |
| `DB_USERNAME` | `billflow` | Database user |
| `DB_PASSWORD` | `billflow_secret` | Database user password |
| `DB_ROOT_PASSWORD` | `root_secret` | MySQL root administrative password |
| `FORWARD_DB_PORT` | `3307` | Host port exposed for external DB clients (avoids conflicts with local MySQL on 3306) |
| `QUEUE_CONNECTION` | `database` (or `redis`) | Queue backend driver |
| `DB_QUEUE_RETRY_AFTER` | `120` | Database queue retry interval (must be > `QUEUE_TIMEOUT`) |
| `QUEUE_TIMEOUT` | `90` | Maximum job execution seconds before worker timeout |
| `QUEUE_TRIES` | `3` | Maximum attempts for failed queue jobs |
| `QUEUE_SLEEP` | `3` | Seconds to poll when no jobs are pending |
| `REDIS_HOST` | `redis` | Redis container hostname |
| `REDIS_PORT` | `6379` | Redis port |
| `SANCTUM_STATEFUL_DOMAINS`| `localhost,localhost:8000,127.0.0.1:8000` | Stateful domains for SPA session authentication |
| `STRIPE_KEY` | `pk_test_...` | Stripe publishable key |
| `STRIPE_SECRET` | `sk_test_...` | Stripe secret API key |
| `STRIPE_WEBHOOK_SECRET` | `whsec_...` | Stripe platform webhook signature |
| `STRIPE_CONNECT_WEBHOOK_SECRET`| `whsec_...` | Stripe Connect webhook signature |

---

## 4. Local Development Quickstart

To spin up the entire BillFlow stack locally:

```bash
# 1. Prepare environment file
cp .env.docker.example .env

# 2. Build images and start containers in the background
docker compose up -d --build

# 3. Generate application encryption key (if not already set)
docker compose exec app php artisan key:generate

# 4. Run database migrations
docker compose exec app php artisan migrate

# 5. Create storage symlink
docker compose exec app php artisan storage:link
```

The application is now accessible at:
- Web Application: **http://localhost:8000**
- Healthcheck Endpoint: **http://localhost:8000/up**
- MySQL (from host tools like TablePlus/DBeaver): `127.0.0.1:3307` (user: `billflow`, pass: `billflow_secret`)
- Redis: `127.0.0.1:6379`

---

## 5. Supervisor & Queue Worker Deep Dive

### Worker Configuration (`docker/supervisor/queue-worker.conf`)

The queue worker is managed by Supervisor in a dedicated container (`billflow_queue`), isolated from the HTTP request-handling PHP-FPM process pool:

```ini
[program:queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=/bin/sh /var/www/html/docker/supervisor/start-queue.sh
autostart=true
autorestart=true
startsecs=2
stopwaitsecs=120
stopsignal=TERM
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=false
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0
```

### Critical Reliability Guarantees:
1. **Timeout Synchronization**:
   - `QUEUE_TIMEOUT` is set to `90` seconds.
   - `DB_QUEUE_RETRY_AFTER` is set to `120` seconds.
   - Because `retry_after (120) > timeout (90)`, a slow or executing job is never re-released to a concurrent worker while still executing.
2. **Graceful Shutdown**:
   - `stopsignal=TERM`: When stopping the container or supervisor, it sends `SIGTERM` to Laravel. Laravel finishes processing the active job before terminating.
   - `stopwaitsecs=120`: Supervisor waits up to 120 seconds for the active job to finish before sending `SIGKILL`.
3. **Memory Leak Mitigation**:
   - The startup script passes `--max-time=3600` and `--max-jobs=1000`. Workers gracefully restart after 1 hour or 1,000 processed jobs, preventing long-running PHP memory leaks. Supervisor immediately respawns the worker via `autorestart=true`.
4. **Log Streaming**:
   - Worker logs write directly to `/dev/stdout` and `/dev/stderr`. Container logs capture all job executions via standard `docker logs billflow_queue`.

### Queue Management Commands

```bash
# Check supervisor status and worker process health
docker compose exec queue supervisorctl status

# Restart workers inside the container
docker compose exec queue supervisorctl restart queue-worker:*

# Gracefully reload workers after deploying new code
docker compose exec app php artisan queue:restart

# Inspect failed jobs
docker compose exec app php artisan queue:failed

# Retry all failed jobs
docker compose exec app php artisan queue:retry all

# Retry a specific failed job by UUID
docker compose exec app php artisan queue:retry <uuid>

# Delete a failed job
docker compose exec app php artisan queue:forget <uuid>

# Flush all failed jobs
docker compose exec app php artisan queue:flush
```

---

## 6. Production Deployment Procedure

Production deployments should minimize downtime, guarantee data safety, and avoid running destructive commands:

### Automated Deployment Script

Run the automated deployment script:

```bash
./scripts/deploy.sh
```

### Step-by-Step Manual Deployment Process

If executing manually:

```bash
# Step 1: Build production images reproducibly
docker compose -f docker-compose.yml -f docker-compose.prod.yml build --pull

# Step 2: Start updated containers
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --remove-orphans

# Step 3: Run non-destructive database migrations
docker compose exec -T app php artisan migrate --force

# Step 4: Warm production framework caches
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache
docker compose exec -T app php artisan view:cache
docker compose exec -T app php artisan event:cache

# Step 5: Signal queue workers to reload fresh code
docker compose exec -T app php artisan queue:restart

# Step 6: Verify healthcheck endpoint
curl -f http://127.0.0.1/up
```

### Safety Rules:
- **NEVER** run `php artisan migrate:fresh` or `php artisan migrate:reset` in production.
- **NEVER** run `composer update` during image build or deployment; images strictly use `composer.lock` with `composer install --no-dev`.
- Queue workers must always be signaled via `php artisan queue:restart` after deploying changes to Job classes or services.

---

## 7. Connecting to Managed Services in Production

When deploying to AWS (ECS/EKS/EC2), Google Cloud, DigitalOcean, or Kubernetes with managed database and Redis services:

1. Update `.env`:
   ```dotenv
   DB_HOST=prod-mysql-instance.c9xyz.rds.amazonaws.com
   DB_PORT=3306
   DB_DATABASE=billflow_prod
   DB_USERNAME=billflow_app
   DB_PASSWORD=SecureProductionPassword123!

   REDIS_HOST=prod-redis.xyz.cache.amazonaws.com
   REDIS_PORT=6379
   REDIS_PASSWORD=SecureRedisAuthToken
   ```
2. When using `docker-compose.prod.yml`, the local `db` and `redis` containers are removed from public binding and can be omitted, directing all traffic directly to your managed cloud infrastructure.

---

## 8. Logs, Health Checks, and Troubleshooting

### Inspecting Container Logs

```bash
# View aggregated live logs across all services
docker compose logs -f

# View live web server (Nginx) access & error logs
docker compose logs -f web

# View live PHP-FPM application logs
docker compose logs -f app

# View live queue worker logs (job started, processed, failed)
docker compose logs -f queue

# View scheduler logs
docker compose logs -f scheduler
```

### Health Check Commands

```bash
# Check status and health status of all containers
docker compose ps

# Inspect HTTP healthcheck endpoint
curl -i http://localhost:8000/up

# Test database connection from inside the app container
docker compose exec app php -r '
  try {
    DB::connection()->getPdo();
    echo "Database connection OK" . PHP_EOL;
  } catch (\Exception $e) {
    echo "Database error: " . $e->getMessage() . PHP_EOL;
  }
'
```

---

## 9. Backups and Storage Volumes

### Persistent Volumes
- `db_data`: Houses the MySQL data directory (`/var/lib/mysql`).
- `redis_data`: Houses Redis snapshots / append-only file (`/data`).
- `storage_data`: Houses uploaded invoices, company logos, and generated PDF attachments (`/var/www/html/storage`).

### Backup Procedure

```bash
# Database backup (mysqldump)
docker compose exec -T db mysqldump -u root -p${DB_ROOT_PASSWORD} billflow > backup_$(date +%Y%m%d_%H%M%S).sql

# Storage backup
tar -czvf storage_backup_$(date +%Y%m%d_%H%M%S).tar.gz storage/app/public
```

---

## 10. Rollback Procedure

If a deployed version encounters issues:

1. Check out the previous stable git commit / tag:
   ```bash
   git checkout <previous-tag-or-commit>
   ```
2. Re-run the deployment script:
   ```bash
   ./scripts/deploy.sh
   ```
3. If database schema changes need to be reverted:
   ```bash
   docker compose exec app php artisan migrate:rollback --step=1
   docker compose exec app php artisan queue:restart
   ```

