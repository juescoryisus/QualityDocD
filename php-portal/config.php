<?php
// ─────────────────────────────────────────────────────────────────────────────
//  QualityDoc PHP Portal — Configuración
// ─────────────────────────────────────────────────────────────────────────────

define('PG_DSN',  'pgsql:host=postgres;port=5432;dbname=qualitydoc_audit');
define('PG_USER', getenv('PG_USER') ?: 'qualitydoc');
define('PG_PASS', getenv('PG_PASSWORD') ?: 'QualityDoc_PG_2026!');

define('SEARCH_API',   'http://search-service:3001');
define('DOTNET_API',   'http://qualitydoc-app:5000');   // API .NET para login/validación

define('APP_NAME', 'QualityDoc Portal');

// ── Conexión PostgreSQL ───────────────────────────────────────────────────────
function getPgConnection(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(PG_DSN, PG_USER, PG_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

// ── Llamada al Search Service (MongoDB) ──────────────────────────────────────
function callSearchApi(string $path): array {
    $url = SEARCH_API . $path;
    $ctx = stream_context_create(['http' => [
        'timeout' => 5,
        'header'  => 'Accept: application/json',
    ]]);
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) return [];
    return json_decode($raw, true) ?? [];
}

// ── Llamada al Search Service filtrando por empresa (multiempresa) ────────────
// Agrega automáticamente companyId del usuario en sesión a todos los requests
// de búsqueda para aislar documentos por empresa.
function callSearchApiForCompany(string $path): array {
    $user      = getSessionUser();
    $companyId = $user['companyId'] ?? null;

    if ($companyId !== null) {
        $separator = str_contains($path, '?') ? '&' : '?';
        $path .= $separator . 'companyId=' . urlencode((string)$companyId);
    }

    return callSearchApi($path);
}

// ── Llamada al API .NET (autenticación, etc.) ─────────────────────────────────
function callDotNetApi(string $path, string $method = 'GET', array $body = [], string $token = ''): array {
    $opts = [
        'http' => [
            'method'  => $method,
            'timeout' => 5,
            'header'  => implode("\r\n", array_filter([
                'Content-Type: application/json',
                'Accept: application/json',
                $token ? "Authorization: Bearer {$token}" : '',
            ])),
        ]
    ];
    if ($body) {
        $opts['http']['content'] = json_encode($body);
    }
    $ctx = stream_context_create($opts);
    $raw = @file_get_contents(DOTNET_API . $path, false, $ctx);
    if ($raw === false) return ['error' => 'API no disponible'];
    return json_decode($raw, true) ?? [];
}

// ── Login contra API .NET (para PHP + acceso a Mongo) ────────────────────────
function loginToApi(string $username, string $password): array {
    $result = callDotNetApi('/api/auth/login', 'POST', [
        'username' => $username,
        'password' => $password,
    ]);
    return $result;
}

// ── Sesión simple del portal PHP ─────────────────────────────────────────────
function startPortalSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function getSessionUser(): ?array {
    startPortalSession();
    return $_SESSION['portal_user'] ?? null;
}

function setSessionUser(array $user, string $token): void {
    startPortalSession();
    $_SESSION['portal_user']  = $user;
    $_SESSION['portal_token'] = $token;
}

function clearSessionUser(): void {
    startPortalSession();
    unset($_SESSION['portal_user'], $_SESSION['portal_token']);
}

function getSessionToken(): ?string {
    startPortalSession();
    return $_SESSION['portal_token'] ?? null;
}

function requireLogin(): void {
    if (getSessionUser() === null) {
        header('Location: login.php');
        exit;
    }
}

// ── Badge de estado — ahora incluye todos los estados intermedios ─────────────
function statusBadge(string $status): string {
    $map = [
        'Draft'             => ['secondary', 'bi-pencil-square',     'Borrador'],
        'UnderReview'       => ['warning',   'bi-hourglass-split',   'En Revisión'],
        'PendingChanges'    => ['info',      'bi-pencil-fill',       'Cambios Pendientes'],
        'UnderSecondReview' => ['primary',   'bi-hourglass-top',     '2ª Revisión'],
        'Approved'          => ['success',   'bi-check-circle-fill', 'Aprobado'],
        'Rejected'          => ['danger',    'bi-x-circle-fill',     'Rechazado'],
        'Obsolete'          => ['dark',      'bi-archive-fill',      'Obsoleto'],
    ];
    [$color, $icon, $label] = $map[$status] ?? ['light', 'bi-question', $status];
    $textColor = in_array($color, ['warning', 'info', 'light']) ? 'text-dark' : 'text-white';
    return "<span class=\"badge bg-{$color} {$textColor}\"><i class=\"bi {$icon} me-1\"></i>{$label}</span>";
}

// ── Icono por extensión de archivo ────────────────────────────────────────────
function extIcon(string $ext): string {
    $map = [
        '.pdf'  => 'bi-file-pdf text-danger',
        '.docx' => 'bi-file-word text-primary',
        '.doc'  => 'bi-file-word text-primary',
        '.xlsx' => 'bi-file-excel text-success',
        '.xls'  => 'bi-file-excel text-success',
        '.pptx' => 'bi-file-ppt text-warning',
        '.png'  => 'bi-file-image text-info',
        '.jpg'  => 'bi-file-image text-info',
        '.jpeg' => 'bi-file-image text-info',
        '.zip'  => 'bi-file-zip text-secondary',
    ];
    return '<i class="bi ' . ($map[$ext] ?? 'bi-file-earmark') . '"></i>';
}

// ── Formatear tamaño de archivo ────────────────────────────────────────────────
function formatSize(int $bytes): string {
    if ($bytes < 1024)       return "{$bytes} B";
    if ($bytes < 1048576)    return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}

// ── Truncar texto para preview ────────────────────────────────────────────────
function truncateText(string $text, int $maxChars = 160): string {
    $text = trim(preg_replace('/\s+/', ' ', $text));
    if (mb_strlen($text) <= $maxChars) return htmlspecialchars($text);
    return htmlspecialchars(mb_substr($text, 0, $maxChars)) . '…';
}
