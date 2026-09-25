<#
.SYNOPSIS
    Start NIS-RCIS locally: Laravel API on :8000 and Next.js on :3000,
    each in its own window, then open the browser.
#>

. (Join-Path $PSScriptRoot 'common.ps1')

if (-not (Test-Path (Join-Path $Frontend '.env.local')) -or -not (Test-Path (Join-Path $Backend '.env'))) {
    Fail 'The application is not set up yet. Run setup.bat first.'
}

# A half-finished npm install (network drop) leaves 'next' missing.
if (-not (Test-Path (Join-Path $Frontend 'node_modules\.bin\next.cmd')) -and -not (Test-Path (Join-Path $Frontend 'node_modules/.bin/next'))) {
    Write-Step 'Website packages are missing or incomplete - installing them first'
    Invoke-NpmInstall $Frontend
}

function Test-Port([int]$port) {
    $client = New-Object System.Net.Sockets.TcpClient
    try { $client.Connect('127.0.0.1', $port); return $true } catch { return $false } finally { $client.Close() }
}

Write-Step 'Starting the Laravel API on http://127.0.0.1:8000'
if (Test-Port 8000) {
    Write-Warn 'Port 8000 is already in use - assuming the API is already running.'
} else {
    Start-Process powershell -ArgumentList @('-NoExit', '-Command',
        "`$Host.UI.RawUI.WindowTitle = 'NIS-RCIS API (close to stop)'; Set-Location '$Backend'; php artisan serve --host=127.0.0.1 --port=8000")
}

Write-Step 'Starting the Next.js frontend on http://localhost:3000'
if (Test-Port 3000) {
    Write-Warn 'Port 3000 is already in use - assuming the frontend is already running.'
} else {
    Start-Process powershell -ArgumentList @('-NoExit', '-Command',
        "`$Host.UI.RawUI.WindowTitle = 'NIS-RCIS Frontend (close to stop)'; Set-Location '$Frontend'; npm run dev")
}

Write-Step 'Waiting for both servers to be ready'
$deadline = (Get-Date).AddMinutes(3)
while ((Get-Date) -lt $deadline) {
    if ((Test-Port 8000) -and (Test-Port 3000)) { break }
    Start-Sleep -Seconds 2
}
if (-not ((Test-Port 8000) -and (Test-Port 3000))) { Fail 'The servers did not start. Check the two server windows for errors.' }

# Warm up the home page so the first click is fast (Next.js compiles on demand)
try { Invoke-WebRequest -Uri 'http://localhost:3000/' -UseBasicParsing -TimeoutSec 120 | Out-Null } catch { }

Write-Ok 'Running. Opening http://localhost:3000'
try { Start-Process 'http://localhost:3000' } catch { Write-Warn 'Open http://localhost:3000 in your browser.' }

Write-Host ''
Write-Host 'Demo password for every account: NisDemo-2026!' -ForegroundColor Green
Write-Host 'E-mails (verification links etc.) are written to backend\storage\logs\laravel.log'
Write-Host 'To stop: close the two server windows.'
