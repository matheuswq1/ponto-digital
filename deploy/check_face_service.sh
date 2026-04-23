#!/bin/bash
echo "=== LOGS LARAVEL (ultimos erros) ==="
cd /home/ponto/htdocs/ponto.approsamistica.com
tail -50 storage/logs/laravel.log | grep -A5 "ERROR\|Exception\|face\|enroll" | head -60

echo ""
echo "=== CONTAINER DOCKER DO SERVICO FACIAL ==="
docker ps -a | grep -i face || echo "(nenhum container face encontrado)"

echo ""
echo "=== TODOS OS CONTAINERS ==="
docker ps -a

echo ""
echo "=== TESTAR ROTA FACE/ENROLL NO LARAVEL ==="
grep -r "face" routes/api.php | head -20

echo ""
echo "=== FACE SERVICE CONFIG (.env) ==="
grep -i "face\|python\|ml\|ai" .env || echo "(sem config face no .env)"
