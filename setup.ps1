param(
    [ValidateSet("All", "Net", "Php", "Node")]
    [string] $Mode = "All",
    [switch] $SkipVS,
    [switch] $ResetData
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

# ── Archivos Compose ──────────────────────────────────────────────────────────
$ComposeNet  = "docker-compose.net.yml"
$ComposePhp  = "docker-compose.php.yml"
$ComposeNode = "docker-compose.node.yml"

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
Write-Host "  ║   Organizado por Stacks en Docker Desktop        ║" -ForegroundColor Blue
Write-Host "  ╚══════════════════════════════════════════════════╝" -ForegroundColor Blue
Write-Host "  Modo de ejecución: $Mode" -ForegroundColor DarkCyan
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

# ── 1.5 Asegurar Red Externa Compartida ───────────────────────────────────────
if (-not (docker network ls --filter name=^qualitydoc_net$ --format "{{.Name}}")) {
    docker network create qualitydoc_net | Out-Null
    Write-OK "Red compartida externa 'qualitydoc_net' creada correctamente."
}

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
    docker compose -f $ComposeNode down -v 2>&1 | Out-Null
    docker compose -f $ComposePhp  down -v 2>&1 | Out-Null
    docker compose -f $ComposeNet  down -v 2>&1 | Out-Null
    Write-OK "Contenedores y volúmenes eliminados de todos los proyectos."
}

# ── Función auxiliar: levantar un composer ────────────────────────────────────
function Start-Compose ($File, $Label) {
    Write-Step "Levantando servicios en $Label..."
    docker compose -f $File up -d 2>&1 | ForEach-Object { Write-Host "    $_" -ForegroundColor DarkGray }
    if ($LASTEXITCODE -ne 0) {
        Write-Fail "Error al levantar el proyecto $Label. Revisa el mensaje anterior."
        exit 1
    }
    Write-OK "Proyecto $Label iniciado correctamente."
}

# ── 4. Levantar según el modo ─────────────────────────────────────────────────
switch ($Mode) {
    "Net"  { Start-Compose $ComposeNet  "stack 'qualitydoc-dotnet-sql'" }
    "Php"  { Start-Compose $ComposePhp  "stack 'qualitydoc-php-postgres'" }
    "Node" { Start-Compose $ComposeNode "stack 'qualitydoc-node-mongo'" }
    "All" {
        Start-Compose $ComposeNet  "stack 'qualitydoc-dotnet-sql'"
        Start-Compose $ComposePhp  "stack 'qualitydoc-php-postgres'"
        Start-Compose $ComposeNode "stack 'qualitydoc-node-mongo'"
    }
}

# ── 5. Esperar a que los motores de BD estén listos ───────────────────────────
Write-Step "Esperando estabilidad en los motores de base de datos activos..."

$Services = @{}
if ($Mode -in @("Net", "All")) { $Services.Add("qualitydoc_sqlserver", "SQL Server") }
if ($Mode -in @("Php", "All")) { $Services.Add("qualitydoc_postgres", "PostgreSQL") }
if ($Mode -in @("Node", "All")) { $Services.Add("qualitydoc_mongodb", "MongoDB") }

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
    }
}

# ── 6. Resumen de servicios activos ───────────────────────────────────────────
Write-Host ""
Write-Host "  ┌────────────────────────────────────────────────────┐" -ForegroundColor Blue
Write-Host "  │  Servicios activos (Organizados por Stack)         │" -ForegroundColor Blue
Write-Host "  ├──────────────────────┬─────────────────────────────┤" -ForegroundColor Blue

if ($Mode -in @("Net", "All")) {
    Write-Host "  │ [qualitydoc-dotnet-sql]                            │" -ForegroundColor Cyan
    Write-Host "  │  SQL Server 2022      │  localhost:1433             │" -ForegroundColor Blue
    Write-Host "  │  .NET App             │  localhost:5001             │" -ForegroundColor Blue
}
if ($Mode -in @("Php", "All")) {
    Write-Host "  │ [qualitydoc-php-postgres]                          │" -ForegroundColor Cyan
    Write-Host "  │  PostgreSQL 16        │  localhost:5432             │" -ForegroundColor Blue
    Write-Host "  │  PHP Portal           │  localhost:8080             │" -ForegroundColor Blue
    Write-Host "  │  Node API             │  localhost:5000             │" -ForegroundColor Blue
}
if ($Mode -in @("Node", "All")) {
    Write-Host "  │ [qualitydoc-node-mongo]                            │" -ForegroundColor Cyan
    Write-Host "  │  MongoDB 7            │  localhost:27017            │" -ForegroundColor Blue
    Write-Host "  │  Search Service       │  localhost:3001             │" -ForegroundColor Blue
}

Write-Host "  └──────────────────────┴─────────────────────────────┘" -ForegroundColor Blue
Write-Host ""

# ── 7. Abrir Visual Studio ────────────────────────────────────────────────────
$ShouldOpenVS = ($Mode -in @("Net", "All")) -and (-not $SkipVS)

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
Write-Host "    ¡Todo listo y organizado en Docker Desktop!" -ForegroundColor Green
Write-Host ""
Write-Host "    Usuarios de prueba:" -ForegroundColor Green
Write-Host "     admin    / Admin123!"    -ForegroundColor White
Write-Host "     gerente  / Gerente123!"  -ForegroundColor White
Write-Host "     revisor1 / Revisor123!"  -ForegroundColor White
Write-Host "     editor   / Editor123!"   -ForegroundColor White
Write-Host "  ══════════════════════════════════════════════════════" -ForegroundColor Green
Write-Host ""

# ── Comandos de ayuda ─────────────────────────────────────────────────────────
Write-Host "  Comandos útiles por archivo de configuración:" -ForegroundColor DarkGray
Write-Host "    docker compose -f docker-compose.net.yml  ps      # Estado stack .NET" -ForegroundColor DarkGray
Write-Host "    docker compose -f docker-compose.php.yml  ps      # Estado stack PHP" -ForegroundColor DarkGray
Write-Host "    docker compose -f docker-compose.node.yml ps      # Estado stack Node" -ForegroundColor DarkGray
Write-Host ""
Write-Host "    .\\setup.ps1 -Mode Net     # Levantar solo stack .NET" -ForegroundColor DarkGray
Write-Host "    .\\setup.ps1 -Mode Php     # Levantar solo stack PHP" -ForegroundColor DarkGray
Write-Host "    .\\setup.ps1 -Mode Node    # Levantar solo stack Node" -ForegroundColor DarkGray
Write-Host "    .\\setup.ps1 -ResetData    # Limpiar volúmenes y recrear todo" -ForegroundColor DarkGray
Write-Host ""