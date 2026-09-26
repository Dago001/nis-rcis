<#
.SYNOPSIS
    Create an encrypted backup of the NIS-RCIS database and documents, then
    check that it can be decrypted and read (restore drill).

    Backups are written to backend\storage\backups (the 14 most recent are
    kept). To run it every night, create a Windows Task Scheduler task that
    runs backup.bat.
#>
. (Join-Path $PSScriptRoot 'common.ps1')

Write-Host ''
Write-Host 'NIS-RCIS - backup' -ForegroundColor Green
Write-Host '-----------------'

Push-Location $Backend
Set-BackupSettings (Join-Path $Backend '.env')
Pop-Location

Write-Step 'Creating the encrypted backup'
Invoke-Native 'php' @('artisan', 'nis:backup') $Backend

$latest = Get-ChildItem -LiteralPath (Join-Path $Backend 'storage\backups') -Filter 'nis-rcis-*.nisbak' | Sort-Object LastWriteTime -Descending | Select-Object -First 1
if ($latest) {
    Write-Step 'Checking the backup can be restored'
    Invoke-Native 'php' @('artisan', 'nis:backup', "--verify=$($latest.FullName)") $Backend
}

Write-Host ''
Write-Host 'Backup complete. Copy backend\storage\backups to another drive or cloud storage regularly.' -ForegroundColor Green
