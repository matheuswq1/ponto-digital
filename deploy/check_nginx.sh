#!/bin/bash
# Verificar config Nginx para o site
cat /etc/nginx/sites-enabled/ponto.approsamistica.com.conf 2>/dev/null || ls /etc/nginx/sites-enabled/
