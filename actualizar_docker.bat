@echo off
echo ===============================================
echo   Actualizando SiGeRU en Docker...
echo ===============================================

cd /d "%~dp0"

echo.
echo [1/2] Reconstruyendo las imagenes (toma los cambios de codigo)...
docker compose build

if errorlevel 1 (
    echo.
    echo ERROR: fallo la reconstruccion. Revisa el mensaje de arriba.
    pause
    exit /b 1
)

echo.
echo [2/2] Levantando los contenedores actualizados...
docker compose up -d

if errorlevel 1 (
    echo.
    echo ERROR: fallo al levantar los contenedores. Revisa el mensaje de arriba.
    pause
    exit /b 1
)

echo.
echo ===============================================
echo   Listo! Estado actual de los contenedores:
echo ===============================================
docker compose ps

echo.
echo Ahora abri http://localhost:8080 y actualiza la pagina con Ctrl+Shift+R
echo (phpMyAdmin de Docker: http://localhost:8082)
echo (si el navegador muestra algo viejo, es la cache del navegador, no de Docker).
echo.
pause
