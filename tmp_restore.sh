#!/bin/bash
cd /home/ponto/htdocs/ponto.approsamistica.com
source .env
echo "=== Restaurando dados: adicionando +2h de volta ==="
mysql -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" -e "UPDATE time_records SET datetime = DATE_ADD(datetime, INTERVAL 2 HOUR) WHERE datetime >= '2026-04-01';" 2>/dev/null

echo "=== Registros após restauração ==="
mysql -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" -e "SELECT id, type, datetime FROM time_records ORDER BY id;" 2>/dev/null
