#!/bin/bash
git config --global --add safe.directory /home/ponto/htdocs/ponto.approsamistica.com
cd /home/ponto/htdocs/ponto.approsamistica.com
git pull origin master
sudo -u ponto php artisan route:cache
sudo -u ponto php artisan view:cache
echo "=== DONE ==="
