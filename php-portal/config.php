<?php
define('PG_DSN',  'pgsql:host=postgres;port=5432;dbname=qualitydoc_audit');
define('PG_USER', 'qualitydoc');
define('PG_PASS', 'QualityDoc_PG_2026!');

define('SEARCH_API', 'http://search-service:3001');

define('APP_NAME', 'QualityDoc Portal');

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

function statusBadge(string $status): string {
    $map = [
        'Draft'       => ['warning',   'Borrador'],
        'UnderReview' => ['primary',   'En Revisión'],
        'Approved'    => ['success',   'Aprobado'],
        'Obsolete'    => ['secondary', 'Obsoleto'],
    ];
    [$color, $label] = $map[$status] ?? ['dark', $status];
    return "<span class=\"badge bg-{$color}\">{$label}</span>";
}

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
    ];
    return '<i class="bi ' . ($map[$ext] ?? 'bi-file-earmark') . '"></i>';
}
