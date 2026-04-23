#!/bin/bash
# Ver últimas linhas do log PHP e nginx
echo "=== PHP ERROR LOG ==="
tail -30 /home/ponto/logs/php/error.log 2>/dev/null
echo "=== NGINX ACCESS LOG (last 20) ==="
tail -20 /home/ponto/logs/nginx/access.log 2>/dev/null
