#!/bin/bash
# Verificar se Varnish está activo
systemctl status varnish 2>/dev/null | grep -E "Active:|loaded"
# Verificar VCL para métodos bloqueados
find /etc/varnish -name "*.vcl" -exec grep -l "PUT\|PATCH\|method" {} \; 2>/dev/null
# Testar curl directo ao backend
curl -s -o /dev/null -w "%{http_code}" -X PUT "http://127.0.0.1:8080/painel/colaboradores/1" -d "_method=PUT" 2>/dev/null
echo ""
