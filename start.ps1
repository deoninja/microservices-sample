# Microservices Demo - Startup Script
# Starts all services locally (without Docker)

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  MicroStore - Starting All Services" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

$rootDir = $PSScriptRoot

# ─── 0. Kill any processes already using our ports ───────
Write-Host "[0/3] Freeing ports 3001, 3002, 3003, 8000..." -ForegroundColor Yellow
foreach ($port in @(3001, 3002, 3003, 8000)) {
    $pids = netstat -ano | Select-String ":$port\s" | ForEach-Object {
        ($_ -split '\s+')[-1]
    } | Sort-Object -Unique
    foreach ($pid in $pids) {
        if ($pid -match '^\d+$' -and $pid -ne '0') {
            try { Stop-Process -Id $pid -Force -ErrorAction SilentlyContinue } catch {}
        }
    }
}
Start-Sleep -Milliseconds 800
Write-Host "  Ports cleared." -ForegroundColor Green
Write-Host ""

# ─── 1. Start Node.js Microservices ──────────────────────
Write-Host "[1/3] Starting Node.js Microservices..." -ForegroundColor Yellow

$services = @(
    @{ Name="User Service"; Port=3001; Dir="$rootDir\services\user-service" },
    @{ Name="Product Service"; Port=3002; Dir="$rootDir\services\product-service" },
    @{ Name="Order Service"; Port=3003; Dir="$rootDir\services\order-service" }
)

$serviceProcesses = @()
foreach ($svc in $services) {
    Write-Host "  Starting $($svc.Name) on port $($svc.Port)..." -ForegroundColor Gray
    $process = Start-Process -NoNewWindow -PassThru -FilePath "node" -ArgumentList "src/index.js" -WorkingDirectory $svc.Dir
    $serviceProcesses += $process
    Start-Sleep -Milliseconds 500
}

Write-Host "  All microservices started." -ForegroundColor Green
Write-Host ""

# ─── 2. Start Laravel API Gateway ────────────────────────
Write-Host "[2/3] Starting Laravel API Gateway..." -ForegroundColor Yellow

$laravelDir = "$rootDir\api-gateway"
$gatewayProcess = Start-Process -NoNewWindow -PassThru -FilePath "php" -ArgumentList "artisan serve --host=localhost --port=8000" -WorkingDirectory $laravelDir

Write-Host "  API Gateway starting on http://localhost:8000" -ForegroundColor Green
Write-Host ""

# ─── 3. Start React Frontend ─────────────────────────────
Write-Host "[3/3] Starting React Frontend..." -ForegroundColor Yellow

$frontendDir = "$rootDir\frontend"
$frontendProcess = Start-Process -NoNewWindow -PassThru -FilePath "npm.cmd" -ArgumentList "run dev" -WorkingDirectory $frontendDir

Write-Host "  Frontend starting on http://localhost:3000" -ForegroundColor Green
Write-Host ""

# ─── Summary ──────────────────────────────────────────────
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  All Services Starting!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "  Frontend:        http://localhost:3000" -ForegroundColor White
Write-Host "  API Gateway:     http://localhost:8000" -ForegroundColor White
Write-Host "  User Service:    http://localhost:3001" -ForegroundColor White
Write-Host "  Product Service: http://localhost:3002" -ForegroundColor White
Write-Host "  Order Service:   http://localhost:3003" -ForegroundColor White
Write-Host ""
Write-Host "  Demo Login: admin / password" -ForegroundColor Yellow
Write-Host ""
Write-Host "  Press Ctrl+C to stop all services" -ForegroundColor Red
Write-Host ""

# Wait for processes
try {
    $gatewayProcess.WaitForExit()
} finally {
    Write-Host "Stopping all services..." -ForegroundColor Yellow
    foreach ($p in $serviceProcesses) {
        if (!$p.HasExited) { $p.Kill() }
    }
    if (!$frontendProcess.HasExited) { $frontendProcess.Kill() }
}
