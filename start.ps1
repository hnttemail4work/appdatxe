# gozviet — khởi động dev (chạy: .\start.ps1)
$ErrorActionPreference = "Stop"
$server = Join-Path $PSScriptRoot "server"

if (-not (Test-Path $server)) {
    Write-Host "Khong tim thay thu muc server/" -ForegroundColor Red
    exit 1
}

Set-Location $server

Write-Host ""
Write-Host "=== gozviet ===" -ForegroundColor Cyan
Write-Host ""

# Kiem tra PHP
try {
    $phpVer = php -v 2>&1 | Select-Object -First 1
    Write-Host "PHP: $phpVer" -ForegroundColor Green
} catch {
    Write-Host "Chua cai PHP hoac chua them vao PATH." -ForegroundColor Red
    exit 1
}

# Kiem tra MySQL
Write-Host "Kiem tra MySQL..." -ForegroundColor Yellow
$dbOk = $false
try {
    php artisan db:show 2>&1 | Out-Null
    if ($LASTEXITCODE -eq 0) { $dbOk = $true }
} catch { }

if (-not $dbOk) {
    Write-Host ""
    Write-Host "Khong ket noi duoc MySQL!" -ForegroundColor Red
    Write-Host "  -> Bat MySQL (XAMPP/Laragon/WAMP) roi chay lai script." -ForegroundColor Yellow
    Write-Host "  -> Kiem tra DB trong server\.env (hien tai: appdatxe)" -ForegroundColor Yellow
    exit 1
}
Write-Host "MySQL: OK" -ForegroundColor Green

# Migrate nhe (bo qua loi neu da chay)
Write-Host "Dong bo database..." -ForegroundColor Yellow
php artisan migrate --force 2>&1 | Out-Null

# Storage link (lan dau)
if (-not (Test-Path "public\storage")) {
    php artisan storage:link 2>&1 | Out-Null
}

Write-Host ""
Write-Host "Mo trinh duyet: http://127.0.0.1:8000" -ForegroundColor Cyan
Write-Host "Dung server: Ctrl+C" -ForegroundColor DarkGray
Write-Host ""

# Scheduler chay nen (dong bo chuyen moi phut) — cua so rieng
$schedCmd = "Set-Location '$server'; php artisan schedule:work"
Start-Process powershell -ArgumentList "-NoExit", "-Command", $schedCmd -WindowStyle Minimized

php artisan serve --host=127.0.0.1 --port=8000
