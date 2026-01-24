#!/bin/sh
set -e

echo "=== Horizon Container Starting ==="

# Wait for PostgreSQL
if [ -n "$DB_HOST" ]; then
    echo "Waiting for PostgreSQL..."
    while ! nc -z "$DB_HOST" "$DB_PORT" 2>/dev/null; do
        sleep 1
    done
    echo "PostgreSQL is ready!"
fi

# Wait for Redis
if [ -n "$REDIS_HOST" ]; then
    echo "Waiting for Redis..."
    while ! nc -z "$REDIS_HOST" "$REDIS_PORT" 2>/dev/null; do
        sleep 1
    done
    echo "Redis is ready!"
fi

# Fix ownership of mounted volumes (running as root here)
echo "Fixing volume permissions..."
chown -R laravel:laravel /var/www/html/vendor 2>/dev/null || true
chown -R laravel:laravel /var/www/html/storage 2>/dev/null || true

# Configure git safe directory
git config --global --add safe.directory /var/www/html 2>/dev/null || true

# Wait for app container to be ready (migrations complete)
echo "Waiting for app container to initialize..."
sleep 15

# Clear Horizon state
echo "Clearing Horizon state..."
su-exec laravel php artisan horizon:terminate 2>/dev/null || true
sleep 2

echo "=== Starting Horizon ==="

# Start Horizon as laravel user
exec su-exec laravel php artisan horizon
