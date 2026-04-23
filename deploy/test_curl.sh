#!/bin/bash
# Testar POST com _method=PUT directamente no backend PHP
cd /home/ponto/htdocs/ponto.approsamistica.com

# Primeiro fazer login para obter cookie
COOKIE_JAR=$(mktemp)
TOKEN=$(curl -s -c "$COOKIE_JAR" http://127.0.0.1:8080/login | grep -o 'name="_token" value="[^"]*"' | cut -d'"' -f4)
echo "CSRF Token: $TOKEN"

# Fazer login
LOGIN_RESP=$(curl -s -b "$COOKIE_JAR" -c "$COOKIE_JAR" -X POST http://127.0.0.1:8080/login \
  -d "_token=$TOKEN&email=admin@pontodigital.com&password=Admin@2026!")
echo "Login status: $?"

# Testar PUT via POST+_method
RESP=$(curl -s -o /dev/null -w "%{http_code}" -b "$COOKIE_JAR" -X POST http://127.0.0.1:8080/painel/colaboradores/2 \
  -d "_method=PUT&_token=$TOKEN&name=Teste&email=teste@test.com&cpf=000.000.000-00&cargo=Dev&company_id=1&contract_type=clt&weekly_hours=44&admission_date=2024-01-01")
echo "PUT via POST+_method response: $RESP"

rm "$COOKIE_JAR"
