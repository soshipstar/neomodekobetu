#!/bin/bash
# ==============================================================================
# Backend container entrypoint (production)
# ------------------------------------------------------------------------------
# 起動ごとに:
#  - storage ディレクトリ整備 & symlink
#  - マイグレーション（APP_RUN_MIGRATIONS=false で無効化可能）
#  - Laravel 本番キャッシュ生成（config / route / view / event)
# ==============================================================================
set -e

cd /var/www/html

# Storage ディレクトリ
mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache
mkdir -p storage/logs bootstrap/cache storage/fonts
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# storage:link（コンテナ再作成で消えるので毎回）
php artisan storage:link --force 2>/dev/null || true

# IPA Gothic (DomPDF 用) — イメージに入っているが、ボリュームで上書きされた場合に復旧
if [ ! -f storage/fonts/ipag.ttf ]; then
    SRC="/usr/share/fonts/opentype/ipafont-gothic/ipag.ttf"
    if [ -f "$SRC" ]; then
        cp "$SRC" storage/fonts/ipag.ttf
        echo "[entrypoint] IPA Gothic font copied to storage/fonts/"
    fi
fi

# package:discover（.env が volume mount された後に実行する必要がある）
php artisan package:discover --ansi 2>/dev/null || true

# 既存キャッシュをクリア（設定変更が確実に反映されるように）
php artisan config:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true

# マイグレーション（デフォルト: 実行する）
if [ "${APP_RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "[entrypoint] Running migrations..."
    php artisan migrate --force || echo "[entrypoint] migration failed (continuing)"
fi

# 本番キャッシュを再生成
echo "[entrypoint] Optimizing (config/route/view cache)..."
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true
php artisan event:cache 2>/dev/null || true

# 実行
exec "$@"
