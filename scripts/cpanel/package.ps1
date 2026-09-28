<#
.SYNOPSIS
    Build the two upload files for the cPanel test server:
      dist\cpanel\nis-rcis-frontend.zip  (Next.js, built for the sub-folder)
      dist\cpanel\nis-rcis-backend.zip   (Laravel, committed code + vendor)
    Your localhost setup is not changed. See docs\DEPLOY-CPANEL.md.

.EXAMPLE
    .\cpanel-package.bat
    .\cpanel-package.bat -SiteUrl https://niscoreapps.com.ng/nis-rcis -ApiUrl https://niscoreapps.com.ng/nis-rcis-api
#>
param(
    [string]$SiteUrl = 'https://niscoreapps.com.ng/nis-rcis',
    [string]$ApiUrl = 'https://niscoreapps.com.ng/nis-rcis-api'
)

. (Join-Path (Split-Path -Parent $PSScriptRoot) 'windows\common.ps1')

$SiteUrl = $SiteUrl.TrimEnd('/')
$ApiUrl = $ApiUrl.TrimEnd('/')
$BasePath = ([Uri]$SiteUrl).AbsolutePath.TrimEnd('/')
$Out = Join-Path $Root 'dist\cpanel'
$Tar = Join-Path $env:SystemRoot 'System32\tar.exe'   # writes zips with '/' paths, unlike Compress-Archive

if (-not (Test-Path $Tar)) { Fail 'tar.exe not found (it ships with Windows 10 1803 and later).' }
if (-not (Get-Command composer -ErrorAction SilentlyContinue)) { Fail 'Composer is not installed. Run setup.bat first.' }

Write-Step "Packaging for $SiteUrl (base path '$BasePath', API $ApiUrl)"
if (Test-Path $Out) { Remove-Item -Recurse -Force $Out }
New-Item -ItemType Directory -Force $Out | Out-Null

# The backend zip holds committed code only, so uncommitted changes would be missing.
$dirty = Invoke-Capture 'git' @('-C', $Root, 'status', '--porcelain', '--', 'backend')
if ($dirty.Text) { Write-Warn 'backend\ has uncommitted changes. They are NOT in the backend zip; commit them first to include them.' }

# ---- Frontend
Write-Step 'Building the website (Next.js) for the sub-folder'
# Process environment overrides frontend\.env.local, so localhost settings do not leak into this build.
$env:NEXT_PUBLIC_BASE_PATH = $BasePath
$env:NEXT_IMAGES_UNOPTIMIZED = '1'
$env:APP_URL = $SiteUrl
$env:API_URL = $ApiUrl
$env:API_PUBLIC_URL = $ApiUrl
$env:DOCUMENTS_PUBLIC_URL = $ApiUrl
Invoke-Native 'npm' @('run', 'build') $Frontend

$web = Join-Path $Out 'frontend'
Copy-Item -Recurse (Join-Path $Frontend '.next\standalone') $web
Copy-Item -Recurse (Join-Path $Frontend '.next\static') (Join-Path $web '.next\static')
Copy-Item -Recurse (Join-Path $Frontend 'public') (Join-Path $web 'public')
# Secrets belong in the cPanel Node.js app settings, never in the upload.
Get-ChildItem -Path $web -Recurse -Force -File -Filter '.env*' | Remove-Item -Force
Invoke-Native $Tar @('-a', '-c', '-f', (Join-Path $Out 'nis-rcis-frontend.zip'), '-C', $web, '.') $Root
Write-Ok 'dist\cpanel\nis-rcis-frontend.zip'

# ---- Backend
Write-Step 'Packaging the API (Laravel)'
$api = Join-Path $Out 'backend'
New-Item -ItemType Directory -Force $api | Out-Null
$archive = Join-Path $Out 'backend.tar'
Invoke-Native 'git' @('-C', $Root, 'archive', '--format=tar', '-o', $archive, 'HEAD:backend') $Root
Invoke-Native $Tar @('-x', '-f', $archive, '-C', $api) $Root
Remove-Item $archive
Invoke-Native 'composer' @('install', '--no-dev', '--optimize-autoloader', '--no-interaction', '--no-progress') $api
Invoke-Native $Tar @('-a', '-c', '-f', (Join-Path $Out 'nis-rcis-backend.zip'), '-C', $api, '.') $Root
Write-Ok 'dist\cpanel\nis-rcis-backend.zip'

Write-Host ''
Write-Host 'Done. Upload the two zips as described in docs\DEPLOY-CPANEL.md.' -ForegroundColor Green
Write-Host 'Localhost is unchanged: keep using start.bat (npm run dev ignores this build).'
