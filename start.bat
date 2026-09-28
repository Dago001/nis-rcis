@echo off
rem NIS-RCIS: start the API (port 8000) and the website (port 3000), then open the browser.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\windows\start.ps1"
echo.
pause
