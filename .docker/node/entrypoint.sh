#!/bin/sh
set -e

cd /app

# Install dependencies if node_modules is empty
if [ ! -d "node_modules" ] || [ -z "$(ls -A node_modules 2>/dev/null)" ]; then
    echo "Installing npm dependencies..."
    npm install
fi

exec "$@"
