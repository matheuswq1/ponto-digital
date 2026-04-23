#!/bin/bash
set -e

DEST="/home/ponto/htdocs/ponto.approsamistica.com"
SITE_USER="ponto"
MYSQL_ROOT_PASS="MgNwiEDT1kGsqqJN"
DB_NAME="ponto_web"
DB_USER="ponto_user"
DB_PASS="PontoDB@2026!"

echo "=== Configurar .env ==="
cat > "$DEST/.env" << 'ENVEOF'
APP_NAME="Sistema Ponto Web"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ponto.approsamistica.com
APP_KEY=

APP_LOCALE=pt_BR
APP_FALLBACK_LOCALE=pt_BR
APP_FAKER_LOCALE=pt_BR
APP_TIMEZONE=America/Sao_Paulo

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=ponto_web
DB_USERNAME=ponto_user
DB_PASSWORD=PontoDB@2026!

SESSION_DRIVER=file
SESSION_LIFETIME=120

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=file

VITE_APP_NAME="${APP_NAME}"
ENVEOF
echo ".env OK"

echo "=== Criar DB ==="
mysql -h127.0.0.1 -uroot -p"$MYSQL_ROOT_PASS" -e "
  CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED WITH mysql_native_password BY '$DB_PASS';
  GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';
  FLUSH PRIVILEGES;
" 2>/dev/null || true
echo "DB OK"

echo "=== Permissões ==="
chown -R $SITE_USER:$SITE_USER "$DEST"
find "$DEST" -type f -exec chmod 644 {} \;
find "$DEST" -type d -exec chmod 755 {} \;
chmod -R 775 "$DEST/storage"
chmod -R 775 "$DEST/bootstrap/cache"
echo "Permissões OK"

echo "=== APP_KEY ==="
cd "$DEST"
sudo -u $SITE_USER php artisan key:generate --force 2>&1

echo "=== Migrations + Seeds ==="
sudo -u $SITE_USER php artisan migrate --force 2>&1
sudo -u $SITE_USER php artisan db:seed --class=RolesAndPermissionsSeeder --force 2>&1
sudo -u $SITE_USER php artisan db:seed --class=CompanySeeder --force 2>&1
sudo -u $SITE_USER php artisan db:seed --class=UserSeeder --force 2>&1
echo "Seeds OK"

echo "=== Cache ==="
sudo -u $SITE_USER php artisan config:cache 2>&1
sudo -u $SITE_USER php artisan route:cache 2>&1
sudo -u $SITE_USER php artisan view:cache 2>&1
sudo -u $SITE_USER php artisan storage:link --force 2>&1 || true
echo "Cache OK"

echo ""
echo "=== DONE ==="
