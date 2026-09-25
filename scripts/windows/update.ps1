<#
.SYNOPSIS
    Bring this NIS-RCIS folder up to date with the latest changes on GitHub.

.DESCRIPTION
    Two ways, chosen automatically:

    1. Git installed: the folder is linked to GitHub the first time, then
       every run fetches the latest version of the branch.
    2. No Git: the latest ZIP is downloaded from GitHub automatically. If
       that fails, the newest nis-rcis*.zip in Downloads (or this folder)
       is used.

    Always kept: backend\.env, frontend\.env.local, backend\storage (keys,
    uploads, logs), vendor, node_modules and the database. Afterwards the
    dependencies are refreshed and new database migrations are applied.

.PARAMETER Zip
    Apply this specific ZIP file instead of looking for one.
#>
param(
    [string]$Zip
)

. (Join-Path $PSScriptRoot 'common.ps1')

$RepoUrl = 'https://github.com/Dago001/nis-rcis.git'
$Branch = 'claude/pensive-goodall-85zqwe'
$ZipUrl = "https://codeload.github.com/Dago001/nis-rcis/zip/refs/heads/$Branch"

# Never overwritten or removed by an update
$Protected = @(
    'backend/.env', 'frontend/.env.local',
    'backend/storage', 'backend/vendor', 'frontend/node_modules', 'frontend/.next', '.git'
)

function Test-Protected([string]$relative) {
    $rel = $relative -replace '\\', '/'
    foreach ($p in $Protected) {
        if ($rel -eq $p -or $rel.StartsWith("$p/")) { return $true }
    }
    return $false
}

Write-Host ''
Write-Host 'NIS-RCIS - update' -ForegroundColor Green
Write-Host '-----------------'

$git = Get-Command git -ErrorAction SilentlyContinue
$before = $null
if ($git -and (Test-Path (Join-Path $Root '.git'))) {
    $run = Invoke-Capture 'git' @('-C', $Root, 'rev-parse', '--short', 'HEAD')
    if ($run.Code -eq 0) { $before = $run.Text }
}

if (-not $Zip -and $git) {
    # ------------------------------------------------------------ Git update
    if (-not (Test-Path (Join-Path $Root '.git'))) {
        Write-Step 'Linking this folder to GitHub (first time only)'
        Invoke-Native 'git' @('init', '-q') $Root
        Invoke-Native 'git' @('remote', 'add', 'origin', $RepoUrl) $Root
    }

    Write-Step "Downloading the latest version ($Branch)"
    Invoke-Native 'git' @('fetch', '--depth', '50', 'origin', $Branch) $Root

    # Tracked project files are replaced with the latest version; ignored
    # files (.env, keys, uploads, vendor, node_modules) are never touched.
    Invoke-Native 'git' @('reset', '-q', '--hard', 'FETCH_HEAD') $Root
    Invoke-Native 'git' @('checkout', '-q', '-B', $Branch) $Root

    $after = (Invoke-Capture 'git' @('-C', $Root, 'rev-parse', '--short', 'HEAD')).Text
    if ($before -and $before -eq $after) {
        Write-Ok "Already up to date ($after)"
    } else {
        Write-Ok "Updated to $after"
        & git -C $Root log -5 --format='      %h  %s'
    }
} else {
    # ------------------------------------------------------------ ZIP update
    $downloadedZip = $null
    if (-not $Zip) {
        Write-Step "Downloading the latest version ($Branch) from GitHub"
        $downloadedZip = Join-Path ([System.IO.Path]::GetTempPath()) 'nis-rcis-latest.zip'
        try {
            [Net.ServicePointManager]::SecurityProtocol = [Net.ServicePointManager]::SecurityProtocol -bor [Net.SecurityProtocolType]::Tls12
            $ProgressPreference = 'SilentlyContinue'
            Invoke-WebRequest -Uri $ZipUrl -OutFile $downloadedZip -UseBasicParsing
            $Zip = $downloadedZip
            Write-Ok 'Downloaded'
        } catch {
            Write-Warn "Automatic download failed: $($_.Exception.Message)"
            $downloadedZip = $null
            $downloads = Join-Path $env:USERPROFILE 'Downloads'
            $candidates = @(Get-ChildItem -Path @($Root, $downloads) -Filter 'nis-rcis*.zip' -File -ErrorAction SilentlyContinue |
                Sort-Object LastWriteTime -Descending)
            if ($candidates.Count -eq 0) {
                Write-Host ''
                Write-Host 'Download the ZIP in your browser, leave it in your Downloads folder, and run update.bat again:' -ForegroundColor Yellow
                Write-Host "  https://github.com/Dago001/nis-rcis/archive/refs/heads/$Branch.zip"
                exit 1
            }
            $Zip = $candidates[0].FullName
        }
    }
    if (-not (Test-Path $Zip)) { Fail "ZIP not found: $Zip" }

    Write-Step "Applying $Zip"
    $temp = Join-Path ([System.IO.Path]::GetTempPath()) ('nis-rcis-update-' + [guid]::NewGuid().ToString('N'))
    Expand-Archive -Path $Zip -DestinationPath $temp -Force

    # GitHub ZIPs contain one top-level folder
    $source = $temp
    $top = @(Get-ChildItem $temp)
    if ($top.Count -eq 1 -and $top[0].PSIsContainer) { $source = $top[0].FullName }
    if (-not (Test-Path (Join-Path $source 'backend\artisan'))) { Fail 'This ZIP does not look like the NIS-RCIS project.' }

    $copied = 0
    foreach ($file in Get-ChildItem -Path $source -Recurse -File -Force) {
        $relative = $file.FullName.Substring($source.Length).TrimStart('\', '/')
        if (Test-Protected $relative) { continue }
        $target = Join-Path $Root $relative
        $dir = Split-Path -Parent $target
        if (-not (Test-Path $dir)) { New-Item -ItemType Directory -Path $dir -Force | Out-Null }
        Copy-Item -LiteralPath $file.FullName -Destination $target -Force
        $copied++
    }
    Remove-Item -Recurse -Force $temp
    Write-Ok "$copied files updated"
    if ($downloadedZip) { Remove-Item -Force $downloadedZip } else { Write-Warn "You can delete $Zip now." }
}

# ------------------------------------------------------ Refresh the app
if (-not (Test-Path (Join-Path $Backend '.env'))) {
    Write-Host ''
    Write-Host 'Code updated. The application is not set up yet: run setup.bat next.' -ForegroundColor Green
    exit 0
}

Write-Step 'Updating backend dependencies and database'
Invoke-Native 'composer' @('install', '--no-interaction', '--no-progress') $Backend
Invoke-Native 'php' @('artisan', 'config:clear') $Backend
Invoke-Native 'php' @('artisan', 'migrate', '--force') $Backend

Write-Step 'Updating frontend dependencies'
Invoke-NpmInstall $Frontend

Write-Host ''
Write-Host 'Update complete.' -ForegroundColor Green
Write-Host 'If the application is running, close the two server windows and run start.bat again.'
