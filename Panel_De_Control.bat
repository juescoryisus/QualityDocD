@echo off
TITLE QualityDoc - Detener Ecosistema Docker
color 0C
echo ==========================================================
echo       DETENIENDO ARQUITECTURA DE CONTENEDORES
echo       Sistema Integral de Gestion Documental
echo ==========================================================
echo.

cd /d "%~dp0"

echo [PASO 1] Deteniendo aplicaciones (.NET, PHP, Node)...
docker-compose -f docker-compose.apps.yml down
echo.

echo [PASO 2] Deteniendo infraestructura (bases de datos)...
docker-compose -f docker-compose.infra.yml down
echo.

echo ==========================================================
echo   Ecosistema detenido. Recursos de RAM liberados.
echo ==========================================================
echo.
pause
