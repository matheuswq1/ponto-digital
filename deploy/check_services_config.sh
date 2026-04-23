#!/bin/bash
cd /home/ponto/htdocs/ponto.approsamistica.com
grep -A5 "face" config/services.php || echo "(sem entrada face em config/services.php)"
