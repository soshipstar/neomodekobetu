#!/bin/bash
set -e

# Ensure storage directories exist
mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache
mkdir -p storage/logs bootstrap/cache storage/fonts
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# Ensure storage symlink exists (survives container recreation)
php artisan storage:link --force 2>/dev/null || true

# Install Japanese fonts for DomPDF (IPA Gothic)
# This registers the font in DomPDF's font cache with proper metrics
php artisan dompdf:install-fonts 2>/dev/null || echo "Font installation skipped"

# Execute the main command
exec "$@"
