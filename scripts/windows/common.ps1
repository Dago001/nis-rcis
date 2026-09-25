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
