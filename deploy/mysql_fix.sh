#!/bin/bash
set -e

echo "=== Parando MySQL ==="
systemctl stop mysql
sleep 2

echo "=== Iniciando com skip-grant-tables ==="
mkdir -p /etc/systemd/system/mysql.service.d
cat > /etc/systemd/system/mysql.service.d/override.conf <<'OVERRIDE'
[Service]
ExecStart=
ExecStart=/usr/sbin/mysqld --skip-grant-tables --socket=/var/run/mysqld/mysqld.sock
OVERRIDE

systemctl daemon-reload
systemctl start mysql
sleep 6

echo "=== Listando todos users root ==="
mysql -uroot -S /var/run/mysqld/mysqld.sock -e "SELECT user, host, plugin, authentication_string FROM mysql.user WHERE user='root';" 2>&1

echo "=== Criando root@localhost e setando senha ==="
mysql -uroot -S /var/run/mysqld/mysqld.sock <<'MYSQL'
UPDATE mysql.user SET authentication_string='', plugin='mysql_native_password' WHERE User='root';
INSERT IGNORE INTO mysql.user (Host, User, plugin, authentication_string, ssl_cipher, x509_issuer, x509_subject, Select_priv, Insert_priv, Update_priv, Delete_priv, Create_priv, Drop_priv, Reload_priv, Shutdown_priv, Process_priv, File_priv, Grant_priv, References_priv, Index_priv, Alter_priv, Show_db_priv, Super_priv, Create_tmp_table_priv, Lock_tables_priv, Execute_priv, Repl_slave_priv, Repl_client_priv, Create_view_priv, Show_view_priv, Create_routine_priv, Alter_routine_priv, Create_user_priv, Event_priv, Trigger_priv, Create_tablespace_priv)
VALUES ('localhost', 'root', 'mysql_native_password', '', '', '', '', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y');
FLUSH PRIVILEGES;
MYSQL
echo "Users updated"

echo "=== Removendo override ==="
rm /etc/systemd/system/mysql.service.d/override.conf
systemctl daemon-reload
systemctl restart mysql
sleep 5

echo "=== Tentando conectar sem senha ==="
mysql -uroot -e "SELECT 'NO_PASSWORD_OK';" 2>&1

echo "=== Definindo senha ==="
mysql -uroot -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY 'PontoDB_Root@2026!'; ALTER USER 'root'@'127.0.0.1' IDENTIFIED WITH mysql_native_password BY 'PontoDB_Root@2026!'; FLUSH PRIVILEGES;" 2>&1

echo "=== Testando com senha ==="
mysql -uroot -p'PontoDB_Root@2026!' -e "SELECT 'ROOT_OK';" 2>&1

echo "=== Criando DB e user aplicacao ==="
mysql -uroot -p'PontoDB_Root@2026!' <<'MYSQL'
CREATE DATABASE IF NOT EXISTS ponto_web CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'ponto_user'@'localhost' IDENTIFIED WITH mysql_native_password BY 'PontoDB@2026!';
GRANT ALL PRIVILEGES ON ponto_web.* TO 'ponto_user'@'localhost';
FLUSH PRIVILEGES;
SHOW DATABASES;
MYSQL
echo "DB OK"
