<?php
// =============================================================================
// QualityDoc Portal — Exportar logs de auditoría a CSV
// Acceso: OPERATOR+ (MODULE_1)
// =============================================================================
require_once 'config.php';
require_once '_roles.php';
requireLogin();

// Solo OPERATOR+ puede exportar
if (!hasMinRole('OPERATOR')) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Acceso denegado. Se requiere rol OPERATOR o superior.');
}

// ── Mapa acción → módulo (igual que en audit.php) ────────────────────────────
const ACTION_MODULE = [
    'Created'          => 'MODULE_1',
    'Updated'          => 'MODULE_1',
    'StatusChanged'    => 'MODULE_1',
    'Approved'         => 'MODULE_1',
    'Rejected'         => 'MODULE_1',
    'ApprovalAdded'    => 'MODULE_1',
    'ApprovalReviewed' => 'MODULE_1',
    'Downloaded'       => 'MODULE_2',
    'Viewed'           => 'MODULE_2',
    'Searched'         => 'MODULE_3',
    'AdvancedSearch'   => 'MODULE_3',
];

const MODULE_LABELS = [
    'MODULE_1' => 'Gestión',
    'MODULE_2' => 'Consulta',
    'MODULE_3' => 'Búsqueda Avanzada',
];

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

    // ── Parámetros de filtrado ────────────────────────────────────────────────
    $singleId     = isset($_GET['id'])     ? (int)$_GET['id']         : 0;
    $filterAction = trim($_GET['action']   ?? '');
    $filterUser   = trim($_GET['user']     ?? '');
    $filterModule = trim($_GET['module']   ?? '');
    $filterDate   = trim($_GET['date']     ?? ''); // YYYY-MM-DD (desde audit.php)
    $filterFrom   = trim($_GET['from']     ?? ''); // YYYY-MM-DD rango inicio
    $filterTo     = trim($_GET['to']       ?? ''); // YYYY-MM-DD rango fin

    $where  = [];
    $params = [];

    // ID específico (desde detalles.php)
    if ($singleId > 0) {
        $where[] = "\"{$cId}\" = :id";
        $params[':id'] = $singleId;
    }

    // Acción exacta
    if ($filterAction !== '') {
        $where[] = "\"{$cAction}\" = :action";
        $params[':action'] = $filterAction;
    }

    // Filtro por módulo: expandir a sus acciones concretas
    if ($filterModule !== '' && $singleId === 0 && $filterAction === '') {
        $moduleActions = array_keys(array_filter(
            ACTION_MODULE,
            fn($m) => $m === $filterModule
        ));
        if (!empty($moduleActions)) {
            $placeholders = implode(',', array_map(fn($i) => ":ma{$i}", array_keys($moduleActions)));
            $where[] = "\"{$cAction}\" IN ({$placeholders})";
            foreach ($moduleActions as $i => $act) $params[":ma{$i}"] = $act;
        }
    }

    // Usuario (búsqueda parcial)
    if ($filterUser !== '') {
        $where[] = "\"{$cUsername}\" ILIKE :user";
        $params[':user'] = '%' . $filterUser . '%';
    }

    // Fecha exacta (de audit.php ?date=YYYY-MM-DD)
    if ($filterDate !== '') {
        $where[] = "DATE(\"{$cCreated}\") = :date";
        $params[':date'] = $filterDate;
    }

    // Rango de fechas (from / to)
    if ($filterFrom !== '') {
        $where[] = "\"{$cCreated}\" >= :from";
        $params[':from'] = $filterFrom . ' 00:00:00';
    }
    if ($filterTo !== '') {
        $where[] = "\"{$cCreated}\" <= :to";
        $params[':to'] = $filterTo . ' 23:59:59';
    }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // COMPANY_ADMIN solo exporta datos de su propia empresa (si hay columna company_id)
    // Si la tabla tiene company_id, agregar filtro adicional
    $hasCompanyCol = false;
    $cCompany = resolveCol($pdo, 'audit_entries', ['company_id', 'CompanyId']);
    if ($cCompany !== 'company_id' || $pdo->query(
        "SELECT COUNT(*) FROM information_schema.columns
         WHERE table_name='audit_entries' AND column_name='company_id'"
    )->fetchColumn() > 0) {
        $hasCompanyCol = true;
    }

    $user    = getSessionUser();
    $isSuperAdmin = ($user['role'] ?? '') === 'SUPER_ADMIN';
    if ($hasCompanyCol && !$isSuperAdmin) {
        $sep = $whereSQL ? ' AND ' : 'WHERE ';
        $whereSQL .= $sep . "\"{$cCompany}\" = :companyId";
        $params[':companyId'] = (int)($user['companyId'] ?? 0);
    }

    $stmt = $pdo->prepare(
        "SELECT \"{$cId}\" AS id,
                \"{$cCreated}\" AS created_at,
                \"{$cAction}\" AS action,
                \"{$cUsername}\" AS username,
                \"{$cDocCode}\" AS document_code,
                \"{$cDocTitle}\" AS document_title,
                \"{$cIp}\" AS ip_address,
                \"{$cOldVal}\" AS old_value,
                \"{$cNewVal}\" AS new_value
         FROM audit_entries {$whereSQL}
         ORDER BY \"{$cCreated}\" DESC
         LIMIT 50000"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

} catch (Exception $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Error de base de datos: ' . $e->getMessage());
}

// ── Nombre del archivo con contexto del filtro ────────────────────────────────
$filenameParts = ['auditoria'];
if ($singleId > 0)       $filenameParts[] = "id{$singleId}";
if ($filterModule !== '') $filenameParts[] = strtolower(str_replace('MODULE_', 'mod', $filterModule));
if ($filterAction !== '') $filenameParts[] = strtolower($filterAction);
if ($filterDate !== '')   $filenameParts[] = str_replace('-', '', $filterDate);
elseif ($filterFrom !== '' || $filterTo !== '') {
    if ($filterFrom !== '') $filenameParts[] = 'desde' . str_replace('-', '', $filterFrom);
    if ($filterTo   !== '') $filenameParts[] = 'hasta' . str_replace('-', '', $filterTo);
}
$filenameParts[] = date('Ymd_His');
$filename = implode('_', $filenameParts) . '.csv';

// ── Headers HTTP ──────────────────────────────────────────────────────────────
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');

// BOM para que Excel lo abra con tildes correctamente
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

// ── Encabezado del CSV ────────────────────────────────────────────────────────
$exportedBy  = $user['name'] ?? $user['username'] ?? 'Desconocido';
$exportedAt  = date('d/m/Y H:i:s');
$exportRole  = $user['role'] ?? 'VIEWER';
$totalRows   = count($rows);

// Meta-información como comentarios al inicio
fputcsv($out, ['# QualityDoc — Exportación de Auditoría']);
fputcsv($out, ['# Exportado por:', $exportedBy, 'Rol:', $exportRole]);
fputcsv($out, ['# Fecha de exportación:', $exportedAt]);
fputcsv($out, ['# Total de registros:', $totalRows]);
if ($filterModule !== '')
    fputcsv($out, ['# Módulo filtrado:', MODULE_LABELS[$filterModule] ?? $filterModule]);
if ($filterAction !== '')
    fputcsv($out, ['# Acción filtrada:', $filterAction]);
if ($filterUser !== '')
    fputcsv($out, ['# Usuario filtrado:', $filterUser]);
if ($filterDate !== '')
    fputcsv($out, ['# Fecha filtrada:', date('d/m/Y', strtotime($filterDate))]);
fputcsv($out, ['']);  // línea vacía separadora

// Columnas
fputcsv($out, [
    'ID',
    'Fecha/Hora',
    'Módulo',
    'Acción',
    'Usuario',
    'Código Documento',
    'Título Documento',
    'IP Origen',
    'Valores Anteriores',
    'Valores Nuevos',
]);

// ── Filas de datos ────────────────────────────────────────────────────────────
foreach ($rows as $row) {
    $action  = $row['action'] ?? '';
    $modKey  = ACTION_MODULE[$action] ?? '';
    $modName = MODULE_LABELS[$modKey] ?? '—';

    // Formatear JSONB para legibilidad en CSV
    $oldVal = $row['old_value'] ?? '';
    $newVal = $row['new_value'] ?? '';
    if ($oldVal) { $dec = json_decode($oldVal, true); if ($dec) $oldVal = json_encode($dec, JSON_UNESCAPED_UNICODE); }
    if ($newVal) { $dec = json_decode($newVal, true); if ($dec) $newVal = json_encode($dec, JSON_UNESCAPED_UNICODE); }

    fputcsv($out, [
        $row['id'],
        $row['created_at'] ? date('d/m/Y H:i:s', strtotime($row['created_at'])) : '',
        $modName,
        $action,
        $row['username']       ?? '',
        $row['document_code']  ?? '',
        $row['document_title'] ?? '',
        $row['ip_address']     ?? '',
        $oldVal,
        $newVal,
    ]);
}

fclose($out);
exit;