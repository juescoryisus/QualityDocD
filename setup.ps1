
param(
    [ValidateSet("All", "Infra", "Apps", "Portal")]
    [string] $Mode = "All",
    [switch] $SkipVS,
    [switch] $ResetData
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

# ── Archivos Compose ──────────────────────────────────────────────────────────
$ComposeInfra  = "docker-compose.infra.yml"
$ComposeApps   = "docker-compose.apps.yml"
$ComposePortal = "docker-compose.portal.yml"

# ── Colores de consola ────────────────────────────────────────────────────────
function Write-Step  ($msg) { Write-Host "`n  ● $msg"  -ForegroundColor Cyan    }
function Write-OK    ($msg) { Write-Host "  ✔ $msg"    -ForegroundColor Green   }
function Write-Warn  ($msg) { Write-Host "  ⚠ $msg"    -ForegroundColor Yellow  }
function Write-Fail  ($msg) { Write-Host "  ✖ $msg"    -ForegroundColor Red     }

# ── Banner ────────────────────────────────────────────────────────────────────
Clear-Host
Write-Host ""
Write-Host "  ╔══════════════════════════════════════════════════╗" -ForegroundColor Blue
Write-Host "  ║         QualityDoc — Setup rápido                ║" -ForegroundColor Blue
Write-Host "  ║  Infra · Apps · Portal  (3 composers)            ║" -ForegroundColor Blue
Write-Host "  ╚══════════════════════════════════════════════════╝" -ForegroundColor Blue
Write-Host "  Modo: $Mode" -ForegroundColor DarkCyan
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
    docker compose -f $ComposeInfra  down -v 2>&1 | Out-Null
    docker compose -f $ComposeApps   down    2>&1 | Out-Null
    docker compose -f $ComposePortal down    2>&1 | Out-Null
    Write-OK "Volúmenes eliminados. Las bases de datos se crearán desde cero."
}

# ── Función auxiliar: levantar un composer ────────────────────────────────────
function Start-Compose ($File, $Label) {
    Write-Step "Levantando $Label ($File)..."
    docker compose -f $File up -d 2>&1 | ForEach-Object { Write-Host "    $_" -ForegroundColor DarkGray }
    if ($LASTEXITCODE -ne 0) {
        Write-Fail "Error al levantar $Label. Revisa el mensaje anterior."
        exit 1
    }
    Write-OK "$Label iniciado."
}

# ── 4. Levantar según el modo ─────────────────────────────────────────────────
switch ($Mode) {
    "Infra"  { Start-Compose $ComposeInfra  "Infraestructura (SQL Server + PostgreSQL + MongoDB)" }
    "Apps"   { Start-Compose $ComposeApps   "Backend (.NET + Node API + Search Service)" }
    "Portal" { Start-Compose $ComposePortal "Portal (PHP)" }
    "All" {
        Start-Compose $ComposeInfra  "Infraestructura (SQL Server + PostgreSQL + MongoDB)"
        Start-Compose $ComposeApps   "Backend (.NET + Node API + Search Service)"
        Start-Compose $ComposePortal "Portal (PHP)"
    }
}

# ── 5. Esperar a que los motores de BD estén listos ───────────────────────────
if ($Mode -in @("Infra", "All")) {
    Write-Step "Esperando a que los motores de base de datos estén listos..."

    $Services = @{
        "qualitydoc_sqlserver" = "SQL Server"
        "qualitydoc_postgres"  = "PostgreSQL"
        "qualitydoc_mongodb"   = "MongoDB"
    }

    $MaxWait  = 120
    $Interval = 3

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
            Write-Warn "Verifica con: docker compose -f $ComposeInfra ps"
        }
    }
}

# ── 6. Resumen de servicios activos ───────────────────────────────────────────
Write-Host ""
Write-Host "  ┌────────────────────────────────────────────────────┐" -ForegroundColor Blue
Write-Host "  │  Servicios activos (modo: $Mode)" -ForegroundColor Blue
Write-Host "  ├──────────────────────┬─────────────────────────────┤" -ForegroundColor Blue

if ($Mode -in @("Infra", "All")) {
    Write-Host "  │  SQL Server 2022      │  localhost:1433             │" -ForegroundColor Blue
    Write-Host "  │  PostgreSQL 16        │  localhost:5432             │" -ForegroundColor Blue
    Write-Host "  │  MongoDB 7            │  localhost:27017            │" -ForegroundColor Blue
}
if ($Mode -in @("Apps", "All")) {
    Write-Host "  │  .NET App             │  localhost:5001             │" -ForegroundColor Blue
    Write-Host "  │  Node API             │  localhost:5000             │" -ForegroundColor Blue
    Write-Host "  │  Search Service       │  localhost:3001             │" -ForegroundColor Blue
}
if ($Mode -in @("Portal", "All")) {
    Write-Host "  │  PHP Portal           │  localhost:8080             │" -ForegroundColor Blue
}

Write-Host "  └──────────────────────┴─────────────────────────────┘" -ForegroundColor Blue
Write-Host ""

# ── 7. Abrir Visual Studio (solo en modo All, a menos que -OpenVS se indique) ─
$ShouldOpenVS = ($Mode -eq "All") -and (-not $SkipVS)

if ($ShouldOpenVS) {
    Write-Step "Abriendo proyecto en Visual Studio..."

    $CsprojPath = Resolve-Path "QualityDocD\QualityDocD.csproj" -ErrorAction SilentlyContinue

    if (-not $CsprojPath) {
        Write-Warn "No se encontró QualityDocD\QualityDocD.csproj."
        Write-Warn "Ábrelo manualmente desde Visual Studio."
    } else {
        $VsPaths = @(
            "${env:ProgramFiles}\Microsoft Visual Studio\2022\Community\Common7\IDE\devenv.exe",
            "${env:ProgramFiles}\Microsoft Visual Studio\2022\Professional\Common7\IDE\devenv.exe",
            "${env:ProgramFiles}\Microsoft Visual Studio\2022\Enterprise\Common7\IDE\devenv.exe",
            "${env:ProgramFiles(x86)}\Microsoft Visual Studio\2019\Community\Common7\IDE\devenv.exe",
            "${env:ProgramFiles(x86)}\Microsoft Visual Studio\2019\Professional\Common7\IDE\devenv.exe"
        )

        $DevEnv = $VsPaths | Where-Object { Test-Path $_ } | Select-Object -First 1

        if ($DevEnv) {
            Write-OK "Visual Studio encontrado."
            Start-Process $DevEnv -ArgumentList "`"$CsprojPath`""
            Write-OK "Visual Studio abriendo QualityDocD.csproj..."
        } else {
            Write-Warn "No se encontró devenv.exe. Intentando abrir con el programa predeterminado..."
            Start-Process $CsprojPath
        }
    }
}

# ── 8. Mensaje final ──────────────────────────────────────────────────────────
Write-Host ""
Write-Host "  ══════════════════════════════════════════════════════" -ForegroundColor Green
Write-Host "   ¡Todo listo!" -ForegroundColor Green
Write-Host ""
Write-Host "   Usuarios de prueba:" -ForegroundColor Green
Write-Host "     admin    / Admin123!"    -ForegroundColor White
Write-Host "     gerente  / Gerente123!"  -ForegroundColor White
Write-Host "     revisor1 / Revisor123!"  -ForegroundColor White
Write-Host "     editor   / Editor123!"   -ForegroundColor White
Write-Host "  ══════════════════════════════════════════════════════" -ForegroundColor Green
Write-Host ""

# ── Comandos de ayuda ─────────────────────────────────────────────────────────
Write-Host "  Comandos útiles por composer:" -ForegroundColor DarkGray
Write-Host "    docker compose -f docker-compose.infra.yml  ps      # Estado BD"        -ForegroundColor DarkGray
Write-Host "    docker compose -f docker-compose.apps.yml   ps      # Estado backend"   -ForegroundColor DarkGray
Write-Host "    docker compose -f docker-compose.portal.yml ps      # Estado portal"    -ForegroundColor DarkGray
Write-Host ""
Write-Host "    docker compose -f docker-compose.infra.yml  logs -f # Logs BD"          -ForegroundColor DarkGray
Write-Host "    docker compose -f docker-compose.apps.yml   logs -f # Logs backend"     -ForegroundColor DarkGray
Write-Host "    docker compose -f docker-compose.portal.yml logs -f # Logs portal"      -ForegroundColor DarkGray
Write-Host ""
Write-Host "    .\setup.ps1 -Mode Infra    # Solo bases de datos"    -ForegroundColor DarkGray
Write-Host "    .\setup.ps1 -Mode Apps     # Solo backend"           -ForegroundColor DarkGray
Write-Host "    .\setup.ps1 -Mode Portal   # Solo PHP portal"        -ForegroundColor DarkGray
Write-Host "    .\setup.ps1 -ResetData     # Borrar datos y reiniciar" -ForegroundColor DarkGray
Write-Host ""

