#!/bin/bash
cd /home/ponto/htdocs/ponto.approsamistica.com

echo "=== ULTIMOS LOGS NGINX ==="
tail -20 /home/ponto/logs/nginx/access.log

echo ""
echo "=== ULTIMOS ERROS PHP ==="
tail -30 /home/ponto/logs/php/error.log 2>/dev/null || echo "(sem log PHP)"

echo ""
echo "=== TESTAR LOGIN VIA CURL ==="
curl -s -X POST http://127.0.0.1:8080/api/v1/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"matheus83wq@gmail.com","password":"senha123","device_name":"test"}'
echo ""

echo "=== USUARIOS NA BASE ==="
sudo -u ponto php artisan tinker --execute="echo json_encode(App\Models\User::select('id','name','email','role','active')->get());"
