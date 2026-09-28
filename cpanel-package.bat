@echo off
rem NIS-RCIS: build the upload files for the cPanel test server (see docs\DEPLOY-CPANEL.md).
rem Your localhost setup is not changed.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\cpanel\package.ps1" %*
echo.
pause
