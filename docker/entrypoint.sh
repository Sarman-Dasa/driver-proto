#!/bin/sh
set -e

# Ensure directory structure exists
mkdir -p \
    /var/www/html/storage/app/public \
    /var/www/html/storage/app/private \
    /var/www/html/storage/framework/cache/data \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/logs \
    /var/www/html/bootstrap/cache

# Adjust permissions on writable directories
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache 2>/dev/null || true

# Wait for database if configured
if [ -n "$DB_HOST" ] && [ "$DB_CONNECTION" = "mysql" ]; then
    echo "Checking database connection at $DB_HOST:${DB_PORT:-3306}..."
    max_tries=30
    count=0
    until php -r '
        $host = getenv("DB_HOST");
        $port = getenv("DB_PORT") ?: 3306;
        $db = getenv("DB_DATABASE");
        $user = getenv("DB_USERNAME");
        $pass = getenv("DB_PASSWORD");
        try {
            $pdo = new PDO("mysql:host={$host};port={$port};dbname={$db}", $user, $pass, [PDO::ATTR_TIMEOUT => 2]);
            exit(0);
        } catch (\Throwable $e) {
            exit(1);
        }
    ' 2>/dev/null; do
        count=$((count + 1))
        if [ "$count" -ge "$max_tries" ]; then
            echo "Warning: Database connection timeout reached ($max_tries attempts). Continuing..."
            break
        fi
        sleep 1
    done
    echo "Database check complete."
fi

# Ensure storage symlink exists
if [ ! -L /var/www/html/public/storage ]; then
    php /var/www/html/artisan storage:link 2>/dev/null || true
fi

# If first arg is `-f` or starts with `-`, prepend php-fpm
if [ "${1#-}" != "$1" ]; then
    set -- php-fpm "$@"
fi

exec "$@"

