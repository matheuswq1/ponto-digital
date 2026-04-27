<#
.SYNOPSIS
    Deploy rápido: push git + git pull no servidor + artisan cache + restart face service.

.DESCRIPTION
    Lê as credenciais de deploy/.env (nunca commitado).
    Crie deploy/.env a partir de deploy/.env.example antes de usar.

.EXAMPLE
    .\deploy\remote_deploy.ps1
#>

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

# ── Carregar variáveis do deploy/.env ────────────────────────────────────────
$EnvFile = "$PSScriptRoot\.env"
if (-not (Test-Path $EnvFile)) {
    Write-Error "Arquivo '$EnvFile' nao encontrado. Copie deploy/.env.example para deploy/.env e preencha os valores."
    exit 1
}

$cfg = @{}
Get-Content $EnvFile | Where-Object { $_ -match '^\s*[^#]\S+=.+' } | ForEach-Object {
    $parts = $_ -split '=', 2
    $cfg[$parts[0].Trim()] = $parts[1].Trim()
}

$VPS_HOST    = $cfg['VPS_HOST']
$VPS_PW      = $cfg['VPS_PW']
$VPS_HOSTKEY = $cfg['VPS_HOSTKEY']
$APP_DIR     = $cfg['VPS_APP_DIR']
$FACE_CTR    = $cfg['FACE_CONTAINER']

Write-Host "`n=== [1/3] Git push para origin ===" -ForegroundColor Cyan
Set-Location "$PSScriptRoot\.."
git push origin master
if ($LASTEXITCODE -ne 0) { Write-Error "git push falhou."; exit 1 }

Write-Host "`n=== [2/3] Deploy no servidor ($VPS_HOST) ===" -ForegroundColor Cyan

$remoteCmd = @"
set -e
cd $APP_DIR
git config --global --add safe.directory $APP_DIR
git pull origin master
chown -R ponto:ponto .
sudo -u ponto php artisan migrate --force --no-interaction
sudo -u ponto php artisan config:cache
sudo -u ponto php artisan route:cache
sudo -u ponto php artisan view:clear
echo '--- Laravel OK ---'
if docker ps -a --format '{{.Names}}' | grep -q '^${FACE_CTR}$'; then
  docker cp $APP_DIR/face_service/main.py ${FACE_CTR}:/app/main.py
  docker restart ${FACE_CTR}
  sleep 4
  curl -s http://127.0.0.1:8001/health
  echo ''
  echo '--- Face Service OK ---'
else
  echo '--- Container ${FACE_CTR} nao encontrado (skipping) ---'
fi
echo '=== DEPLOY CONCLUIDO ==='
"@

plink -batch -ssh $VPS_HOST -pw $VPS_PW -hostkey $VPS_HOSTKEY $remoteCmd
if ($LASTEXITCODE -ne 0) { Write-Error "Comando remoto falhou."; exit 1 }

Write-Host "`n=== [3/3] Verificacao final ===" -ForegroundColor Cyan
$check = plink -batch -ssh $VPS_HOST -pw $VPS_PW -hostkey $VPS_HOSTKEY `
    "cd $APP_DIR && sudo -u ponto git log -1 --oneline && curl -s http://127.0.0.1:8001/health"
Write-Host $check -ForegroundColor Green
Write-Host "`nDeploy finalizado com sucesso!" -ForegroundColor Green
