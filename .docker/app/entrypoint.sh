#!/bin/sh
set -e

DB_PATH="/var/www/database/database.sqlite"

# Create SQLite database file if it does not exist
if [ ! -f "$DB_PATH" ]; then
    touch "$DB_PATH"
    echo "Created database.sqlite"
fi

# Ensure storage and cache directories have correct permissions
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache /var/www/database
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

cd /var/www

# Install dependencies (no dev in production)
if [ "$APP_ENV" = "production" ]; then
    composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
else
    composer install --no-interaction --prefer-dist
fi

# Run migrations
php artisan migrate --force

# Cache config and routes in production
if [ "$APP_ENV" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

exec "$@"
