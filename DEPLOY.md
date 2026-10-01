# Production deployment

Requirements: PHP 8.2+, Composer, MySQL/MariaDB, HTTPS, cron or a supervised queue worker.

## Application setup

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate --force
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan app:health-check
```

Document root must be the repository `public/` directory. `storage/` and `bootstrap/cache/` must be writable by the PHP user.

Use the production defaults in `.env.example`: MySQL, database queue, database cache, database sessions, secure session cookies, `APP_DEBUG=false`, and an HTTPS `APP_URL`. Keep all Salla and WooCommerce secrets outside source control.

## Hostinger shared-hosting layout

The repository can remain in a sibling `app/` directory while `public_html/` receives only Laravel's public files. Use:

```bash
cd /home/USER/domains/YOUR-DOMAIN/app
APP_ROOT="$PWD" PUBLIC_ROOT="../public_html" bash scripts/deploy-hostinger.sh
```

The deployment script updates `main`, installs production Composer dependencies, runs migrations and Laravel caches, publishes `public/` into `public_html/`, rewrites the public `index.php` bootstrap paths for the sibling `app/` directory, and runs the production readiness check.

## Automatic deployment on shared hosting

`scripts/hostinger-auto-deploy.sh` is safe to run repeatedly. It fetches `origin/main`, compares the remote SHA with the last successfully deployed SHA, and only calls the full deployment script when there is a new commit. It also uses a deployment lock to prevent overlapping deploys.

A hosting cron can run it at the shortest interval allowed by the plan:

```bash
cd /home/USER/domains/YOUR-DOMAIN/app && APP_ROOT="$PWD" bash scripts/hostinger-auto-deploy.sh
```

Output is appended to `storage/logs/auto-deploy.log`. The successful deployment SHA is stored under `storage/app/deploy/`.

Hostinger's hPanel Git integration is also supported for pulling the repository itself. When using hPanel Git deployment, keep the Laravel application outside the public web root and still run the deploy/readiness steps so Composer, migrations, caches, queue processing, and `public_html` are updated consistently.

## Queue processing

Run queue processing continuously where possible:

```bash
php artisan queue:work --sleep=2 --tries=3 --timeout=180 --max-time=3600
```

The database queue uses a retry window longer than the worker timeout (`DB_QUEUE_RETRY_AFTER=240`) to reduce duplicate execution if a worker is terminated during a long API request.

On shared hosting without a process supervisor, use the hosting scheduler to run:

```bash
php artisan queue:work --stop-when-empty --tries=3 --timeout=180
```

Run it as frequently as the hosting plan permits. A continuously supervised worker is preferred for production SaaS workloads.

## Health endpoints

- `/health/live` verifies that the PHP/Laravel process can answer requests.
- `/health/ready` verifies production configuration, database connectivity, required tables, non-sync queue configuration, and writable runtime directories. It returns HTTP 503 until all checks pass.
- `php artisan app:health-check` runs the same readiness checks from the CLI without modifying application data.

Do not put credentials or secret values in health output; readiness only reports whether required secrets are configured.

After deployment, an external HTTP smoke check can be run with:

```bash
BASE_URL=https://YOUR-DOMAIN bash scripts/post-deploy-smoke.sh
```

It verifies liveness, readiness, and that the dashboard responds successfully.

## Salla configuration

Configure the Salla Partner webhook URL as:

```text
https://YOUR-DOMAIN/api/webhooks/salla
```

Set the webhook secret and the required product/category scopes in the Partner portal, then perform an install/authorization test using a Salla development store.

## Deployment gate

Before an end-to-end merchant test, all of these must be true:

1. `php artisan migrate:status` succeeds against the production MySQL database.
2. `php artisan app:health-check` exits successfully.
3. `/health/ready` returns HTTP 200.
4. `BASE_URL=https://YOUR-DOMAIN bash scripts/post-deploy-smoke.sh` succeeds.
5. A queue worker is actively processing the database queue.
6. The web server document root serves Laravel public files, not a hosting default page.
7. Salla OAuth callback and webhook URLs use the final HTTPS domain.
8. Production secrets are unique and have not been pasted into source control or public logs.
