#!/bin/bash
set -e

MYSQL_ROOT_PASS="PontoDB_Root@2026!"
DB_NAME="ponto_web"
DB_USER="ponto_user"
DB_PASS="PontoDB@2026!"
APP_DIR="/var/www/ponto-digital"
DOMAIN="ponto.approsamistica.com"

echo "=== STEP 1: Criar DB e user ==="
mysql -uroot -p"$MYSQL_ROOT_PASS" -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -uroot -p"$MYSQL_ROOT_PASS" -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED WITH mysql_native_password BY '$DB_PASS';"
mysql -uroot -p"$MYSQL_ROOT_PASS" -e "GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost'; FLUSH PRIVILEGES;"
echo "DB created OK"

echo "=== STEP 2: Configurar .env Laravel ==="
cd "$APP_DIR"
cp .env.example .env

sed -i "s|APP_ENV=local|APP_ENV=production|" .env
sed -i "s|APP_DEBUG=true|APP_DEBUG=false|" .env
sed -i "s|APP_URL=.*|APP_URL=https://$DOMAIN|" .env
sed -i "s|DB_DATABASE=.*|DB_DATABASE=$DB_NAME|" .env
sed -i "s|DB_USERNAME=.*|DB_USERNAME=$DB_USER|" .env
sed -i "s|DB_PASSWORD=.*|DB_PASSWORD=$DB_PASS|" .env
sed -i "s|SESSION_DRIVER=database|SESSION_DRIVER=file|" .env
echo ".env configured OK"

echo "=== STEP 3: Composer install ==="
cd "$APP_DIR"
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction 2>&1
echo "Composer OK"

echo "=== STEP 4: Artisan setup ==="
php artisan key:generate --force
php artisan config:clear
php artisan config:cache
echo "Key + config OK"

echo "=== STEP 5: Migrate + Seed ==="
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan db:seed --class=CompanySeeder --force
php artisan db:seed --class=UserSeeder --force
echo "Migrations + Seeds OK"

echo "=== STEP 6: Cache routes e views ==="
php artisan route:cache
php artisan view:cache
php artisan storage:link --force 2>/dev/null || true
echo "Cache OK"

echo "=== STEP 7: Permissoes ==="
chown -R www-data:www-data "$APP_DIR"
find "$APP_DIR" -type f -exec chmod 644 {} \;
find "$APP_DIR" -type d -exec chmod 755 {} \;
chmod -R 775 "$APP_DIR/storage"
chmod -R 775 "$APP_DIR/bootstrap/cache"
echo "Permissions OK"

echo "=== STEP 8: Nginx vhost ==="
cat > /etc/nginx/sites-available/ponto-digital << NGINX
server {
    listen 80;
    server_name $DOMAIN;

    root $APP_DIR/public;
    index index.php index.html;

    client_max_body_size 20M;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-XSS-Protection "1; mode=block";
    add_header X-Content-Type-Options "nosniff";

    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php\$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX

ln -sf /etc/nginx/sites-available/ponto-digital /etc/nginx/sites-enabled/ponto-digital
rm -f /etc/nginx/sites-enabled/default 2>/dev/null || true
nginx -t
nginx -s reload
echo "Nginx OK"

echo ""
echo "=== DEPLOY CONCLUIDO ==="
echo "URL: http://$DOMAIN (SSL pendente)"
echo "DB: $DB_NAME / $DB_USER"
