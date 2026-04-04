#!/bin/bash
# ==============================================================================
# KIDURI 2026 デプロイスクリプト
# ローカルでDockerイメージをビルド → サーバーに転送 → コンテナ更新
#
# 使い方:
#   ./deploy.sh frontend    # フロントエンドのみビルド＆デプロイ
#   ./deploy.sh backend     # バックエンドのみビルド＆デプロイ
#   ./deploy.sh all         # 両方ビルド＆デプロイ
#   ./deploy.sh restart     # ビルドなし再起動（設定変更のみ、コード変更には使えない）
# ==============================================================================

set -e

SERVER="kiduri"
REMOTE_DIR="/root/kiduri2026"
PROJECT_DIR="$(cd "$(dirname "$0")" && pwd)"

log()  { echo -e "\033[1;34m[deploy]\033[0m $1"; }
ok()   { echo -e "\033[1;32m[done]\033[0m $1"; }

# git push & pull
push_code() {
    log "Pushing code..."
    cd "$PROJECT_DIR"
    git push origin master
    ssh $SERVER "cd $REMOTE_DIR && git pull origin master"
    ok "Code synced"
}

# フロントエンドのビルド＆デプロイ
deploy_frontend() {
    log "Building frontend locally..."
    cd "$PROJECT_DIR"
    docker build --platform linux/amd64 -t kiduri2026-frontend:latest -f frontend/Dockerfile.prod frontend/
    ok "Frontend built"

    log "Transferring image to server..."
    docker save kiduri2026-frontend:latest | gzip | ssh $SERVER "gunzip | docker load"
    ok "Image transferred"

    log "Restarting frontend..."
    ssh $SERVER "cd $REMOTE_DIR && docker compose -f docker-compose.prod.yml up -d --no-deps frontend"
    ok "Frontend deployed"
}

# バックエンドのビルド＆デプロイ
deploy_backend() {
    log "Building backend locally..."
    cd "$PROJECT_DIR"
    docker build --platform linux/amd64 -t kiduri2026-backend:latest -f docker/php/Dockerfile.prod .
    ok "Backend built"

    log "Transferring image to server..."
    docker save kiduri2026-backend:latest | gzip | ssh $SERVER "gunzip | docker load"
    ok "Image transferred"

    log "Restarting backend containers..."
    ssh $SERVER "cd $REMOTE_DIR && docker compose -f docker-compose.prod.yml up -d --no-deps backend queue reverb"
    ok "Backend deployed"
}

# 再起動のみ（.env変更やコンテナ設定変更のみ。コード変更にはbackendを使うこと）
deploy_restart() {
    push_code
    log "Restarting containers (no rebuild)..."
    ssh $SERVER "cd $REMOTE_DIR && docker compose -f docker-compose.prod.yml restart backend queue reverb"
    ok "Restarted"
}

# マイグレーション実行
run_migrate() {
    log "Running migrations..."
    ssh $SERVER "cd $REMOTE_DIR && docker compose -f docker-compose.prod.yml exec backend php artisan migrate --force"
    ok "Migrations done"
}

# メイン
TARGET="${1:-all}"

case "$TARGET" in
    frontend)
        push_code
        deploy_frontend
        ;;
    backend)
        push_code
        deploy_backend
        run_migrate
        ;;
    all)
        push_code
        deploy_frontend
        deploy_backend
        run_migrate
        ;;
    restart)
        deploy_restart
        ;;
    *)
        echo "Usage: $0 {frontend|backend|all|restart}"
        exit 1
        ;;
esac

echo ""
ok "Deploy complete!"
