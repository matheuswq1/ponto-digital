#!/bin/bash
set -e

SRC="/var/www/ponto-digital"
DEST="/home/ponto/htdocs/ponto.approsamistica.com"
SITE_USER="ponto"
MYSQL_ROOT_PASS="DRXOFeKzT5YWHBmp"

echo "=== Movendo ficheiros do projeto para pasta do CloudPanel ==="
# Apagar apenas a pasta public vazia criada pelo CloudPanel
rm -rf "$DEST/public"

# Copiar todo o conteúdo do projeto para o destino
cp -a "$SRC/." "$DEST/"

echo "Ficheiros copiados OK"
ls "$DEST" | head -10

echo "=== Ajustando .env para novo caminho ==="
# O .env já está configurado, apenas garantir que está correto
grep "APP_URL\|DB_" "$DEST/.env"

echo "=== Criando DB no MySQL do CloudPanel (se não existir) ==="
mysql -h127.0.0.1 -uroot -p"$MYSQL_ROOT_PASS" -e "
  CREATE DATABASE IF NOT EXISTS ponto_web CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER IF NOT EXISTS 'ponto_user'@'localhost' IDENTIFIED WITH mysql_native_password BY 'PontoDB@2026!';
  GRANT ALL PRIVILEGES ON ponto_web.* TO 'ponto_user'@'localhost';
  FLUSH PRIVILEGES;
" 2>&1
echo "DB OK"

echo "=== Ajustando permissões ==="
chown -R $SITE_USER:$SITE_USER "$DEST"
find "$DEST" -type f -exec chmod 644 {} \;
find "$DEST" -type d -exec chmod 755 {} \;
chmod -R 775 "$DEST/storage"
chmod -R 775 "$DEST/bootstrap/cache"
chmod +x "$DEST/artisan"
echo "Permissões OK"

echo "=== Limpando cache Laravel para novo ambiente ==="
cd "$DEST"
sudo -u $SITE_USER php artisan config:clear 2>&1 || true
sudo -u $SITE_USER php artisan config:cache 2>&1 || true
sudo -u $SITE_USER php artisan route:cache 2>&1 || true
sudo -u $SITE_USER php artisan view:cache 2>&1 || true
echo "Cache OK"

echo "=== Verificando se DB tem tabelas ==="
mysql -h127.0.0.1 -uroot -p"$MYSQL_ROOT_PASS" ponto_web -e "SHOW TABLES;" 2>&1 | head -5
TABLE_COUNT=$(mysql -h127.0.0.1 -uroot -p"$MYSQL_ROOT_PASS" ponto_web -e "SHOW TABLES;" 2>/dev/null | wc -l)
echo "Tabelas existentes: $TABLE_COUNT"

if [ "$TABLE_COUNT" -lt 5 ]; then
  echo "=== Executando migrations e seeds ==="
  cd "$DEST"
  sudo -u $SITE_USER php artisan migrate --force 2>&1
  sudo -u $SITE_USER php artisan db:seed --class=RolesAndPermissionsSeeder --force 2>&1
  sudo -u $SITE_USER php artisan db:seed --class=CompanySeeder --force 2>&1
  sudo -u $SITE_USER php artisan db:seed --class=UserSeeder --force 2>&1
  echo "Migrations OK"
else
  echo "=== DB já tem dados, pulando migrations ==="
fi

echo ""
echo "=== DONE ==="
echo "Projeto em: $DEST"
