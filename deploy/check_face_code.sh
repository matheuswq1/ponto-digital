#!/bin/bash
cd /home/ponto/htdocs/ponto.approsamistica.com

echo "=== FaceService.php ==="
cat app/Services/FaceService.php

echo ""
echo "=== FaceController.php ==="
cat app/Http/Controllers/Api/FaceController.php

echo ""
echo "=== .env FACE vars ==="
grep -i "face\|python\|ml\|recognition" .env || echo "(nenhuma)"
