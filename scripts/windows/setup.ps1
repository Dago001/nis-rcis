<#
.SYNOPSIS
    One-time local setup of NIS-RCIS for testing on Windows (no Docker).

.DESCRIPTION
    Checks prerequisites, creates the PostgreSQL database, configures the
    Laravel backend and Next.js frontend, creates the OAuth2 clients and
    loads demo accounts and sample applications.

    Run it from the repository root with setup.bat, or:
        powershell -ExecutionPolicy Bypass -File scripts\windows\setup.ps1

.PARAMETER Reset
    Wipe the local database and start again with fresh demo data.
#>
param(
    [switch]$Reset
)

. (Join-Path $PSScriptRoot 'common.ps1')

Write-Host ''
Write-Host 'NIS-RCIS - local test setup' -ForegroundColor Green
Write-Host '---------------------------'

# ---------------------------------------------------------------- 1. Tools
Write-Step 'Checking required software'

$missing = @()
if (-not (Get-Command php -ErrorAction SilentlyContinue)) { $missing += 'PHP 8.3 or newer  -> https://php.new  (or Laravel Herd: https://herd.laravel.com/windows)' }
if (-not (Get-Command composer -ErrorAction SilentlyContinue)) { $missing += 'Composer          -> installed by php.new / Herd, or https://getcomposer.org/download/' }
if (-not (Get-Command node -ErrorAction SilentlyContinue)) { $missing += 'Node.js 22 LTS    -> https://nodejs.org/' }
$psql = Find-Psql
if (-not $psql) { $missing += 'PostgreSQL 16     -> https://www.postgresql.org/download/windows/' }

if ($missing.Count -gt 0) {
    Write-Host ''
    Write-Host 'Please install the following, then run setup again:' -ForegroundColor Yellow
    $missing | ForEach-Object { Write-Host "  - $_" }
    Write-Host ''
    Write-Host 'Tip: close and reopen this window after installing, so the new programs are found.'
    exit 1
}

$phpRun = Invoke-Capture 'php' @('-r', 'echo ''NISVER='' . PHP_VERSION;')
$phpVersion = ($phpRun.Lines | Where-Object { $_ -match '^NISVER=' } | Select-Object -First 1) -replace '^NISVER=', ''
if (-not $phpVersion) { Fail "Could not run PHP: $($phpRun.Text)" }
if ([version]($phpVersion -replace '[^0-9.].*$', '') -lt [version]'8.3') { Fail "PHP $phpVersion found; PHP 8.3 or newer is required." }
Write-Ok "PHP $phpVersion"

$nodeVersion = ((Invoke-Capture 'node' @('-v')).Lines | Where-Object { $_ -match '^v\d' } | Select-Object -First 1).TrimStart('v')
if ([version]$nodeVersion -lt [version]'20.9') { Fail "Node.js $nodeVersion found; Node.js 20.9 or newer (22 LTS recommended) is required." }
Write-Ok "Node.js $nodeVersion"
Write-Ok "Composer ($((Get-Command composer).Source))"
Write-Ok "PostgreSQL client ($psql)"

# --------------------------------------------------------- 2. PHP extensions
Write-Step 'Checking PHP extensions'

$required = @('pdo_pgsql', 'pgsql', 'fileinfo', 'mbstring', 'openssl', 'curl', 'zip')
function Get-MissingExtensions {
    $loaded = (Invoke-Capture 'php' @('-m')).Lines | ForEach-Object { $_.Trim().ToLower() }
    return @($required | Where-Object { $loaded -notcontains $_ })
}

$missingExt = Get-MissingExtensions
if ($missingExt.Count -gt 0) {
    Write-Warn ("Missing PHP extensions: " + ($missingExt -join ', '))

    $phpDir = Split-Path -Parent (Get-Command php).Source
    $ini = ((Invoke-Capture 'php' @('-r', 'echo ''NISINI='' . php_ini_loaded_file();')).Lines | Where-Object { $_ -match '^NISINI=' } | Select-Object -First 1) -replace '^NISINI=', ''
    if (-not $ini) {
        $template = Join-Path $phpDir 'php.ini-development'
        if (-not (Test-Path $template)) { Fail "No php.ini found. Enable these extensions in your php.ini: $($missingExt -join ', ')" }
        $ini = Join-Path $phpDir 'php.ini'
        Copy-Item $template $ini
        Write-Ok "Created $ini from php.ini-development"
    }

    $answer = Read-Host "    Enable them in $ini now? A backup is made first. [Y/n]"
    if ($answer -and $answer.ToLower() -ne 'y') { Fail "Enable these extensions in $ini and run setup again: $($missingExt -join ', ')" }

    Copy-Item $ini "$ini.bak-nis-rcis" -Force
    $content = [System.IO.File]::ReadAllText($ini)

    # Windows needs an absolute extension_dir when PHP is not in C:\php
    $extDir = Join-Path $phpDir 'ext'
    if ((Test-Path $extDir) -and ($content -notmatch '(?m)^\s*extension_dir\s*=')) {
        $content = $content.TrimEnd() + "`r`nextension_dir = `"$extDir`"`r`n"
    }

    foreach ($ext in $missingExt) {
        $pattern = "(?m)^\s*;\s*extension\s*=\s*(php_)?$ext(\.dll)?\s*$"
        if ($content -match $pattern) {
            $content = [regex]::Replace($content, $pattern, "extension=$ext")
        } else {
            $content = $content.TrimEnd() + "`r`nextension=$ext`r`n"
        }
    }
    Write-Utf8File $ini $content

    $missingExt = Get-MissingExtensions
    if ($missingExt.Count -gt 0) { Fail "Still missing after editing ${ini}: $($missingExt -join ', '). Check that the matching php_*.dll files exist in $extDir." }
}
Write-Ok ('All required extensions loaded (' + ($required -join ', ') + ')')

# --------------------------------------------------------------- 3. Database
Write-Step 'Preparing the PostgreSQL database'

$backendEnvPath = Join-Path $Backend '.env'
$existing = Read-EnvFile $backendEnvPath
$dbPassword = $existing['DB_PASSWORD']
if (-not $dbPassword -or $dbPassword -eq 'secret') { $dbPassword = New-Password }

$pgPassword = $env:NIS_PG_SUPERUSER_PASSWORD
if (-not $pgPassword) {
    $secure = Read-Host '    Password of the PostgreSQL "postgres" user (chosen when you installed PostgreSQL)' -AsSecureString
    $pgPassword = [Runtime.InteropServices.Marshal]::PtrToStringAuto([Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure))
}
$env:PGPASSWORD = $pgPassword

function Invoke-Psql([string]$sql, [string]$database = 'postgres') {
    $run = Invoke-Capture $psql @('-h', '127.0.0.1', '-U', 'postgres', '-d', $database, '-v', 'ON_ERROR_STOP=1', '-tAc', $sql)
    if ($run.Code -ne 0) { Fail "PostgreSQL command failed: $($run.Text)`n       Is PostgreSQL running, and is the postgres password correct?" }
    return (($run.Lines | Where-Object { $_ -notmatch '^(WARNING|NOTICE|Xdebug)' }) -join "`n").Trim()
}

Invoke-Psql 'SELECT 1' | Out-Null
Write-Ok 'Connected to PostgreSQL'

if ((Invoke-Psql "SELECT 1 FROM pg_roles WHERE rolname = 'nis_rcis'") -eq '1') {
    Invoke-Psql "ALTER ROLE nis_rcis WITH LOGIN CREATEDB PASSWORD '$dbPassword'" | Out-Null
} else {
    Invoke-Psql "CREATE ROLE nis_rcis WITH LOGIN CREATEDB PASSWORD '$dbPassword'" | Out-Null
}
foreach ($db in @('nis_rcis', 'nis_rcis_test')) {
    if ((Invoke-Psql "SELECT 1 FROM pg_database WHERE datname = '$db'") -ne '1') {
        Invoke-Psql "CREATE DATABASE $db OWNER nis_rcis" | Out-Null
        Write-Ok "Created database $db"
    } else {
        Write-Ok "Database $db already exists"
    }
}
Remove-Item Env:\PGPASSWORD

# ---------------------------------------------------------------- 4. Backend
Write-Step 'Installing the Laravel backend (this can take a few minutes the first time)'

Repair-EnvFile $backendEnvPath
Invoke-Native 'composer' @('install', '--no-interaction', '--no-progress') $Backend

$envContent = if (Test-Path $backendEnvPath) { [System.IO.File]::ReadAllText($backendEnvPath) } else { [System.IO.File]::ReadAllText((Join-Path $Backend '.env.example')) }
$envContent = Set-EnvValues $envContent @{
    'APP_ENV' = 'local'
    'APP_DEBUG' = 'true'
    'APP_URL' = 'http://127.0.0.1:8000'
    'FRONTEND_URL' = 'http://localhost:3000'
    'DB_CONNECTION' = 'pgsql'
    'DB_HOST' = '127.0.0.1'
    'DB_PORT' = '5432'
    'DB_DATABASE' = 'nis_rcis'
    'DB_USERNAME' = 'nis_rcis'
    'DB_PASSWORD' = $dbPassword
    # No Redis needed for local testing
    'SESSION_DRIVER' = 'file'
    'CACHE_STORE' = 'file'
    'QUEUE_CONNECTION' = 'sync'
    'LOG_STACK' = 'single'
    'MAIL_MAILER' = 'log'
    'DOCUMENTS_DISK' = 'local'
    'PAYMENTS_FAKE' = 'true'
    # Testing convenience: applicants can sign in without the e-mail link
    'SKIP_EMAIL_VERIFICATION' = 'true'
}
Write-Utf8File $backendEnvPath $envContent
Write-Ok 'backend\.env written (local settings)'

if (-not (Read-EnvFile $backendEnvPath)['APP_KEY']) { Invoke-Native 'php' @('artisan', 'key:generate', '--force') $Backend }
if (-not (Test-Path (Join-Path $Backend 'storage\oauth-private.key'))) { Invoke-Native 'php' @('artisan', 'passport:keys') $Backend }
Push-Location $Backend; Set-BackupSettings $backendEnvPath; Pop-Location
Invoke-Native 'php' @('artisan', 'config:clear') $Backend

if ($Reset) {
    Write-Warn 'Resetting the local database (-Reset)'
    Invoke-Native 'php' @('artisan', 'migrate:fresh', '--seed', '--force') $Backend
} else {
    Invoke-Native 'php' @('artisan', 'migrate', '--seed', '--force') $Backend
}
Invoke-Native 'php' @('artisan', 'nis:demo') $Backend

# ------------------------------------------------------------ 5. OAuth clients
Write-Step 'Configuring OAuth2 clients for the frontend'

$frontendEnvPath = Join-Path $Frontend '.env.local'
$frontendEnv = Read-EnvFile $frontendEnvPath
Push-Location $Backend
$clientExists = $false
if ($frontendEnv['OAUTH_STAFF_CLIENT_ID']) {
    $check = Invoke-Capture 'php' @('artisan', 'tinker', '--execute', "echo 'NISCLIENT=' . (App\Models\OAuthClient::whereKey('$($frontendEnv['OAUTH_STAFF_CLIENT_ID'])')->exists() ? 'yes' : 'no');")
    $clientExists = [bool]($check.Lines | Where-Object { $_ -eq 'NISCLIENT=yes' })
}
Pop-Location

if ($clientExists -and -not $Reset) {
    Write-Ok 'Existing OAuth2 clients kept (frontend\.env.local)'
} else {
    Push-Location $Backend
    $run = Invoke-Capture 'php' @('artisan', 'nis:oauth-clients', '--frontend=http://localhost:3000')
    $output = $run.Lines
    Pop-Location
    if ($run.Code -ne 0) { Fail "Could not create the OAuth2 clients: $($run.Text)" }

    $clients = @{}
    foreach ($line in $output) {
        if ($line -match '^(OAUTH_[A-Z_]+)=(.+)$') { $clients[$Matches[1]] = $Matches[2].Trim() }
    }
    if ($clients.Count -ne 4) { Fail 'Unexpected output from nis:oauth-clients.' }

    $lines = @(
        '# Generated by scripts\windows\setup.ps1 for local testing',
        'APP_URL=http://localhost:3000',
        'API_URL=http://127.0.0.1:8000',
        'API_PUBLIC_URL=http://127.0.0.1:8000',
        "SESSION_SECRET=$(New-Secret 48)",
        "OAUTH_STAFF_CLIENT_ID=$($clients['OAUTH_STAFF_CLIENT_ID'])",
        "OAUTH_STAFF_CLIENT_SECRET=$($clients['OAUTH_STAFF_CLIENT_SECRET'])",
        "OAUTH_APPLICANT_CLIENT_ID=$($clients['OAUTH_APPLICANT_CLIENT_ID'])",
        "OAUTH_APPLICANT_CLIENT_SECRET=$($clients['OAUTH_APPLICANT_CLIENT_SECRET'])"
    )
    Write-Utf8File $frontendEnvPath (($lines -join "`n") + "`n")
    Write-Ok 'OAuth2 clients created; frontend\.env.local written'
}

# --------------------------------------------------------------- 6. Frontend
Write-Step 'Installing the Next.js frontend (this can take a few minutes the first time)'
Invoke-NpmInstall $Frontend

# ------------------------------------------------------------------- Done
Write-Host ''
Write-Host 'Setup complete.' -ForegroundColor Green
Write-Host ''
Write-Host 'Start the application with:  start.bat'
Write-Host 'Then open:                   http://localhost:3000'
Write-Host ''
Write-Host "All demo accounts use the password:  NisDemo-2026!"
Write-Host '  Staff console (/staff):   demo.admin, demo.approver, demo.issuer, demo.inspector, demo.auditor'
Write-Host '  Applicant portal:         john.smith@example.com, kwame.mensah@example.com, amara.diallo@example.com,'
Write-Host '                            li.wei@example.com, elena.rossi@example.com'
Write-Host ''
Write-Host 'See TESTING.md for a step-by-step test plan.'
