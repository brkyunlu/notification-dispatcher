#!/bin/bash
set -e

echo "========================================"
echo "  Notification Dispatcher - Auto Setup"
echo "========================================"

cd /var/www

# Check if already initialized
if [ -f ".initialized" ]; then
    echo "[SKIP] Already initialized. Remove .initialized to re-run setup."
    exit 0
fi

echo ""
echo "[1/9] Checking .env file..."
if [ ! -f ".env" ]; then
    echo "  -> Creating .env from .env.example"
    cp .env.example .env
else
    echo "  -> .env already exists"
fi

echo ""
echo "[2/9] Waiting for MySQL to be ready..."
MAX_TRIES=30
TRIES=0
until php -r "new PDO('mysql:host=mysql;dbname=notification_dispatcher', 'dispatcher_user', 'secret');" 2>/dev/null; do
    TRIES=$((TRIES + 1))
    if [ $TRIES -ge $MAX_TRIES ]; then
        echo "  -> ERROR: MySQL not ready after $MAX_TRIES attempts"
        exit 1
    fi
    echo "  -> Waiting... ($TRIES/$MAX_TRIES)"
    sleep 2
done
echo "  -> MySQL is ready!"

echo ""
echo "[3/9] Installing Composer dependencies..."
composer install --no-interaction --prefer-dist --optimize-autoloader

echo ""
echo "[4/9] Generating application key..."
if grep -q "^APP_KEY=$" .env || grep -q "^APP_KEY=base64:$" .env 2>/dev/null; then
    php artisan key:generate --force
    echo "  -> Application key generated"
else
    echo "  -> Application key already set"
fi

echo ""
echo "[5/9] Running database migrations and seeding..."
php artisan migrate --force
php artisan db:seed --force
echo "  -> Migrations and seeds completed"

echo ""
echo "[6/9] Generating API key for Dashboard..."
# Capture the API key from the output
API_OUTPUT=$(php artisan api:generate-key "Dashboard" --permissions=read,write 2>&1)
echo "$API_OUTPUT"

# Extract the API key (line starting with "API Key: ")
API_KEY=$(echo "$API_OUTPUT" | grep "API Key:" | sed 's/API Key: //')

if [ -z "$API_KEY" ]; then
    echo "  -> WARNING: Could not extract API key"
else
    echo "  -> API Key extracted: ${API_KEY:0:20}..."
fi

echo ""
echo "[7/9] Getting webhook.site URL..."
WEBHOOK_RESPONSE=$(curl -s -X POST https://webhook.site/token)
WEBHOOK_UUID=$(echo "$WEBHOOK_RESPONSE" | jq -r '.uuid')

if [ -z "$WEBHOOK_UUID" ] || [ "$WEBHOOK_UUID" = "null" ]; then
    echo "  -> WARNING: Could not get webhook URL, using default"
    WEBHOOK_URL="https://httpbin.org/post"
else
    WEBHOOK_URL="https://webhook.site/${WEBHOOK_UUID}"
    echo "  -> Webhook URL: $WEBHOOK_URL"
    echo "  -> View requests: https://webhook.site/#!/view/${WEBHOOK_UUID}"
fi

echo ""
echo "[8/9] Updating .env with generated values..."

# Update VITE_API_KEY
if [ -n "$API_KEY" ]; then
    sed -i "s|^VITE_API_KEY=.*|VITE_API_KEY=${API_KEY}|" .env
    echo "  -> VITE_API_KEY updated"
fi

# Update NOTIFICATION_WEBHOOK_URL
sed -i "s|^NOTIFICATION_WEBHOOK_URL=.*|NOTIFICATION_WEBHOOK_URL=${WEBHOOK_URL}|" .env
echo "  -> NOTIFICATION_WEBHOOK_URL updated"

# Sync VITE_REVERB_* from REVERB_* (for frontend build)
REVERB_APP_KEY=$(grep "^REVERB_APP_KEY=" .env | cut -d '=' -f2)
REVERB_PORT=$(grep "^REVERB_PORT=" .env | cut -d '=' -f2)
REVERB_SCHEME=$(grep "^REVERB_SCHEME=" .env | cut -d '=' -f2)

if [ -n "$REVERB_APP_KEY" ]; then
    sed -i "s|^VITE_REVERB_APP_KEY=.*|VITE_REVERB_APP_KEY=${REVERB_APP_KEY}|" .env
    echo "  -> VITE_REVERB_APP_KEY synced from REVERB_APP_KEY"
fi

if [ -n "$REVERB_PORT" ]; then
    sed -i "s|^VITE_REVERB_PORT=.*|VITE_REVERB_PORT=${REVERB_PORT}|" .env
    echo "  -> VITE_REVERB_PORT synced from REVERB_PORT"
fi

if [ -n "$REVERB_SCHEME" ]; then
    sed -i "s|^VITE_REVERB_SCHEME=.*|VITE_REVERB_SCHEME=${REVERB_SCHEME}|" .env
    echo "  -> VITE_REVERB_SCHEME synced from REVERB_SCHEME"
fi

echo ""
echo "[9/9] Building frontend assets..."
npm install --silent
npm run build
echo "  -> Frontend build completed"

# Create marker file
touch .initialized

echo ""
echo "========================================"
echo "  Setup Complete!"
echo "========================================"
echo ""
echo "Services:"
echo "  - API:       http://localhost:8000/api/v1"
echo "  - Dashboard: http://localhost:8000/dashboard"
echo "  - RabbitMQ:  http://localhost:15672"
echo "  - Jaeger:    http://localhost:16686"
echo ""
if [ -n "$API_KEY" ]; then
    echo "API Key (save this!):"
    echo "  $API_KEY"
    echo ""
fi
if [ "$WEBHOOK_URL" != "https://httpbin.org/post" ]; then
    echo "Webhook URL:"
    echo "  $WEBHOOK_URL"
    echo "  View: https://webhook.site/#!/view/${WEBHOOK_UUID}"
    echo ""
fi
echo "========================================"
