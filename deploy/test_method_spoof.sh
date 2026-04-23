#!/bin/bash
# Testar se method spoofing funciona directamente no PHP-FPM (porta 8080)
# Primeiro verificar se o Laravel tem o middleware correto
cd /home/ponto/htdocs/ponto.approsamistica.com
sudo -u ponto php artisan route:list --method=PUT --path=painel 2>/dev/null

# Verificar kernel HTTP middleware
grep -n "MethodNotAllowed\|method\|_method\|spoofing" app/Http/Kernel.php 2>/dev/null | head -10

# Ver se o middleware está no kernel
cat app/Http/Kernel.php 2>/dev/null | grep -A5 "web.*middleware"
