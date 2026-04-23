#!/bin/bash
cd /home/ponto/htdocs/ponto.approsamistica.com
sudo -u ponto php artisan route:list --path=painel/colaboradores
