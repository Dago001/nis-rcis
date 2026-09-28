@echo off
rem NIS-RCIS: one-time local setup for testing on Windows (no Docker).
rem Add -Reset to wipe the local database and reload the demo data.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\windows\setup.ps1" %*
echo.
pause
