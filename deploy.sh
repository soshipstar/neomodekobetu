#!/bin/bash
set -e

echo "=== KIDURI 2026 Production Deploy ==="

APP_DIR="/root/kiduri2026"

# 1. Install Docker if not present
if ! command -v docker &> /dev/null; then
    echo ">>> Installing Docker..."
    curl -fsSL https://get.docker.com | sh
    systemctl enable docker
    systemctl start docker
    echo ">>> Docker installed."
fi

# 2. Clone or pull repo
if [ -d "$APP_DIR" ]; then
    echo ">>> Pulling latest code..."
    cd "$APP_DIR"
    git pull origin master
else
    echo ">>> Cloning repository..."
    git clone https://github.com/soshipstar/neomodekobetu.git "$APP_DIR"
    cd "$APP_DIR"
fi

# 3. Create production .env if not exists
if [ ! -f "$APP_DIR/backend/.env.production" ]; then
    echo ">>> Creating production .env..."
    cp "$APP_DIR/backend/.env" "$APP_DIR/backend/.env.production"
    echo "!!! IMPORTANT: Edit backend/.env.production with production values !!!"
fi

# 4. SSL Certificate (initial setup - HTTP only first)
echo ">>> Starting initial deployment (HTTP only for SSL cert)..."

# Temporarily use HTTP-only nginx config for certbot
cat > /tmp/nginx-initial.conf << 'NGINX'
server {
    listen 80;
    server_name kiduri.xyz;

    location /.well-known/acme-challenge/ {
        root /var/www/certbot;
    }

    location / {
        return 200 'Kiduri deploy in progress...';
        add_header Content-Type text/plain;
    }
}
NGINX

# 5. Build and start
echo ">>> Building containers..."
docker compose -f docker-compose.prod.yml build

echo ">>> Starting services..."
docker compose -f docker-compose.prod.yml up -d

# 6. Wait for services
echo ">>> Waiting for services to start..."
sleep 10

# 7. Run migrations
echo ">>> Running database migrations..."
docker compose -f docker-compose.prod.yml exec backend php artisan migrate --force

# 8. Laravel optimizations
echo ">>> Optimizing Laravel..."
docker compose -f docker-compose.prod.yml exec backend php artisan config:cache
docker compose -f docker-compose.prod.yml exec backend php artisan route:cache
docker compose -f docker-compose.prod.yml exec backend php artisan view:cache
docker compose -f docker-compose.prod.yml exec backend php artisan storage:link

echo ""
echo "=== Deploy complete! ==="
echo "Next steps:"
echo "1. Point DNS A record for kiduri.xyz to this server's IP"
echo "2. Run: docker compose -f docker-compose.prod.yml run --rm certbot certonly --webroot -w /var/www/certbot -d kiduri.xyz"
echo "3. Restart nginx: docker compose -f docker-compose.prod.yml restart nginx"
echo "4. Set up SSL auto-renewal: crontab -e"
echo "   0 0 1 * * docker compose -f /root/kiduri2026/docker-compose.prod.yml run --rm certbot renew && docker compose -f /root/kiduri2026/docker-compose.prod.yml restart nginx"
