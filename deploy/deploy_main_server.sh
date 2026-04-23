#!/bin/bash
set -e

DEST="/home/ponto/htdocs/ponto.approsamistica.com"
SITE_USER="ponto"
MYSQL_ROOT_PASS="MgNwiEDT1kGsqqJN"
DB_NAME="ponto_web"
DB_USER="ponto_user"
DB_PASS="PontoDB@2026!"

echo "=== STEP 1: Clonar repositório ==="
# Remover pasta public vazia e clonar o projeto
rm -rf "$DEST/public"
cd "$DEST"
git clone https://github.com/matheuswq1/ponto-digital.git .
echo "Clone OK"

echo "=== STEP 2: Instalar dependências ==="
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -5
echo "Composer OK"

echo "=== STEP 3: Configurar .env ==="
cp .env.example .env

sed -i "s|APP_ENV=local|APP_ENV=production|" .env
sed -i "s|APP_DEBUG=true|APP_DEBUG=false|" .env
sed -i "s|APP_URL=.*|APP_URL=https://ponto.approsamistica.com|" .env
sed -i "s|DB_CONNECTION=.*|DB_CONNECTION=mysql|" .env
sed -i "s|# DB_HOST=.*|DB_HOST=localhost|" .env
sed -i "s|# DB_PORT=.*|DB_PORT=3306|" .env
sed -i "s|# DB_DATABASE=.*|DB_DATABASE=$DB_NAME|" .env
sed -i "s|# DB_USERNAME=.*|DB_USERNAME=$DB_USER|" .env
sed -i "s|# DB_PASSWORD=.*|DB_PASSWORD=$DB_PASS|" .env
sed -i "s|SESSION_DRIVER=database|SESSION_DRIVER=file|" .env
sed -i "s|CACHE_STORE=database|CACHE_STORE=file|" .env
echo "APP_KEY=" >> .env
echo ".env OK"

echo "=== STEP 4: Gerar APP_KEY ==="
php artisan key:generate --force
echo "Key OK"

echo "=== STEP 5: Criar DB e user ==="
mysql -h127.0.0.1 -uroot -p"$MYSQL_ROOT_PASS" -e "
  CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED WITH mysql_native_password BY '$DB_PASS';
  GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';
  FLUSH PRIVILEGES;
" 2>/dev/null || true
echo "DB OK"

echo "=== STEP 6: Migrations + Seeds ==="
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan db:seed --class=CompanySeeder --force
php artisan db:seed --class=UserSeeder --force
echo "Migrations OK"

echo "=== STEP 7: Cache e storage ==="
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link --force 2>/dev/null || true
echo "Cache OK"

echo "=== STEP 8: Permissões ==="
chown -R $SITE_USER:$SITE_USER "$DEST"
find "$DEST" -type f -exec chmod 644 {} \;
find "$DEST" -type d -exec chmod 755 {} \;
chmod -R 775 "$DEST/storage"
chmod -R 775 "$DEST/bootstrap/cache"
echo "Permissões OK"

echo ""
echo "=== DEPLOY CONCLUIDO ==="
echo "Site: https://ponto.approsamistica.com"
