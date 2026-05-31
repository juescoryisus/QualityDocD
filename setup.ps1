#Requires -Version 5.1
<#
.SYNOPSIS
    QualityDoc — Script de configuración y arranque automático.

.DESCRIPTION
    1. Verifica que Docker Desktop esté corriendo.
    2. Copia .env.example → .env si no existe.
    3. Levanta SQL Server, PostgreSQL y MongoDB con Docker Compose.
    4. Espera a que los 3 motores estén listos (health checks).
    5. Abre el proyecto QualityDocD en Visual Studio.

.EXAMPLE
    .\setup.ps1
    .\setup.ps1 -SkipVS          # No abre Visual Studio al final
    .\setup.ps1 -ResetData       # Borra los volúmenes y empieza de cero
#>

param(
    [switch] $SkipVS,
    [switch] $ResetData
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

# ── Colores de consola ────────────────────────────────────────────────────────
function Write-Step  ($msg) { Write-Host "`n  ● $msg"  -ForegroundColor Cyan    }
function Write-OK    ($msg) { Write-Host "  ✔ $msg"    -ForegroundColor Green   }
function Write-Warn  ($msg) { Write-Host "  ⚠ $msg"    -ForegroundColor Yellow  }
function Write-Fail  ($msg) { Write-Host "  ✖ $msg"    -ForegroundColor Red     }

# ── Banner ────────────────────────────────────────────────────────────────────
Clear-Host
Write-Host ""
Write-Host "  ╔══════════════════════════════════════════╗" -ForegroundColor Blue
Write-Host "  ║        QualityDoc — Setup rápido         ║" -ForegroundColor Blue
Write-Host "  ║  SQL Server · PostgreSQL · MongoDB · VS  ║" -ForegroundColor Blue
Write-Host "  ╚══════════════════════════════════════════╝" -ForegroundColor Blue
Write-Host ""

# ── 0. Moverse a la carpeta del script ───────────────────────────────────────
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Definition
Set-Location $ScriptDir

# ── 1. Verificar Docker Desktop ───────────────────────────────────────────────
Write-Step "Verificando Docker Desktop..."

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    Write-Fail "Docker no está instalado o no está en el PATH."
    Write-Host "  Descárgalo en: https://www.docker.com/products/docker-desktop" -ForegroundColor Gray
    exit 1
}

$dockerInfo = docker info 2>&1
if ($LASTEXITCODE -ne 0) {
    Write-Fail "Docker Desktop no está corriendo. Ábrelo y vuelve a ejecutar este script."
    exit 1
}
Write-OK "Docker Desktop está corriendo."

# ── 2. Archivo .env ───────────────────────────────────────────────────────────
Write-Step "Configurando archivo .env..."

if (-not (Test-Path ".env")) {
    if (Test-Path ".env.example") {
        Copy-Item ".env.example" ".env"
        Write-OK ".env creado desde .env.example."
        Write-Warn "Revisa las contraseñas en .env antes de pasar a producción."
    } else {
        Write-Fail "No se encontró .env.example. Asegúrate de estar en la carpeta correcta."
        exit 1
    }
} else {
    Write-OK ".env ya existe — se respetan las configuraciones actuales."
}

# ── 3. Borrar datos si se pidió reset ─────────────────────────────────────────
if ($ResetData) {
    Write-Step "Borrando volúmenes de datos (ResetData activado)..."
    docker compose down -v 2>&1 | Out-Null
    Write-OK "Volúmenes eliminados. Las bases de datos se crearán desde cero."
}

# ── 4. Levantar Docker Compose ────────────────────────────────────────────────
Write-Step "Levantando SQL Server, PostgreSQL y MongoDB..."

docker compose up -d 2>&1 | ForEach-Object { Write-Host "    $_" -ForegroundColor DarkGray }

if ($LASTEXITCODE -ne 0) {
    Write-Fail "Error al ejecutar 'docker compose up -d'. Revisa el mensaje anterior."
    exit 1
}
Write-OK "Contenedores iniciados."

# ── 5. Esperar a que los 3 motores estén listos ───────────────────────────────
Write-Step "Esperando a que los motores de base de datos estén listos..."

$Services = @{
    "qualitydoc_sqlserver" = "SQL Server"
    "qualitydoc_postgres"  = "PostgreSQL"
    "qualitydoc_mongodb"   = "MongoDB"
}

$MaxWait    = 120   # segundos máximos de espera
$Interval   =   3   # segundos entre intentos

foreach ($Container in $Services.Keys) {
    $Name    = $Services[$Container]
    $Elapsed = 0
    $Ready   = $false

    Write-Host "  Esperando $Name" -NoNewline -ForegroundColor White

    while ($Elapsed -lt $MaxWait) {
        $Status = docker inspect --format='{{.State.Health.Status}}' $Container 2>$null
        if ($Status -eq "healthy") {
            Write-Host " ✔" -ForegroundColor Green
            $Ready = $true
            break
        }
        Write-Host "." -NoNewline -ForegroundColor DarkGray
        Start-Sleep -Seconds $Interval
        $Elapsed += $Interval
    }

    if (-not $Ready) {
        Write-Host " ⚠" -ForegroundColor Yellow
        Write-Warn "$Name tardó más de ${MaxWait}s. Puede que aún esté iniciando."
        Write-Warn "Verifica con: docker compose ps"
    }
}

# ── 6. Resumen de puertos ─────────────────────────────────────────────────────
Write-Host ""
Write-Host "  ┌──────────────────────────────────────────┐" -ForegroundColor Blue
Write-Host "  │  Servicios activos                       │" -ForegroundColor Blue
Write-Host "  ├──────────────────┬───────────────────────┤" -ForegroundColor Blue
Write-Host "  │  SQL Server 2022 │  localhost:1433        │" -ForegroundColor Blue
Write-Host "  │  PostgreSQL 16   │  localhost:5432        │" -ForegroundColor Blue
Write-Host "  │  MongoDB 7       │  localhost:27017       │" -ForegroundColor Blue
Write-Host "  └──────────────────┴───────────────────────┘" -ForegroundColor Blue
Write-Host ""

# ── 7. Abrir Visual Studio ────────────────────────────────────────────────────
if (-not $SkipVS) {
    Write-Step "Abriendo proyecto en Visual Studio..."

    $CsprojPath = Resolve-Path "QualityDocD\QualityDocD.csproj" -ErrorAction SilentlyContinue

    if (-not $CsprojPath) {
        Write-Warn "No se encontró QualityDocD\QualityDocD.csproj."
        Write-Warn "Ábrelo manualmente desde Visual Studio."
    } else {
        # Buscar devenv.exe en las ubicaciones comunes de VS 2019, 2022
        $VsPaths = @(
            "${env:ProgramFiles}\Microsoft Visual Studio\2022\Community\Common7\IDE\devenv.exe",
            "${env:ProgramFiles}\Microsoft Visual Studio\2022\Professional\Common7\IDE\devenv.exe",
            "${env:ProgramFiles}\Microsoft Visual Studio\2022\Enterprise\Common7\IDE\devenv.exe",
            "${env:ProgramFiles(x86)}\Microsoft Visual Studio\2019\Community\Common7\IDE\devenv.exe",
            "${env:ProgramFiles(x86)}\Microsoft Visual Studio\2019\Professional\Common7\IDE\devenv.exe"
        )

        $DevEnv = $VsPaths | Where-Object { Test-Path $_ } | Select-Object -First 1

        if ($DevEnv) {
            Write-OK "Visual Studio encontrado en:"
            Write-Host "    $DevEnv" -ForegroundColor DarkGray
            Start-Process $DevEnv -ArgumentList "`"$CsprojPath`""
            Write-OK "Visual Studio abriendo QualityDocD.csproj..."
        } else {
            # Fallback: usar el comando 'start' de Windows (abre VS según la asociación de .csproj)
            Write-Warn "No se encontró devenv.exe. Intentando abrir con el programa predeterminado..."
            Start-Process $CsprojPath
        }
    }
}

# ── 8. Mensaje final ──────────────────────────────────────────────────────────
Write-Host ""
Write-Host "  ══════════════════════════════════════════" -ForegroundColor Green
Write-Host "   ¡Todo listo! Presiona F5 en Visual Studio" -ForegroundColor Green
Write-Host "   Las migraciones se aplican automáticamente" -ForegroundColor Green
Write-Host "   al arrancar la app por primera vez."        -ForegroundColor Green
Write-Host ""
Write-Host "   Usuarios de prueba:" -ForegroundColor Green
Write-Host "     admin    / Admin123!"    -ForegroundColor White
Write-Host "     gerente  / Gerente123!"  -ForegroundColor White
Write-Host "     revisor  / Revisor123!"  -ForegroundColor White
Write-Host "     operario / Operario123!" -ForegroundColor White
Write-Host "  ══════════════════════════════════════════" -ForegroundColor Green
Write-Host ""

# ── Comandos de ayuda ─────────────────────────────────────────────────────────
Write-Host "  Comandos útiles:" -ForegroundColor DarkGray
Write-Host "    docker compose ps            # Ver estado de los contenedores" -ForegroundColor DarkGray
Write-Host "    docker compose logs -f       # Ver logs en tiempo real"         -ForegroundColor DarkGray
Write-Host "    docker compose down          # Detener (conserva los datos)"    -ForegroundColor DarkGray
Write-Host "    .\setup.ps1 -ResetData       # Borrar datos y empezar de cero"  -ForegroundColor DarkGray
Write-Host "    .\setup.ps1 -SkipVS          # Iniciar sin abrir Visual Studio" -ForegroundColor DarkGray
Write-Host ""
