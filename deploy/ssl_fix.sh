#!/bin/bash
set -e

DOMAIN="ponto.approsamistica.com"
APP_DIR="/var/www/ponto-digital"
CERT_PATH="/etc/letsencrypt/live/$DOMAIN"

echo "=== Criando vhost com SSL ==="
cat > /etc/nginx/sites-available/ponto-digital << NGINX
server {
    listen 80;
    server_name $DOMAIN;
    return 301 https://\$host\$request_uri;
}

server {
    listen 443 ssl;
    server_name $DOMAIN;

    ssl_certificate $CERT_PATH/fullchain.pem;
    ssl_certificate_key $CERT_PATH/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    root $APP_DIR/public;
    index index.php index.html;

    client_max_body_size 20M;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-XSS-Protection "1; mode=block";
    add_header X-Content-Type-Options "nosniff";
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

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

echo "=== Ativando site e testando Nginx ==="
ln -sf /etc/nginx/sites-available/ponto-digital /etc/nginx/sites-enabled/ponto-digital

nginx -t
nginx -s reload
echo "Nginx reloaded OK"

echo "=== Verificando site ==="
curl -s -o /dev/null -w "%{http_code}" https://$DOMAIN/ 2>&1 || true
echo ""
echo "=== HTTPS ativo: https://$DOMAIN ==="
