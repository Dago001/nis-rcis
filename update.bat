@echo off
rem NIS-RCIS: bring this folder up to date with the latest changes from GitHub.
rem Keeps your settings, database and uploads.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\windows\update.ps1" %*
echo.
pause
