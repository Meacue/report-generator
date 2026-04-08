#!/bin/sh
set -e

DB_PATH="/var/www/database/database.sqlite"

# Wait for database file to be created by app container
echo "Waiting for database..."
while [ ! -f "$DB_PATH" ]; do
    sleep 1
done

echo "Database found, starting queue worker..."

exec "$@"
