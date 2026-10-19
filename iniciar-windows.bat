@echo off
title SiGeRU - Iniciar Servidores (Windows)
chcp 65001 >nul
cd /d "%~dp0"

echo ====================================================
echo        INICIANDO SERVIDORES SiGeRU (WINDOWS)
echo ====================================================
echo.

:: Detectar ejecutable de PHP
set "PHP_BIN=php"
where php >nul 2>&1
if errorlevel 1 (
    if exist "C:\xampp\php\php.exe" (
        set "PHP_BIN=C:\xampp\php\php.exe"
    ) else (
        echo [ERROR] No se encontro PHP en el sistema.
        echo Asegurate de tener XAMPP instalado en C:\xampp o PHP en las variables de entorno.
        echo.
        pause
        exit /b 1
    )
)

echo PHP detectado: "%PHP_BIN%"
echo.
echo Iniciando terminales para cada servicio...
echo.

:: Iniciar APIs y frontend en ventanas de CMD separadas
echo [1/5] Iniciando API Usuarios en puerto 8091...
start "SiGeRU - API Usuarios [8091]" cmd /c "title SiGeRU - API Usuarios [8091] && echo Servidor API Usuarios en http://localhost:8091 && "%PHP_BIN%" -S localhost:8091 api-usuarios/index.php"

timeout /t 1 /nobreak >nul

echo [2/5] Iniciando API Logistica en puerto 8092...
start "SiGeRU - API Logistica [8092]" cmd /c "title SiGeRU - API Logistica [8092] && echo Servidor API Logistica en http://localhost:8092 && "%PHP_BIN%" -S localhost:8092 api-logistica/index.php"

timeout /t 1 /nobreak >nul

echo [3/5] Iniciando API Contenedores en puerto 8093...
start "SiGeRU - API Contenedores [8093]" cmd /c "title SiGeRU - API Contenedores [8093] && echo Servidor API Contenedores en http://localhost:8093 && "%PHP_BIN%" -S localhost:8093 api-contenedores/index.php"

timeout /t 1 /nobreak >nul

echo [4/5] Iniciando API Centros en puerto 8094...
start "SiGeRU - API Centros [8094]" cmd /c "title SiGeRU - API Centros [8094] && echo Servidor API Centros en http://localhost:8094 && "%PHP_BIN%" -S localhost:8094 api-centros/index.php"

timeout /t 1 /nobreak >nul

echo [5/5] Iniciando Frontend en puerto 8090...
start "SiGeRU - Frontend [8090]" cmd /c "title SiGeRU - Frontend [8090] && echo Servidor Frontend en http://localhost:8090 && "%PHP_BIN%" -S localhost:8090 -t frontend"

echo.
echo ====================================================
echo  Todos los servicios se iniciaron correctamente.
echo.
echo  - Frontend:         http://localhost:8090
echo  - API Usuarios:     http://localhost:8091
echo  - API Logistica:    http://localhost:8092
echo  - API Contenedores: http://localhost:8093
echo  - API Centros:      http://localhost:8094
echo ====================================================
echo.
echo Abriendo navegador en http://localhost:8090...
start http://localhost:8090
echo.
echo Podes cerrar esta ventana cuando quieras. Las terminales continuaran activas.
echo Para cerrarlas podes usar detener-windows.bat o cerrar cada ventana.
echo.
pause
