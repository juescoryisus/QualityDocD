@echo off
TITLE QualityDoc - Iniciar Ecosistema Docker
color 0A
echo ==========================================================
echo       INICIANDO ARQUITECTURA DE CONTENEDORES
echo       Sistema Integral de Gestion Documental
echo ==========================================================
echo.

cd /d "%~dp0"

echo [PASO 1] Iniciando infraestructura (SQL Server, PostgreSQL, MongoDB)...
docker-compose -f docker-compose.infra.yml up -d --build
if %errorlevel% neq 0 (
    echo ERROR: Fallo al iniciar la infraestructura. Verifica que Docker Desktop este corriendo.
    pause
    exit /b 1
)
echo.

echo [PASO 2] Esperando que las bases de datos esten listas (20 seg)...
timeout /t 20 /nobreak >nul
echo.

echo [PASO 3] Iniciando aplicaciones (.NET, PHP Portal, Node API, Search)...
docker-compose -f docker-compose.apps.yml up -d --build
if %errorlevel% neq 0 (
    echo ERROR: Fallo al iniciar las aplicaciones.
    pause
    exit /b 1
)
echo.

echo [PASO 4] Validando estado de los servicios...
docker-compose -f docker-compose.infra.yml ps
docker-compose -f docker-compose.apps.yml ps
echo.

echo ==========================================================
echo  Ecosistema iniciado correctamente.
echo.
echo  Modulo 1 - Gestion (.NET Core): http://localhost:5001
echo  Modulo 2 - Auditoria (PHP):     http://localhost:8080
echo  Modulo 3 - Busqueda (Node.js):  http://localhost:3001
echo  Node API (interno):             http://localhost:5000
echo ==========================================================
echo.
pause
