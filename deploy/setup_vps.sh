#!/bin/bash
set -e

echo "=== STEP 1: Remover override MySQL se existir ==="
rm -f /etc/systemd/system/mysql.service.d/override.conf
systemctl daemon-reload
systemctl restart mysql 2>/dev/null || true
sleep 4
echo "MySQL restarted"

echo "=== STEP 2: Reset senha MySQL ==="
# Parar MySQL
systemctl stop mysql
sleep 2

# Override para skip-grant-tables
mkdir -p /etc/systemd/system/mysql.service.d
cat > /etc/systemd/system/mysql.service.d/override.conf <<'OVERRIDE'
[Service]
ExecStart=
ExecStart=/usr/sbin/mysqld --skip-grant-tables
OVERRIDE

systemctl daemon-reload
systemctl start mysql
sleep 6

# Resetar senha root
mysql -uroot <<'MYSQL'
UPDATE mysql.user SET authentication_string='' WHERE User='root' AND Host='localhost';
UPDATE mysql.user SET plugin='mysql_native_password' WHERE User='root' AND Host='localhost';
FLUSH PRIVILEGES;
MYSQL
echo "User reset OK"

# Remover override
rm /etc/systemd/system/mysql.service.d/override.conf
systemctl daemon-reload
systemctl restart mysql
sleep 5

# Definir nova senha
mysql -uroot -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'PontoDB_Root@2026!'; FLUSH PRIVILEGES;"
echo "Password set OK"

echo "=== STEP 3: Criar DB e user ==="
mysql -uroot -p'PontoDB_Root@2026!' <<'MYSQL'
CREATE DATABASE IF NOT EXISTS ponto_web CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'ponto_user'@'localhost' IDENTIFIED BY 'PontoDB@2026!';
GRANT ALL PRIVILEGES ON ponto_web.* TO 'ponto_user'@'localhost';
FLUSH PRIVILEGES;
MYSQL
echo "DB created OK"

echo "=== STEP 4: Configurar .env Laravel ==="
cd /var/www/ponto-digital
cp .env.example .env

sed -i "s|APP_ENV=local|APP_ENV=production|g" .env
sed -i "s|APP_DEBUG=true|APP_DEBUG=false|g" .env
sed -i 's|APP_URL=.*|APP_URL=https://ponto.approsamistica.com|g' .env
sed -i "s|DB_DATABASE=.*|DB_DATABASE=ponto_web|g" .env
sed -i "s|DB_USERNAME=.*|DB_USERNAME=ponto_user|g" .env
sed -i "s|DB_PASSWORD=.*|DB_PASSWORD=PontoDB@2026!|g" .env

echo ".env configured OK"

echo "=== STEP 5: Composer install ==="
cd /var/www/ponto-digital
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction
echo "Composer OK"

echo "=== STEP 6: Artisan setup ==="
php artisan key:generate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link --force 2>/dev/null || true
echo "Artisan OK"

echo "=== STEP 7: Migrate + Seed ==="
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan db:seed --class=CompanySeeder --force
php artisan db:seed --class=UserSeeder --force
echo "Migrations OK"

echo "=== STEP 8: Permissões ==="
chown -R www-data:www-data /var/www/ponto-digital
find /var/www/ponto-digital -type f -exec chmod 644 {} \;
find /var/www/ponto-digital -type d -exec chmod 755 {} \;
chmod -R 775 /var/www/ponto-digital/storage
chmod -R 775 /var/www/ponto-digital/bootstrap/cache
echo "Permissions OK"

echo "=== STEP 9: Nginx vhost ==="
cat > /etc/nginx/sites-available/ponto-digital <<'NGINX'
server {
    listen 80;
    server_name ponto.approsamistica.com;

    root /var/www/ponto-digital/public;
    index index.php index.html;

    client_max_body_size 20M;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-XSS-Protection "1; mode=block";
    add_header X-Content-Type-Options "nosniff";

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX

ln -sf /etc/nginx/sites-available/ponto-digital /etc/nginx/sites-enabled/ponto-digital
nginx -t && nginx -s reload
echo "Nginx OK"

echo "=== DONE: Setup completo! ==="
echo "Acesso: http://ponto.approsamistica.com"
