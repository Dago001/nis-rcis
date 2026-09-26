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
    if (-not (Test-Path -LiteralPath $Zip)) { Fail "ZIP not found: $Zip" }

    Write-Step "Applying $Zip"
    $temp = Join-Path ([System.IO.Path]::GetTempPath()) ('nis-rcis-update-' + [guid]::NewGuid().ToString('N'))
    Expand-Archive -LiteralPath $Zip -DestinationPath $temp -Force

    # GitHub ZIPs contain one top-level folder
    $source = $temp
    $top = @(Get-ChildItem $temp)
    if ($top.Count -eq 1 -and $top[0].PSIsContainer) { $source = $top[0].FullName }
    if (-not [System.IO.File]::Exists((Join-Path $source 'backend\artisan'))) { Fail 'This ZIP does not look like the NIS-RCIS project.' }

    # .NET file APIs are used on purpose: PowerShell treats [ and ] in paths
    # (e.g. frontend\src\app\staff\cards\[id]) as wildcards.
    $copied = 0
    $inZip = New-Object 'System.Collections.Generic.HashSet[string]' ([StringComparer]::OrdinalIgnoreCase)
    foreach ($path in [System.IO.Directory]::EnumerateFiles($source, '*', [System.IO.SearchOption]::AllDirectories)) {
        $relative = $path.Substring($source.Length).TrimStart('\', '/')
        [void]$inZip.Add(($relative -replace '\\', '/'))
        if (Test-Protected $relative) { continue }
        $target = [System.IO.Path]::Combine($Root, $relative)
        [void][System.IO.Directory]::CreateDirectory([System.IO.Path]::GetDirectoryName($target))
        [System.IO.File]::Copy($path, $target, $true)
        $copied++
    }

    # Remove project source files that no longer exist in the latest version
    # (for example pages that moved). Only these code folders are cleaned;
    # settings, keys, uploads and installed packages are never touched.
    $removed = 0
    $codeFolders = @('backend/app', 'backend/config', 'backend/database', 'backend/resources',
        'backend/routes', 'backend/tests', 'frontend/src', 'frontend/public', 'scripts', 'deploy')
    foreach ($folder in $codeFolders) {
        $full = [System.IO.Path]::Combine($Root, $folder)
        if (-not [System.IO.Directory]::Exists($full)) { continue }
        foreach ($path in [System.IO.Directory]::EnumerateFiles($full, '*', [System.IO.SearchOption]::AllDirectories)) {
            $relative = ($path.Substring($Root.Length).TrimStart('\', '/')) -replace '\\', '/'
            if ($relative -like '*.sqlite') { continue }
            if (-not $inZip.Contains($relative) -and -not (Test-Protected $relative)) {
                [System.IO.File]::Delete($path)
                $removed++
            }
        }
    }
    # Drop folders left empty by the clean-up
    foreach ($folder in $codeFolders) {
        $full = [System.IO.Path]::Combine($Root, $folder)
        if (-not [System.IO.Directory]::Exists($full)) { continue }
        $dirs = @([System.IO.Directory]::EnumerateDirectories($full, '*', [System.IO.SearchOption]::AllDirectories)) | Sort-Object Length -Descending
        foreach ($d in $dirs) {
            if (-not [System.IO.Directory]::EnumerateFileSystemEntries($d).GetEnumerator().MoveNext()) { [System.IO.Directory]::Delete($d) }
        }
    }
    Remove-Item -LiteralPath $temp -Recurse -Force
    Write-Ok "$copied files updated, $removed old files removed"
    if ($downloadedZip) { Remove-Item -Force $downloadedZip } else { Write-Warn "You can delete $Zip now." }
}

# The website's build cache can keep serving pages that moved or no longer
# exist ("404 This page could not be found"); it is rebuilt automatically.
$nextCache = Join-Path $Frontend '.next'
if ([System.IO.Directory]::Exists($nextCache)) {
    Write-Step 'Clearing the website build cache'
    try {
        [System.IO.Directory]::Delete($nextCache, $true)
        Write-Ok 'Cleared'
    } catch {
        Write-Warn 'Could not clear frontend\.next (is the website still running?). Close the server windows and run update.bat again.'
    }
}

# ------------------------------------------------------ Refresh the app
if (-not (Test-Path (Join-Path $Backend '.env'))) {
    Write-Host ''
    Write-Host 'Code updated. The application is not set up yet: run setup.bat next.' -ForegroundColor Green
    exit 0
}

Write-Step 'Updating backend dependencies and database'
Invoke-Native 'composer' @('install', '--no-interaction', '--no-progress') $Backend
Push-Location $Backend; Set-BackupSettings (Join-Path $Backend '.env'); Pop-Location
Invoke-Native 'php' @('artisan', 'optimize:clear') $Backend
Invoke-Native 'php' @('artisan', 'migrate', '--force') $Backend

Write-Step 'Updating frontend dependencies'
Invoke-NpmInstall $Frontend

Write-Host ''
Write-Host 'Update complete.' -ForegroundColor Green
Write-Host 'If the application is running, close the two server windows and run start.bat again.'
