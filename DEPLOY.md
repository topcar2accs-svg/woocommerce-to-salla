# Production deployment

Requirements: PHP 8.2+, Composer, MySQL/MariaDB, HTTPS, cron or a supervised queue worker.

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate --force
php artisan migrate --force
php artisan config:cache
php artisan route:cache
```

Document root must be the repository `public/` directory. `storage/` and `bootstrap/cache/` must be writable by the PHP user.

Run queue processing continuously where possible:

```bash
php artisan queue:work --sleep=2 --tries=3 --timeout=180
```

On shared hosting without a process supervisor, run a cron every minute with `php artisan queue:work --stop-when-empty --tries=3 --timeout=180`.

Configure Salla Partner webhook URL as `https://YOUR-DOMAIN/api/webhooks/salla`, set the webhook secret and the required product scopes in the Partner portal, then perform an install/authorization test using a Salla development store.
