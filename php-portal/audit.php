<?php
// =============================================================================
// QualityDoc Portal — Auditoría
// Acceso: OPERATOR+ (MODULE_1)
// =============================================================================
require_once 'config.php';
require_once '_roles.php';
requireLogin();
requireModule('MODULE_1'); // Solo OPERATOR, COMPANY_ADMIN, SUPER_ADMIN

$user  = getSessionUser();
$token = getSessionToken();
$role  = $user['role'] ?? 'VIEWER';

$page     = max(1, (int)($_GET['page'] ?? 1));
$pageSize = 15;
$offset   = ($page - 1) * $pageSize;

$filterAction = trim($_GET['action'] ?? '');
$filterUser   = trim($_GET['user']   ?? '');
$filterModule = trim($_GET['module'] ?? '');
$filterDate   = trim($_GET['date']   ?? '');

$logs       = [];
$total      = 0;
$actions    = [];
$compliance = [];
$error      = null;
$pgOk       = false;

// ── Mapa acción → módulo ──────────────────────────────────────────────────────
$ACTION_MODULE = [
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

$MODULE_LABELS = [
    'MODULE_1' => ['Gestión',         '#4f8ef7'],
    'MODULE_2' => ['Consulta',        '#10b981'],
    'MODULE_3' => ['Búsq. Avanzada',  '#8b5cf6'],
];

$ACTION_MAP = [
    'Created'          => ['bi-plus-circle-fill',   '#10b981'],
    'Updated'          => ['bi-pencil-fill',         '#f59e0b'],
    'StatusChanged'    => ['bi-arrow-repeat',         '#4f8ef7'],
    'Approved'         => ['bi-check-circle-fill',   '#10b981'],
    'Rejected'         => ['bi-x-circle-fill',       '#ef4444'],
    'Downloaded'       => ['bi-download',             '#14b8a6'],
    'Viewed'           => ['bi-eye-fill',             '#6b7280'],
    'ApprovalAdded'    => ['bi-person-check-fill',   '#4f8ef7'],
    'ApprovalReviewed' => ['bi-patch-check-fill',    '#10b981'],
    'Searched'         => ['bi-search',               '#8b5cf6'],
    'AdvancedSearch'   => ['bi-search-heart',         '#a855f7'],
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
    $pdo  = getPgConnection();
    $pgOk = true;

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

    $actions = $pdo
        ->query("SELECT DISTINCT \"{$cAction}\" FROM audit_entries ORDER BY \"{$cAction}\"")
        ->fetchAll(PDO::FETCH_COLUMN);

    // ── Filtrar acciones por módulo seleccionado ──────────────────────────────
    $moduleActions = [];
    if ($filterModule !== '') {
        foreach ($ACTION_MODULE as $act => $mod) {
            if ($mod === $filterModule) $moduleActions[] = $act;
        }
    }

    $where  = [];
    $params = [];
    if ($filterAction !== '') {
        $where[] = "\"{$cAction}\" = :action";
        $params[':action'] = $filterAction;
    } elseif (!empty($moduleActions)) {
        $placeholders = implode(',', array_map(fn($i) => ":ma{$i}", array_keys($moduleActions)));
        $where[] = "\"{$cAction}\" IN ({$placeholders})";
        foreach ($moduleActions as $i => $act) $params[":ma{$i}"] = $act;
    }
    if ($filterUser !== '') {
        $where[] = "\"{$cUsername}\" ILIKE :user";
        $params[':user'] = '%' . $filterUser . '%';
    }
    if ($filterDate !== '') {
        $where[] = "DATE(\"{$cCreated}\") = :date";
        $params[':date'] = $filterDate;
    }
    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM audit_entries {$whereSQL}");
    $stmtCount->execute($params);
    $total = (int)$stmtCount->fetchColumn();

    $stmtLogs = $pdo->prepare(
        "SELECT \"{$cId}\" AS id, \"{$cAction}\" AS action,
                \"{$cDocCode}\" AS document_code, \"{$cDocTitle}\" AS document_title,
                \"{$cUsername}\" AS username, \"{$cOldVal}\" AS old_value,
                \"{$cNewVal}\" AS new_value, \"{$cIp}\" AS ip_address,
                \"{$cCreated}\" AS created_at
         FROM audit_entries {$whereSQL}
         ORDER BY \"{$cCreated}\" DESC
         LIMIT :limit OFFSET :offset"
    );
    foreach ($params as $k => $v) $stmtLogs->bindValue($k, $v);
    $stmtLogs->bindValue(':limit',  $pageSize, PDO::PARAM_INT);
    $stmtLogs->bindValue(':offset', $offset,   PDO::PARAM_INT);
    $stmtLogs->execute();
    $logs = $stmtLogs->fetchAll();

    $compliance = $pdo->query(
        "SELECT \"{$cAction}\" AS action, COUNT(*) AS total,
                COUNT(DISTINCT \"{$cDocId}\") AS docs,
                MAX(\"{$cCreated}\") AS last_event
         FROM audit_entries
         GROUP BY \"{$cAction}\"
         ORDER BY total DESC"
    )->fetchAll();

} catch (Exception $e) {
    $error = $e->getMessage();
}

$totalPages = $total > 0 ? (int)ceil($total / $pageSize) : 1;

function buildPageUrl(int $p, string $action, string $user, string $module, string $date): string {
    return 'audit.php?' . http_build_query(array_filter([
        'page'   => $p,
        'action' => $action,
        'user'   => $user,
        'module' => $module,
        'date'   => $date,
    ]));
}

$currentPage = 'audit';
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Auditoría — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root { --sidebar-w:260px; --topbar-h:56px; --bg-sidebar:#0f1117; --bg-main:#13151a; --accent:#4f8ef7; }
        body { margin:0; background:var(--bg-main); font-family:'Segoe UI',system-ui,sans-serif; }
        #sidebar { position:fixed; top:0; left:0; bottom:0; width:var(--sidebar-w); background:var(--bg-sidebar); border-right:1px solid rgba(255,255,255,.07); display:flex; flex-direction:column; z-index:200; transition:transform .25s; }
        .sidebar-brand { display:flex; align-items:center; gap:10px; padding:18px 20px 16px; border-bottom:1px solid rgba(255,255,255,.07); }
        .brand-icon { width:36px; height:36px; border-radius:8px; background:var(--accent); display:flex; align-items:center; justify-content:center; font-size:1.1rem; color:#fff; }
        .brand-name { font-weight:700; font-size:.95rem; color:#e8eaf0; }
        .brand-sub  { font-size:.72rem; color:#6b7280; }
        .sidebar-user { display:flex; align-items:center; gap:10px; padding:14px 18px; border-bottom:1px solid rgba(255,255,255,.07); }
        .user-avatar { width:34px; height:34px; border-radius:50%; background:linear-gradient(135deg,#4f8ef7,#7c3aed); display:flex; align-items:center; justify-content:center; font-weight:700; font-size:.85rem; color:#fff; flex-shrink:0; }
        .user-name { font-size:.82rem; font-weight:600; color:#e0e2ea; }
        .user-role { font-size:.7rem; color:#6b7280; }
        .sidebar-section { padding:14px 12px 4px; font-size:.66rem; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#4b5563; }
        .nav-item-link { display:flex; align-items:center; gap:11px; padding:9px 14px; margin:1px 8px; border-radius:8px; text-decoration:none; color:#9ca3af; font-size:.85rem; font-weight:500; transition:background .15s,color .15s; }
        .nav-item-link:hover  { background:rgba(79,142,247,.12); color:#c8d4f5; }
        .nav-item-link.active { background:rgba(79,142,247,.18); color:var(--accent); }
        .nav-item-link i { font-size:1rem; width:18px; text-align:center; }
        .sidebar-services { padding:0 12px 8px; }
        .service-badge { display:flex; align-items:center; gap:8px; padding:7px 10px; margin-bottom:2px; border-radius:8px; text-decoration:none; background:rgba(255,255,255,.03); border:1px solid rgba(255,255,255,.06); font-size:.78rem; color:#9ca3af; transition:background .15s; }
        .service-badge:hover { background:rgba(255,255,255,.07); color:#d1d5db; }
        .service-dot { width:7px; height:7px; border-radius:50%; flex-shrink:0; }
        .dot-ok { background:#22c55e; } .dot-off { background:#6b7280; }
        .sidebar-footer { margin-top:auto; padding:14px 12px; border-top:1px solid rgba(255,255,255,.07); }
        #topbar { position:fixed; top:0; left:var(--sidebar-w); right:0; height:var(--topbar-h); background:var(--bg-sidebar); border-bottom:1px solid rgba(255,255,255,.07); display:flex; align-items:center; padding:0 24px; z-index:100; gap:12px; }
        .page-title { font-size:1rem; font-weight:600; color:#e0e2ea; }
        #main { margin-left:var(--sidebar-w); margin-top:var(--topbar-h); padding:28px 28px 40px; min-height:calc(100vh - var(--topbar-h)); }
        .hub-card { background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08); border-radius:12px; overflow:hidden; }
        .hub-card-header { padding:14px 18px; border-bottom:1px solid rgba(255,255,255,.07); display:flex; align-items:center; gap:8px; font-size:.85rem; font-weight:600; color:#c8d0e0; }
        .table-row { border-color:rgba(255,255,255,.05) !important; }
        .table-row td { border-color:rgba(255,255,255,.05); vertical-align:middle; }
        .module-tab { display:inline-flex; align-items:center; gap:5px; padding:4px 12px; border-radius:99px; font-size:.72rem; font-weight:600; cursor:pointer; border:1px solid transparent; text-decoration:none; transition:all .15s; }
        .stat-chip { background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.08); border-radius:10px; padding:14px 18px; }
        @media(max-width:768px){ #sidebar{transform:translateX(-100%);} #sidebar.open{transform:translateX(0);} #topbar,#main{left:0;margin-left:0;} }
    </style>
</head>
<body>
<?php require_once '_sidebar.php'; ?>

<main id="main">

    <div class="d-flex align-items-start justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h5 class="fw-bold text-white mb-1">Registro de Auditoría</h5>
            <p class="text-muted small mb-0">
                Eventos en tiempo real desde <strong>PostgreSQL</strong>
                <code style="font-size:.75rem">(qualitydoc_audit)</code>
            </p>
        </div>
        <?php if (hasMinRole('OPERATOR')): ?>
        <a href="exportar_csv.php<?= ($filterAction||$filterUser||$filterModule||$filterDate) ? '?'.http_build_query(array_filter(['action'=>$filterAction,'user'=>$filterUser,'module'=>$filterModule,'date'=>$filterDate])) : '' ?>"
           class="btn btn-sm" style="background:rgba(16,185,129,.15);color:#10b981;border:1px solid rgba(16,185,129,.3);">
            <i class="bi bi-download me-1"></i>Exportar CSV
        </a>
        <?php endif; ?>
    </div>

    <?php if ($error): ?>
    <div class="alert border-0 mb-4" style="background:rgba(239,68,68,.1);color:#fca5a5;">
        <i class="bi bi-x-circle me-2"></i>
        Error de conexión a PostgreSQL: <code><?= htmlspecialchars($error) ?></code>
        <br><small>Verifica que el contenedor <code>qualitydoc_postgres</code> esté corriendo.</small>
    </div>
    <?php else: ?>

    <!-- Tabs de módulo (acceso rápido) -->
    <div class="d-flex gap-2 flex-wrap mb-4">
        <a href="audit.php" class="module-tab"
           style="background:<?= $filterModule===''?'rgba(79,142,247,.2)':'rgba(255,255,255,.05)' ?>;
                  border-color:<?= $filterModule===''?'rgba(79,142,247,.4)':'rgba(255,255,255,.1)' ?>;
                  color:<?= $filterModule===''?'#4f8ef7':'#9ca3af' ?>">
            <i class="bi bi-grid-3x3-gap"></i> Todos los módulos
        </a>
        <?php foreach ($MODULE_LABELS as $mKey => [$mName, $mColor]): ?>
        <a href="audit.php?module=<?= $mKey ?>" class="module-tab"
           style="background:<?= $filterModule===$mKey?"rgba(0,0,0,.2)":"rgba(255,255,255,.05)" ?>;
                  border-color:<?= $filterModule===$mKey?$mColor:"rgba(255,255,255,.1)" ?>;
                  color:<?= $filterModule===$mKey?$mColor:"#9ca3af" ?>">
            <i class="bi bi-circle-fill" style="font-size:.45rem"></i> <?= $mName ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Resumen por tipo -->
    <?php if (!empty($compliance)): ?>
    <?php
    // Agrupar compliance por módulo para mostrar mini-stats
    $byModule = [];
    foreach ($compliance as $row) {
        $mod = $ACTION_MODULE[$row['action']] ?? 'Otros';
        $byModule[$mod] = ($byModule[$mod] ?? 0) + (int)$row['total'];
    }
    ?>
    <div class="row g-3 mb-4">
        <?php foreach ($MODULE_LABELS as $mKey => [$mName, $mColor]): ?>
        <div class="col-6 col-md-4">
            <div class="stat-chip">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <span style="width:8px;height:8px;border-radius:50%;background:<?= $mColor ?>;display:inline-block"></span>
                    <span style="font-size:.72rem;color:#9ca3af;font-weight:600"><?= $mName ?></span>
                </div>
                <div style="font-size:1.5rem;font-weight:700;color:#e0e2ea"><?= number_format($byModule[$mKey] ?? 0) ?></div>
                <div style="font-size:.7rem;color:#6b7280">eventos totales</div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="hub-card mb-4">
        <div class="hub-card-header">
            <i class="bi bi-clipboard-data" style="color:#8b5cf6"></i> Desglose por Tipo de Evento
        </div>
        <div class="table-responsive">
            <table class="table mb-0" style="font-size:.82rem;">
                <thead style="background:rgba(255,255,255,.03);">
                    <tr style="color:#6b7280;">
                        <th class="px-4 py-3 fw-semibold">Acción</th>
                        <th class="py-3 fw-semibold">Módulo</th>
                        <th class="py-3 text-center fw-semibold">Total</th>
                        <th class="py-3 text-center fw-semibold">Docs</th>
                        <th class="py-3 fw-semibold">Último Evento</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($compliance as $row):
                        [$ico, $col] = $ACTION_MAP[$row['action']] ?? ['bi-circle','#6b7280'];
                        $mod   = $ACTION_MODULE[$row['action']] ?? null;
                        [$modLabel, $modColor] = $mod ? $MODULE_LABELS[$mod] : ['—','#6b7280'];
                    ?>
                    <tr class="table-row">
                        <td class="px-4 py-2">
                            <span style="display:inline-flex;align-items:center;gap:5px;background:<?= $col ?>18;color:<?= $col ?>;padding:3px 9px;border-radius:99px;font-size:.75rem;font-weight:600;">
                                <i class="bi <?= $ico ?>"></i> <?= htmlspecialchars($row['action']) ?>
                            </span>
                        </td>
                        <td class="py-2">
                            <span style="font-size:.7rem;padding:2px 8px;border-radius:99px;background:<?= $modColor ?>18;color:<?= $modColor ?>;border:1px solid <?= $modColor ?>33;font-weight:600">
                                <?= $modLabel ?>
                            </span>
                        </td>
                        <td class="py-2 text-center">
                            <span class="badge rounded-pill" style="background:<?= $col ?>22;color:<?= $col ?>;font-size:.75rem;"><?= $row['total'] ?></span>
                        </td>
                        <td class="py-2 text-center" style="color:#9ca3af;"><?= $row['docs'] ?></td>
                        <td class="py-2 small" style="color:#6b7280;">
                            <?= date('d/m/Y H:i', strtotime($row['last_event'])) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filtros avanzados -->
    <div class="hub-card mb-4">
        <div class="hub-card-header">
            <i class="bi bi-funnel" style="color:#f59e0b"></i> Filtros Avanzados
            <?php if ($filterAction||$filterUser||$filterModule||$filterDate): ?>
            <a href="audit.php" class="ms-auto badge text-decoration-none"
               style="background:rgba(239,68,68,.15);color:#ef4444;font-size:.72rem">
                <i class="bi bi-x me-1"></i>Limpiar filtros
            </a>
            <?php endif; ?>
        </div>
        <div class="p-3">
            <form method="get" action="audit.php" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Acción</label>
                    <select name="action" class="form-select form-select-sm bg-dark border-secondary text-light">
                        <option value="">Todas</option>
                        <?php foreach ($actions as $a): ?>
                        <option value="<?= htmlspecialchars($a) ?>" <?= $filterAction===$a?'selected':'' ?>>
                            <?= htmlspecialchars($a) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Módulo</label>
                    <select name="module" class="form-select form-select-sm bg-dark border-secondary text-light">
                        <option value="">Todos</option>
                        <?php foreach ($MODULE_LABELS as $mKey => [$mName]): ?>
                        <option value="<?= $mKey ?>" <?= $filterModule===$mKey?'selected':'' ?>><?= $mName ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Usuario</label>
                    <input type="text" name="user" class="form-control form-control-sm bg-dark border-secondary text-light"
                           placeholder="Buscar usuario..." value="<?= htmlspecialchars($filterUser) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Fecha</label>
                    <input type="date" name="date" class="form-control form-control-sm bg-dark border-secondary text-light"
                           value="<?= htmlspecialchars($filterDate) ?>">
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla de logs -->
    <div class="hub-card">
        <div class="hub-card-header justify-content-between">
            <span>
                <i class="bi bi-list-ul me-2" style="color:#4f8ef7"></i>Eventos de Auditoría
            </span>
            <div class="d-flex align-items-center gap-3">
                <span style="font-size:.75rem;color:#6b7280;font-weight:400;">
                    <?= number_format($total) ?> registro(s)
                    <?php if ($filterModule !== ''): ?>
                    — <?= $MODULE_LABELS[$filterModule][0] ?? $filterModule ?>
                    <?php endif; ?>
                </span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table mb-0" style="font-size:.82rem;">
                <thead style="background:rgba(255,255,255,.03);">
                    <tr style="color:#6b7280;">
                        <th class="px-4 py-3 fw-semibold" style="white-space:nowrap">Fecha</th>
                        <th class="py-3 fw-semibold">Módulo</th>
                        <th class="py-3 fw-semibold">Acción</th>
                        <th class="py-3 fw-semibold">Documento</th>
                        <th class="py-3 fw-semibold">Usuario</th>
                        <th class="py-3 fw-semibold">Cambio</th>
                        <th class="py-3 fw-semibold text-center" style="width:90px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                    <tr><td colspan="7" class="text-center py-5" style="color:#6b7280;">
                        <i class="bi bi-inbox fs-2 d-block mb-2"></i>Sin registros con ese filtro
                    </td></tr>
                    <?php else: ?>
                    <?php foreach ($logs as $log):
                        [$ico, $col] = $ACTION_MAP[$log['action']] ?? ['bi-circle','#6b7280'];
                        $mod = $ACTION_MODULE[$log['action']] ?? null;
                        [$modLabel, $modColor] = $mod ? $MODULE_LABELS[$mod] : ['—','#6b7280'];
                    ?>
                    <tr class="table-row">
                        <td class="px-4 py-2" style="white-space:nowrap;color:#6b7280;font-size:.78rem;">
                            <?= date('d/m/Y', strtotime($log['created_at'])) ?>
                            <div style="font-size:.7rem;color:#4b5563"><?= date('H:i:s', strtotime($log['created_at'])) ?></div>
                        </td>
                        <td class="py-2">
                            <span style="font-size:.68rem;padding:2px 7px;border-radius:99px;
                                         background:<?= $modColor ?>15;color:<?= $modColor ?>;
                                         border:1px solid <?= $modColor ?>30;font-weight:600;white-space:nowrap">
                                <?= $modLabel ?>
                            </span>
                        </td>
                        <td class="py-2">
                            <span style="display:inline-flex;align-items:center;gap:5px;background:<?= $col ?>18;color:<?= $col ?>;padding:3px 9px;border-radius:99px;font-size:.73rem;font-weight:600;white-space:nowrap">
                                <i class="bi <?= $ico ?>"></i><?= htmlspecialchars($log['action']) ?>
                            </span>
                        </td>
                        <td class="py-2">
                            <?php if ($log['document_code']): ?>
                            <span class="font-monospace" style="font-size:.72rem;color:#6b7280"><?= htmlspecialchars($log['document_code']) ?></span>
                            <div style="font-size:.8rem;color:#d1d5db"><?= htmlspecialchars(mb_strimwidth($log['document_title'] ?? '', 0, 30, '…')) ?></div>
                            <?php else: ?><span style="color:#4b5563">—</span><?php endif; ?>
                        </td>
                        <td class="py-2">
                            <div style="display:inline-flex;align-items:center;gap:6px;">
                                <div style="width:22px;height:22px;border-radius:50%;background:linear-gradient(135deg,#4f8ef7,#7c3aed);display:inline-flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:700;color:#fff;flex-shrink:0">
                                    <?= strtoupper(substr($log['username'] ?? 'S', 0, 1)) ?>
                                </div>
                                <span style="color:#c8d0e0;font-size:.82rem"><?= htmlspecialchars($log['username'] ?? 'Sistema') ?></span>
                            </div>
                        </td>
                        <td class="py-2 small" style="color:#6b7280;font-size:.78rem;max-width:200px">
                            <?php if ($log['old_value'] || $log['new_value']): ?>
                            <span style="color:#9ca3af"><?= htmlspecialchars(mb_strimwidth($log['old_value'] ?? '—', 0, 18, '…')) ?></span>
                            <i class="bi bi-arrow-right mx-1" style="color:#4f8ef7;font-size:.62rem"></i>
                            <span style="color:#e0e2ea"><?= htmlspecialchars(mb_strimwidth($log['new_value'] ?? '—', 0, 18, '…')) ?></span>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td class="py-2 text-center" style="white-space:nowrap;">
                            <a href="detalles.php?id=<?= $log['id'] ?>"
                               class="btn btn-sm" title="Ver detalle"
                               style="background:rgba(79,142,247,.12);color:#4f8ef7;border:1px solid rgba(79,142,247,.2);padding:3px 8px;font-size:.73rem;">
                                <i class="bi bi-eye"></i>
                            </a>
                            <?php if (hasMinRole('OPERATOR')): ?>
                            <a href="exportar_csv.php?id=<?= $log['id'] ?>"
                               class="btn btn-sm ms-1" title="Exportar"
                               style="background:rgba(16,185,129,.12);color:#10b981;border:1px solid rgba(16,185,129,.2);padding:3px 8px;font-size:.73rem;">
                                <i class="bi bi-download"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <?php if ($totalPages > 1): ?>
        <div style="padding:12px 18px;border-top:1px solid rgba(255,255,255,.07);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
            <small style="color:#6b7280;">Página <?= $page ?> de <?= $totalPages ?> — <?= number_format($total) ?> registros</small>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $page<=1?'disabled':'' ?>">
                        <a class="page-link bg-dark border-secondary text-light"
                           href="<?= buildPageUrl($page-1,$filterAction,$filterUser,$filterModule,$filterDate) ?>">‹</a>
                    </li>
                    <?php for ($i=max(1,$page-2); $i<=min($totalPages,$page+2); $i++): ?>
                    <li class="page-item <?= $i===$page?'active':'' ?>">
                        <a class="page-link bg-dark border-secondary text-light"
                           href="<?= buildPageUrl($i,$filterAction,$filterUser,$filterModule,$filterDate) ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>">
                        <a class="page-link bg-dark border-secondary text-light"
                           href="<?= buildPageUrl($page+1,$filterAction,$filterUser,$filterModule,$filterDate) ?>">›</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>

    <?php endif; ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('click', e => {
    const sb = document.getElementById('sidebar');
    if (window.innerWidth < 768 && !sb.contains(e.target) && !e.target.closest('[onclick]'))
        sb.classList.remove('open');
});
</script>
</body>
</html>