@echo off
title SiGeRU - Detener Servidores (Windows)
chcp 65001 >nul
cd /d "%~dp0"

echo ====================================================
echo          DETENIENDO SERVIDORES SiGeRU
echo ====================================================
echo.
echo Cerrando servidores y terminales...

:: 1. Cerrar procesos PHP en ejecucion
taskkill /F /IM php.exe /T >nul 2>&1

:: 2. Cerrar ventanas de terminal por titulo
taskkill /F /FI "WINDOWTITLE eq SiGeRU - API*" /T >nul 2>&1
taskkill /F /FI "WINDOWTITLE eq SiGeRU - Frontend*" /T >nul 2>&1

:: 3. Cerrar cualquier proceso remanente por puerto (8090-8094)
for /f "tokens=5" %%a in ('netstat -aon ^| findstr ":8090 :8091 :8092 :8093 :8094" 2^>nul') do (
    taskkill /F /PID %%a /T >nul 2>&1
)

:: 4. Cerrar terminales remanentes mediante PowerShell si quedaron abiertas
powershell -NoProfile -Command "try { Get-Process | Where-Object { $_.MainWindowTitle -like 'SiGeRU - *' } | Stop-Process -Force } catch {}" >nul 2>&1
powershell -NoProfile -Command "try { Get-CimInstance Win32_Process -Filter \"Name = 'cmd.exe'\" | Where-Object { $_.CommandLine -like '*localhost:809*' -or $_.CommandLine -like '*api-*' } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force } } catch {}" >nul 2>&1

echo.
echo ====================================================
echo   Todos los servidores y terminales fueron cerrados.
echo ====================================================
echo.
echo Presioná cualquier tecla para salir...
pause >nul
