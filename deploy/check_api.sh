#!/bin/bash
# Testar se a API responde
curl -s -o /dev/null -w "Status: %{http_code}\n" https://ponto.approsamistica.com/api/v1/login -X POST \
  -H "Content-Type: application/json" \
  -d '{"email":"test@test.com","password":"test","device_name":"test"}'

# Verificar se o PHP-FPM está a correr
systemctl is-active php8.2-fpm 2>/dev/null || systemctl is-active php8.1-fpm 2>/dev/null || echo "fpm status unknown"

# Verificar se o Nginx está a correr
systemctl is-active nginx
