@echo off
:: Panel de Control Inteligente - QualityDoc
title Panel de Control - QualityDoc
chcp 65001 > nul
cls

:MENU
cls
echo =====================================================================
echo                 PANEL DE CONTROL — QUALITYDOC
echo =====================================================================
echo.
echo   [ CONTENEDORES ]
echo     1. Encender TODO (Bases de Datos + Apps + Portal)
echo     2. Apagar TODO (Limpiar contenedores y liberar memoria)
echo     3. Reiniciar TODO rápido
echo     4. Encender SOLO Bases de Datos  [qualitydoc-databases]
echo     5. Encender SOLO Aplicaciones    [qualitydoc-apps]
echo.
echo   [ ACCESOS DIRECTOS (Abrir Navegador) ]
echo     6. Abrir Aplicación .NET    -- http://localhost:5001
echo     7. Abrir Node API           -- http://localhost:5000
echo     8. Abrir Search Service     -- http://localhost:3001
echo     9. Abrir Portal PHP         -- http://localhost:8080
echo.
echo   [ SISTEMA ]
echo     10. Ver estado actual (Docker PS)
echo     11. Salir del panel
echo.
echo =====================================================================
set /p opc="Seleccione una opción (1-11) y presione ENTER: "

if "%opc%"=="1" goto START_ALL
if "%opc%"=="2" goto STOP_ALL
if "%opc%"=="3" goto RESTART_ALL
if "%opc%"=="4" goto START_INFRA
if "%opc%"=="5" goto START_APPS
if "%opc%"=="6" goto OPEN_NET
if "%opc%"=="7" goto OPEN_NODE
if "%opc%"=="8" goto OPEN_SEARCH
if "%opc%"=="9" goto OPEN_PHP
if "%opc%"=="10" goto STATUS
if "%opc%"=="11" goto EXIT

echo.
echo [x] Opción inválida. Intente de nuevo...
timeout /t 2 > nul
goto MENU

:START_ALL
echo.
echo [+] Levantando infraestructura completa y aplicaciones...
:: Se ejecutan por separado para que Docker respete las carpetas (proyectos)
docker compose -f docker-compose.infra.yml up -d
docker compose -f docker-compose.apps.yml up -d
docker compose -f docker-compose.portal.yml up -d
echo.
echo [!] Sistema encendido.
pause
goto MENU

:STOP_ALL
echo.
echo [-] Apagando todos los contenedores...
:: Apagamos en orden inverso para evitar conflictos de dependencias
docker compose -f docker-compose.portal.yml down
docker compose -f docker-compose.apps.yml down
docker compose -f docker-compose.infra.yml down
echo.
echo [!] Todos los servicios se han detenido correctamente.
pause
goto MENU

:RESTART_ALL
echo.
echo [*] Reiniciando servicios en segundo plano...
docker compose -f docker-compose.infra.yml restart
docker compose -f docker-compose.apps.yml restart
docker compose -f docker-compose.portal.yml restart
echo.
echo [!] Reinicio completado.
pause
goto MENU

:START_INFRA
echo.
echo [+] Levantando carpeta visual [qualitydoc-databases]...
docker compose -f docker-compose.infra.yml up -d
echo.
pause
goto MENU

:START_APPS
echo.
echo [+] Levantando carpeta visual [qualitydoc-apps]...
docker compose -f docker-compose.apps.yml -f docker-compose.portal.yml up -d
echo.
pause
goto MENU

:OPEN_NET
echo.
echo [+] Abriendo Aplicación .NET...
start http://localhost:5001
goto MENU

:OPEN_NODE
echo.
echo [+] Abriendo Node API...
start http://localhost:5000
goto MENU

:OPEN_SEARCH
echo.
echo [+] Abriendo Search Service...
start http://localhost:3001
goto MENU

:OPEN_PHP
echo.
echo [+] Abriendo Portal PHP...
start http://localhost:8080
goto MENU

:STATUS
echo.
echo =====================================================================
echo                   ESTADO ACTUAL DE CONTENEDORES
echo =====================================================================
docker ps --format "table {{.Names}}\t{{.Ports}}\t{{.Status}}"
echo =====================================================================
echo.
pause
goto MENU

:EXIT
echo.
echo ¡Hasta luego! Desarrolla con éxito.
timeout /t 2 > nul
exit