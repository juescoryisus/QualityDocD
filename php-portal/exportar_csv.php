<?php
// =============================================================================
// QualityDoc Portal — Exportar logs de auditoría a CSV
// =============================================================================
require_once 'config.php';
requireLogin();

$user = getSessionUser();
$role = $user['role'] ?? 'Viewer';

// Solo Admin y Manager pueden exportar
if (!in_array($role, ['Admin', 'Manager'])) {
    http_response_code(403);
    exit('Acceso denegado. Se requiere rol Admin o Manager.');
}

function resolveCol(PDO $pdo, string $table, array $candidates): string {
    $stmt = $pdo->prepare(
        "SELECT column_name FROM information_schema.columns
         WHERE table_name = ? AND column_name = ANY(?)"
    );
    $stmt->execute([$table, '{' . implode(',', $candidates) . '}']);
    return $stmt->fetchColumn() ?: $candidates[0];
}

try {
    $pdo = getPgConnection();

    $cId       = resolveCol($pdo, 'audit_entries', ['id',            'Id']);
    $cAction   = resolveCol($pdo, 'audit_entries', ['action',        'Action']);
    $cDocId    = resolveCol($pdo, 'audit_entries', ['document_id',   'DocumentId']);
    $cDocCode  = resolveCol($pdo, 'audit_entries', ['document_code', 'DocumentCode']);
    $cDocTitle = resolveCol($pdo, 'audit_entries', ['document_title','DocumentTitle']);
    $cUsername = resolveCol($pdo, 'audit_entries', ['username',      'Username']);
    $cOldVal   = resolveCol($pdo, 'audit_entries', ['old_value',     'OldValue']);
    $cNewVal   = resolveCol($pdo, 'audit_entries', ['new_value',     'NewValue']);
    $cIp       = resolveCol($pdo, 'audit_entries', ['ip_address',    'IpAddress']);
    $cCreated  = resolveCol($pdo, 'audit_entries', ['created_at',    'CreatedAt']);

    // Si se pide un ID específico (desde detalles.php)
    $singleId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    // Filtros opcionales (desde audit.php)
    $filterAction = trim($_GET['action'] ?? '');
    $filterUser   = trim($_GET['user']   ?? '');
    $filterFrom   = trim($_GET['from']   ?? '');
    $filterTo     = trim($_GET['to']     ?? '');

    $where  = [];
    $params = [];

    if ($singleId > 0) {
        $where[] = "\"{$cId}\" = :id";
        $params[':id'] = $singleId;
    }
    if ($filterAction !== '') {
        $where[] = "\"{$cAction}\" = :action";
        $params[':action'] = $filterAction;
    }
    if ($filterUser !== '') {
        $where[] = "\"{$cUsername}\" ILIKE :user";
        $params[':user'] = '%' . $filterUser . '%';
    }
    if ($filterFrom !== '') {
        $where[] = "\"{$cCreated}\" >= :from";
        $params[':from'] = $filterFrom . ' 00:00:00';
    }
    if ($filterTo !== '') {
        $where[] = "\"{$cCreated}\" <= :to";
        $params[':to'] = $filterTo . ' 23:59:59';
    }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $pdo->prepare(
        "SELECT \"{$cId}\" AS id, \"{$cCreated}\" AS created_at,
                \"{$cAction}\" AS action, \"{$cUsername}\" AS username,
                \"{$cDocCode}\" AS document_code, \"{$cDocTitle}\" AS document_title,
                \"{$cIp}\" AS ip_address,
                \"{$cOldVal}\" AS old_value, \"{$cNewVal}\" AS new_value
         FROM audit_entries {$whereSQL}
         ORDER BY \"{$cCreated}\" DESC
         LIMIT 10000"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

} catch (Exception $e) {
    http_response_code(500);
    exit('Error de base de datos: ' . $e->getMessage());
}

// ── Generar CSV ───────────────────────────────────────────────────────────────
$filename = 'auditoria_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// BOM para que Excel lo abra con tildes correctamente
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

// Cabecera CSV
fputcsv($out, [
    'ID', 'Fecha/Hora', 'Acción', 'Usuario',
    'Código Documento', 'Título Documento',
    'IP Origen', 'Valores Anteriores', 'Valores Nuevos'
]);

foreach ($rows as $row) {
    // Formatear JSONB para legibilidad en CSV
    $oldVal = $row['old_value'];
    $newVal = $row['new_value'];
    if ($oldVal) { $dec = json_decode($oldVal, true); if ($dec) $oldVal = json_encode($dec, JSON_UNESCAPED_UNICODE); }
    if ($newVal) { $dec = json_decode($newVal, true); if ($dec) $newVal = json_encode($dec, JSON_UNESCAPED_UNICODE); }

    fputcsv($out, [
        $row['id'],
        $row['created_at'] ? date('d/m/Y H:i:s', strtotime($row['created_at'])) : '',
        $row['action']         ?? '',
        $row['username']       ?? '',
        $row['document_code']  ?? '',
        $row['document_title'] ?? '',
        $row['ip_address']     ?? '',
        $oldVal ?? '',
        $newVal ?? '',
    ]);
}

fclose($out);
exit;
