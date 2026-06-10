#!/bin/bash
# ==============================================================================
# KIDURI 2026 デプロイスクリプト
# ------------------------------------------------------------------------------
# 本番サーバー(3.8GB)ではビルドを絶対にしない。
# ローカルでビルド → イメージを save | ssh | load でサーバーに転送 → 再起動。
#
# 使い方:
#   ./deploy.sh frontend    # フロントエンドのみ
#   ./deploy.sh backend     # バックエンドのみ（マイグレーションは自動）
#   ./deploy.sh all         # 両方
#   ./deploy.sh restart     # コンテナ再起動のみ（.env変更など）
#
# 注意:
#  - サーバー上で `git pull && docker compose up --build` をやると OOM で落ちます
#  - 必ずこのスクリプト経由で deploy してください
# ==============================================================================

set -e

SERVER="kiduri"
REMOTE_DIR="/root/kiduri2026"
PROJECT_DIR="$(cd "$(dirname "$0")" && pwd)"

# BuildKit を有効化（並列ビルド & 依存キャッシュ）
export DOCKER_BUILDKIT=1
export COMPOSE_DOCKER_CLI_BUILD=1

log()  { echo -e "\033[1;34m[deploy]\033[0m $1"; }
ok()   { echo -e "\033[1;32m[done]\033[0m  $1"; }
warn() { echo -e "\033[1;33m[warn]\033[0m  $1"; }

# ------------------------------------------------------------------------------
# 未コミット変更ガード
# ------------------------------------------------------------------------------
# Docker イメージは「作業ツリー」からビルドされるため、未コミットの変更がそのまま
# 本番に乗る。過去に未コミットWIPが意図せず本番稼働する事故が起きたため、
# ビルドに入るパス (backend/ frontend/ docker/) に未コミット変更があれば中断する。
# どうしても作業ツリーのままデプロイしたい場合のみ --allow-dirty を付ける。
check_clean_tree() {
    cd "$PROJECT_DIR"
    local dirty
    dirty=$(git status --porcelain -- backend frontend docker 2>/dev/null)
    if [ -n "$dirty" ]; then
        if [ "$ALLOW_DIRTY" = "1" ]; then
            warn "未コミットの変更がありますが --allow-dirty 指定のため続行します:"
            echo "$dirty"
        else
            warn "ビルド対象 (backend/ frontend/ docker/) に未コミットの変更があります:"
            echo "$dirty"
            warn "未コミットの変更は『コミットされていないのに本番で稼働する』事故の原因になります。"
            warn "コミット(または stash)してから再実行してください。"
            warn "意図的に作業ツリーのままデプロイする場合のみ: ./deploy.sh <target> --allow-dirty"
            exit 1
        fi
    fi
    log "Deploying commit: $(git rev-parse --short HEAD) ($(git log -1 --format=%s | head -c 60))"
}

# git push & サーバー同期
push_code() {
    log "Syncing code (git push → server reset)..."
    cd "$PROJECT_DIR"
    git push origin master
    # サーバー側は pull ではなく reset --hard（分岐防止）
    ssh $SERVER "cd $REMOTE_DIR && git fetch origin && git reset --hard origin/master"
    ok "Code synced"
}

# --- frontend: ローカルビルド → 転送 → 再起動 ---
deploy_frontend() {
    log "Building frontend image locally..."
    cd "$PROJECT_DIR"
    docker build \
        --platform linux/amd64 \
        -t kiduri2026-frontend:latest \
        -f frontend/Dockerfile.prod \
        frontend/
    ok "Frontend image built locally"

    log "Transferring image to server (gzip stream)..."
    docker save kiduri2026-frontend:latest | gzip -1 | ssh $SERVER "gunzip | docker load"
    ok "Image transferred"

    log "Restarting frontend container on server..."
    ssh $SERVER "cd $REMOTE_DIR && docker compose -f docker-compose.prod.yml up -d --no-deps --no-build frontend"
    ok "Frontend deployed"
}

# --- backend: ローカルビルド → 転送 → 再起動 ---
deploy_backend() {
    log "Building backend image locally..."
    cd "$PROJECT_DIR"
    docker build \
        --platform linux/amd64 \
        -t kiduri2026-backend:latest \
        -f docker/php/Dockerfile.prod \
        .
    ok "Backend image built locally"

    log "Transferring image to server (gzip stream)..."
    docker save kiduri2026-backend:latest | gzip -1 | ssh $SERVER "gunzip | docker load"
    ok "Image transferred"

    log "Restarting backend / queue / reverb / scheduler containers..."
    ssh $SERVER "cd $REMOTE_DIR && docker compose -f docker-compose.prod.yml up -d --no-deps --no-build backend queue reverb scheduler"
    # backend を再作成すると Docker が新しい内部 IP を割り当てる。
    # nginx の upstream は起動時に DNS 解決した IP を握り続けるため、
    # backend が再作成された後は必ず nginx も再起動して upstream を再解決させる。
    # (2026-05-29 502 Bad Gateway 事故の再発防止)
    log "Restarting nginx (refresh upstream DNS cache)..."
    ssh $SERVER "cd $REMOTE_DIR && docker compose -f docker-compose.prod.yml restart nginx"
    ok "Backend deployed (migrations run automatically via entrypoint)"
}

# --- restart only ---
deploy_restart() {
    push_code
    log "Restarting containers (no rebuild)..."
    # nginx も含めて再起動し upstream DNS を再解決させる (502 事故の再発防止)
    ssh $SERVER "cd $REMOTE_DIR && docker compose -f docker-compose.prod.yml restart backend queue reverb scheduler nginx"
    ok "Restarted"
}

# マイグレーションのみ手動実行したい時用
run_migrate() {
    log "Running migrations manually..."
    ssh $SERVER "cd $REMOTE_DIR && docker compose -f docker-compose.prod.yml exec -T backend php artisan migrate --force"
    ok "Migrations done"
}

# メイン
TARGET="${1:-all}"

# --allow-dirty フラグ (位置は任意)
ALLOW_DIRTY=0
for arg in "$@"; do
    if [ "$arg" = "--allow-dirty" ]; then
        ALLOW_DIRTY=1
    fi
done

# ローカルビルドの前提チェック
if ! docker info >/dev/null 2>&1; then
    warn "Docker が起動していません。Docker Desktop を起動してください。"
    exit 1
fi

case "$TARGET" in
    frontend)
        check_clean_tree
        push_code
        deploy_frontend
        ;;
    backend)
        check_clean_tree
        push_code
        deploy_backend
        ;;
    all)
        check_clean_tree
        push_code
        deploy_frontend
        deploy_backend
        ;;
    restart)
        deploy_restart
        ;;
    migrate)
        run_migrate
        ;;
    *)
        echo "Usage: $0 {frontend|backend|all|restart|migrate}"
        exit 1
        ;;
esac

echo ""
ok "Deploy complete!"
