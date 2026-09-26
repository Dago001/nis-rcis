# Shared helpers for the NIS-RCIS Windows scripts (Windows PowerShell 5.1 compatible).

$ErrorActionPreference = 'Stop'
$Root = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$Backend = Join-Path $Root 'backend'
$Frontend = Join-Path $Root 'frontend'

function Write-Step([string]$text) { Write-Host ''; Write-Host "==> $text" -ForegroundColor Cyan }
function Write-Ok([string]$text) { Write-Host "    [OK] $text" -ForegroundColor Green }
function Write-Warn([string]$text) { Write-Host "    [!]  $text" -ForegroundColor Yellow }
function Fail([string]$text) {
    Write-Host ''
    Write-Host "ERROR: $text" -ForegroundColor Red
    exit 1
}

# Write text as UTF-8 WITHOUT a byte-order mark (PowerShell 5.1 adds one by
# default, which breaks .env files).
function Write-Utf8File([string]$path, [string]$content) {
    [System.IO.File]::WriteAllText($path, $content, (New-Object System.Text.UTF8Encoding $false))
}

# Run a native command and stop on failure.
function Invoke-Native([string]$exe, [string[]]$arguments, [string]$workDir) {
    Push-Location $workDir
    try {
        & $exe @arguments
        if ($LASTEXITCODE -ne 0) { Fail "'$exe $($arguments -join ' ')' failed (exit code $LASTEXITCODE)." }
    } finally {
        Pop-Location
    }
}

# Run a native command and capture stdout+stderr as text. Windows PowerShell
# 5.1 turns captured stderr into terminating errors under
# ErrorActionPreference=Stop (e.g. Xdebug warnings), so relax it locally.
function Invoke-Capture([string]$exe, [string[]]$arguments) {
    $previous = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        $out = & $exe @arguments 2>&1 | ForEach-Object { "$_" }
        return @{ Code = $LASTEXITCODE; Lines = @($out); Text = (($out | Out-String).Trim()) }
    } finally {
        $ErrorActionPreference = $previous
    }
}

# npm install with retries: home connections often drop during the large
# first download (ECONNRESET), and antivirus can briefly lock files (EPERM).
function Invoke-NpmInstall([string]$workDir) {
    for ($attempt = 1; $attempt -le 3; $attempt++) {
        Push-Location $workDir
        try {
            & npm install --no-audit --no-fund --fetch-retries=5 --fetch-retry-mintimeout=20000 --fetch-retry-maxtimeout=120000
            $code = $LASTEXITCODE
        } finally {
            Pop-Location
        }
        if ($code -eq 0) { return }
        if ($attempt -lt 3) {
            Write-Warn "npm install failed (attempt $attempt of 3), probably a network drop. Retrying in 10 seconds..."
            Start-Sleep -Seconds 10
        }
    }
    Fail "npm install failed 3 times. Check your internet connection, then run: cd `"$workDir`"; npm install"
}

function New-Secret([int]$bytes = 32) {
    $buffer = New-Object byte[] $bytes
    [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($buffer)
    return [Convert]::ToBase64String($buffer)
}

# Alphanumeric only, so it is safe inside SQL and .env files without quoting.
function New-Password([int]$length = 24) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789'.ToCharArray()
    $buffer = New-Object byte[] $length
    [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($buffer)
    return -join ($buffer | ForEach-Object { $chars[$_ % $chars.Length] })
}

# Read KEY=value pairs from a .env file into a hashtable.
function Read-EnvFile([string]$path) {
    $values = @{}
    if (Test-Path $path) {
        foreach ($line in [System.IO.File]::ReadAllLines($path)) {
            if ($line -match '^\s*([A-Z0-9_]+)\s*=\s*(.*)$') { $values[$Matches[1]] = $Matches[2].Trim('"') }
        }
    }
    return $values
}

# Set (or add) KEY=value lines in .env content.
function Set-EnvValues([string]$content, [hashtable]$values) {
    foreach ($key in $values.Keys) {
        $line = "$key=$($values[$key])"
        $pattern = "(?m)^#?\s*$key=.*$"
        if ($content -match $pattern) {
            $content = [regex]::Replace($content, $pattern, [System.Text.RegularExpressions.MatchEvaluator] { param($m) $line }, 1)
        } else {
            $content = $content.TrimEnd() + "`n$line`n"
        }
    }
    return $content
}

function Find-Psql {
    $cmd = Get-Command psql -ErrorAction SilentlyContinue
    if ($cmd) { return $cmd.Source }
    $candidates = @(Get-ChildItem 'C:\Program Files\PostgreSQL\*\bin\psql.exe' -ErrorAction SilentlyContinue |
        Sort-Object { [int]($_.Directory.Parent.Name -replace '\D', '') } -Descending)
    if ($candidates.Count -gt 0) { return $candidates[0].FullName }
    return $null
}

# Backups: make sure backend\.env has a BACKUP_KEY and the pg_dump path.
function Set-BackupSettings([string]$envPath) {
    $current = Read-EnvFile $envPath
    $values = @{}
    if (-not $current['BACKUP_KEY']) {
        $gen = Invoke-Capture 'php' @('artisan', 'nis:backup', '--generate-key')
        $line = @($gen.Lines | Where-Object { $_ -like 'BACKUP_KEY=*' })[0]
        if ($line) { $values['BACKUP_KEY'] = $line.Substring('BACKUP_KEY='.Length) }
    }
    if (-not $current['PG_DUMP_PATH']) {
        $psql = Find-Psql
        if ($psql) {
            $dump = Join-Path (Split-Path -Parent $psql) 'pg_dump.exe'
            if (Test-Path -LiteralPath $dump) { $values['PG_DUMP_PATH'] = $dump }
        }
    }
    if ($values.Count -gt 0) {
        $content = [System.IO.File]::ReadAllText($envPath)
        Write-Utf8File $envPath (Set-EnvValues $content $values)
        if ($values.ContainsKey('BACKUP_KEY')) {
            Write-Warn 'A backup encryption key (BACKUP_KEY) was added to backend\.env. Keep a copy of it somewhere safe: backups cannot be restored without it.'
        }
    }
}
