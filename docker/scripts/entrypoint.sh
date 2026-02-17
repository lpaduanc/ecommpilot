#!/bin/sh
set -e

echo "=== EcommPilot Development Container Starting ==="

# Wait for PostgreSQL to be ready
if [ -n "$DB_HOST" ]; then
    echo "Waiting for PostgreSQL at $DB_HOST:$DB_PORT..."
    while ! nc -z "$DB_HOST" "$DB_PORT" 2>/dev/null; do
        sleep 1
    done
    echo "PostgreSQL is ready!"
fi

# Wait for Redis to be ready
if [ -n "$REDIS_HOST" ]; then
    echo "Waiting for Redis at $REDIS_HOST:$REDIS_PORT..."
    while ! nc -z "$REDIS_HOST" "$REDIS_PORT" 2>/dev/null; do
        sleep 1
    done
    echo "Redis is ready!"
fi

# Fix ownership of mounted volumes (running as root here)
echo "Fixing volume permissions..."
chown -R laravel:laravel /var/www/html/vendor 2>/dev/null || true
chown -R laravel:laravel /var/www/html/storage 2>/dev/null || true
chown -R laravel:laravel /var/www/html/bootstrap/cache 2>/dev/null || true

# Ensure log files are writable by laravel user (php-fpm master runs as root
# and may create log files owned by root, blocking the laravel worker processes)
chmod 775 /var/www/html/storage/logs 2>/dev/null || true
find /var/www/html/storage/logs -name "*.log" -exec chown laravel:laravel {} \; -exec chmod 664 {} \; 2>/dev/null || true

# Make framework cache dirs world-writable so both root (queue worker) and
# laravel (php-fpm) can create/overwrite compiled views and cache files
chmod 777 /var/www/html/storage/framework/views 2>/dev/null || true
chmod 777 /var/www/html/storage/framework/cache 2>/dev/null || true
chmod 777 /var/www/html/storage/framework/cache/data 2>/dev/null || true
chmod 777 /var/www/html/storage/framework/sessions 2>/dev/null || true

# Configure git safe directory (needed for composer)
git config --global --add safe.directory /var/www/html 2>/dev/null || true

# Criar estrutura de storage/framework se não existir
echo "Ensuring storage structure exists..."
su-exec laravel mkdir -p /var/www/html/storage/framework/cache/data
su-exec laravel mkdir -p /var/www/html/storage/framework/sessions
su-exec laravel mkdir -p /var/www/html/storage/framework/views
su-exec laravel mkdir -p /var/www/html/storage/logs

# Check if vendor autoload exists (composer install needed)
if [ ! -f "/var/www/html/vendor/autoload.php" ]; then
    echo "Running composer install (this may take a while on first run)..."
    su-exec laravel composer install --no-interaction --prefer-dist --optimize-autoloader
else
    echo "Vendor directory OK"
fi

# Generate app key only if not already present in .env file
# IMPORTANT: Check the .env file directly, NOT the shell environment variable,
# because APP_KEY is not passed as a container env var in docker-compose.yml.
# Previously this checked $APP_KEY (always empty), causing key:generate --force
# to run on EVERY container restart, invalidating all encrypted data in the DB.
if ! grep -q "^APP_KEY=base64:" /var/www/html/.env 2>/dev/null; then
    echo "Generating application key..."
    su-exec laravel php artisan key:generate --force
else
    echo "APP_KEY already set in .env, skipping generation"
fi

# Clear caches for development
echo "Clearing caches..."
su-exec laravel php artisan config:clear 2>/dev/null || true
su-exec laravel php artisan cache:clear 2>/dev/null || true
su-exec laravel php artisan view:clear 2>/dev/null || true
su-exec laravel php artisan route:clear 2>/dev/null || true

# Run migrations (development only)
if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "Running database migrations..."
    su-exec laravel php artisan migrate --force
fi

# Run seeders if requested (fresh install)
if [ "$RUN_SEEDERS" = "true" ]; then
    echo "Running database seeders..."
    su-exec laravel php artisan db:seed --force
fi

# Create storage link if not exists
if [ ! -L "/var/www/html/public/storage" ]; then
    echo "Creating storage symlink..."
    su-exec laravel php artisan storage:link 2>/dev/null || true
fi

echo "=== Container Ready ==="

# Execute the main command
# Note: php-fpm needs to run as root to spawn workers as 'laravel' user (configured in www.conf)
# Other commands should run as laravel user
if [ "$1" = "php-fpm" ]; then
    exec "$@"
else
    exec su-exec laravel "$@"
fi
