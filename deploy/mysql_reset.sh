#!/bin/bash
set -e

echo "=== Investigando MySQL ==="
MYSQL_PID=$(pgrep mysqld | head -1)
echo "PID: $MYSQL_PID"
cat /proc/$MYSQL_PID/cmdline | tr '\0' ' '
echo ""

echo "=== Parando MySQL ==="
systemctl stop mysql
sleep 3

echo "=== Iniciando com skip-grant-tables via socket ==="
mkdir -p /etc/systemd/system/mysql.service.d
cat > /etc/systemd/system/mysql.service.d/override.conf <<'OVERRIDE'
[Service]
ExecStart=
ExecStart=/usr/sbin/mysqld --skip-grant-tables --socket=/var/run/mysqld/mysqld.sock
OVERRIDE

systemctl daemon-reload
systemctl start mysql
sleep 6

echo "=== Status após restart ==="
systemctl status mysql --no-pager | head -5

echo "=== Testando conexão sem senha via socket ==="
mysql -uroot -S /var/run/mysqld/mysqld.sock -e "SELECT user, host, plugin FROM mysql.user WHERE user='root';" 2>&1

echo "=== Resetando senha ==="
mysql -uroot -S /var/run/mysqld/mysqld.sock <<'MYSQL'
UPDATE mysql.user SET authentication_string='' WHERE User='root';
UPDATE mysql.user SET plugin='mysql_native_password' WHERE User='root';
FLUSH PRIVILEGES;
MYSQL
echo "Update OK"

echo "=== Removendo override ==="
rm /etc/systemd/system/mysql.service.d/override.conf
systemctl daemon-reload
systemctl restart mysql
sleep 5

echo "=== Definindo senha root ==="
mysql -uroot -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'PontoDB_Root@2026!'; FLUSH PRIVILEGES;"
echo "Password set OK"

echo "=== Testando nova senha ==="
mysql -uroot -p'PontoDB_Root@2026!' -e "SELECT 'ROOT_OK';" 2>&1
