#!/bin/bash
# Verificar privilégios e setar senha

echo "=== Users root ==="
mysql -uroot -e "SELECT user, host, plugin, Super_priv FROM mysql.user WHERE user='root';" 2>&1

echo "=== Grants root@localhost ==="
mysql -uroot -e "SHOW GRANTS FOR 'root'@'localhost';" 2>&1

echo "=== Tentando SET PASSWORD ==="
mysql -uroot -e "SET PASSWORD FOR 'root'@'localhost' = 'PontoDB_Root@2026!';" 2>&1

echo "=== Tentando UPDATE ==="
mysql -uroot <<'MYSQL'
UPDATE mysql.user SET authentication_string = PASSWORD('PontoDB_Root@2026!') WHERE User='root' AND Host='localhost';
UPDATE mysql.user SET plugin='mysql_native_password' WHERE User='root';
FLUSH PRIVILEGES;
MYSQL
echo "Update done"

mysql -uroot -p'PontoDB_Root@2026!' -e "SELECT 'ROOT_WITH_PASS_OK';" 2>&1
