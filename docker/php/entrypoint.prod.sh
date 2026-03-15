#!/bin/bash
set -e

# Ensure storage directories exist
mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache
mkdir -p storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# Ensure storage symlink exists (survives container recreation)
php artisan storage:link --force 2>/dev/null || true

# Execute the main command
exec "$@"
