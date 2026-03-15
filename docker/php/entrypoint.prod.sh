#!/bin/bash
set -e

# Ensure storage symlink exists (survives container recreation)
php artisan storage:link --force 2>/dev/null || true

# Execute the main command
exec "$@"
