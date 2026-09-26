@echo off
rem NIS-RCIS: encrypted backup of the database and documents, with a restore check.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\windows\backup.ps1"
echo.
pause
