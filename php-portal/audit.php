<?php
// =============================================================================
// QualityDoc Portal — Auditoría (con sidebar compartida)
// =============================================================================
require_once 'config.php';
requireLogin();

$user  = getSessionUser();
$token = getSessionToken();
$role  = $user['role'] ?? 'Viewer';

$page     = max(1, (int)($_GET['page'] ?? 1));
$pageSize = 15;
$offset   = ($page - 1) * $pageSize;

$filterAction = trim($_GET['action'] ?? '');
$filterUser   = trim($_GET['user']   ?? '');

$logs       = [];
$total      = 0;
$actions    = [];
$compliance = [];
$error      = null;
$pgOk       = false;

function resolveCol(PDO $pdo, string $table, array $candidates): string {
    $stmt = $pdo->prepare(
        "SELECT column_name FROM information_schema.columns
         WHERE table_name = ? AND column_name = ANY(?)"
    );
    $stmt->execute([$table, '{' . implode(',', $candidates) . '}']);
    return $stmt->fetchColumn() ?: $candidates[0];
}

try {
    $pdo   = getPgConnection();
    $pgOk  = true;

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

    $where  = [];
    $params = [];
    if ($filterAction !== '') { $where[] = "\"{$cAction}\" = :action"; $params[':action'] = $filterAction; }
    if ($filterUser   !== '') { $where[] = "\"{$cUsername}\" ILIKE :user"; $params[':user'] = '%'.$filterUser.'%'; }
    $whereSQL = $where ? 'WHERE '.implode(' AND ', $where) : '';

    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM audit_entries $whereSQL");
    $stmtCount->execute($params);
    $total = (int)$stmtCount->fetchColumn();

    $stmtLogs = $pdo->prepare(
        "SELECT \"{$cId}\" AS id, \"{$cAction}\" AS action, \"{$cDocCode}\" AS document_code,
                \"{$cDocTitle}\" AS document_title, \"{$cUsername}\" AS username,
                \"{$cOldVal}\" AS old_value, \"{$cNewVal}\" AS new_value,
                \"{$cIp}\" AS ip_address, \"{$cCreated}\" AS created_at
         FROM audit_entries $whereSQL
         ORDER BY \"{$cCreated}\" DESC LIMIT :limit OFFSET :offset"
    );
    foreach ($params as $k => $v) $stmtLogs->bindValue($k, $v);
    $stmtLogs->bindValue(':limit',  $pageSize, PDO::PARAM_INT);
    $stmtLogs->bindValue(':offset', $offset,   PDO::PARAM_INT);
    $stmtLogs->execute();
    $logs = $stmtLogs->fetchAll();

    $compliance = $pdo->query(
        "SELECT \"{$cAction}\" AS action, COUNT(*) AS total,
                COUNT(DISTINCT \"{$cDocId}\") AS docs, MAX(\"{$cCreated}\") AS last_event
         FROM audit_entries GROUP BY \"{$cAction}\" ORDER BY total DESC"
    )->fetchAll();

} catch (Exception $e) {
    $error = $e->getMessage();
}

$totalPages = $total > 0 ? ceil($total / $pageSize) : 1;

function buildPageUrl(int $p, string $action, string $user): string {
    return 'audit.php?' . http_build_query(array_filter(['page'=>$p,'action'=>$action,'user'=>$user]));
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
        .service-badge { display:flex; align-items:center; gap:8px; padding:7px 10px; margin-bottom:2px; border-radius:8px; text-decoration:none; background:rgba(255,255,255,.03); border:1px solid rgba(255,255,255,.06); font-size:.78rem; color:#9ca3af; transition:background .15s,color .15s; }
        .service-badge:hover { background:rgba(255,255,255,.07); color:#d1d5db; }
        .service-dot { width:7px; height:7px; border-radius:50%; flex-shrink:0; }
        .dot-ok  { background:#22c55e; } .dot-off { background:#6b7280; }
        .sidebar-footer { margin-top:auto; padding:14px 12px; border-top:1px solid rgba(255,255,255,.07); }
        #topbar { position:fixed; top:0; left:var(--sidebar-w); right:0; height:var(--topbar-h); background:var(--bg-sidebar); border-bottom:1px solid rgba(255,255,255,.07); display:flex; align-items:center; padding:0 24px; z-index:100; gap:12px; }
        .page-title { font-size:1rem; font-weight:600; color:#e0e2ea; }
        #main { margin-left:var(--sidebar-w); margin-top:var(--topbar-h); padding:28px 28px 40px; min-height:calc(100vh - var(--topbar-h)); }
        .hub-card { background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08); border-radius:12px; overflow:hidden; }
        .hub-card-header { padding:14px 18px; border-bottom:1px solid rgba(255,255,255,.07); display:flex; align-items:center; gap:8px; font-size:.85rem; font-weight:600; color:#c8d0e0; }
        @media(max-width:768px){ #sidebar{transform:translateX(-100%);} #sidebar.open{transform:translateX(0);} #topbar,#main{left:0;margin-left:0;} }
    </style>
</head>
<body>
<?php require_once '_sidebar.php'; ?>

<main id="main">

    <div class="mb-4">
        <h5 class="fw-bold text-white mb-1">Registro de Auditoría</h5>
        <p class="text-muted small mb-0">Datos en tiempo real desde <strong>PostgreSQL</strong> <code>(qualitydoc_audit)</code></p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger border-0" style="background:rgba(239,68,68,.1);color:#fca5a5;">
        <i class="bi bi-x-circle me-2"></i>
        Error de conexión a PostgreSQL: <code><?= htmlspecialchars($error) ?></code>
        <br><small>Verifica que el contenedor <code>qualitydoc_postgres</code> esté corriendo.</small>
    </div>
    <?php else: ?>

    <!-- Resumen por tipo -->
    <?php if (!empty($compliance)): ?>
    <div class="hub-card mb-4">
        <div class="hub-card-header">
            <i class="bi bi-clipboard-data" style="color:#8b5cf6"></i> Resumen por Tipo de Evento
        </div>
        <div class="table-responsive">
            <table class="table mb-0" style="font-size:.82rem;">
                <thead style="background:rgba(255,255,255,.03);">
                    <tr style="color:#6b7280;">
                        <th class="px-4 py-3 fw-semibold">Acción</th>
                        <th class="py-3 text-center fw-semibold">Total</th>
                        <th class="py-3 text-center fw-semibold">Documentos</th>
                        <th class="py-3 fw-semibold">Último Evento</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $colors = ['#4f8ef7','#10b981','#f59e0b','#8b5cf6','#ec4899','#14b8a6'];
                    foreach ($compliance as $i => $row):
                        $c = $colors[$i % count($colors)];
                    ?>
                    <tr style="border-color:rgba(255,255,255,.05);">
                        <td class="px-4 py-2">
                            <span class="fw-semibold" style="color:#e0e2ea;"><?= htmlspecialchars($row['action']) ?></span>
                        </td>
                        <td class="py-2 text-center">
                            <span class="badge rounded-pill" style="background:<?= $c ?>22;color:<?= $c ?>;font-size:.75rem;"><?= $row['total'] ?></span>
                        </td>
                        <td class="py-2 text-center" style="color:#9ca3af;"><?= $row['docs'] ?></td>
                        <td class="py-2 small" style="color:#6b7280;"><?= date('d/m/Y H:i', strtotime($row['last_event'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="hub-card mb-4">
        <div class="hub-card-header">
            <i class="bi bi-funnel" style="color:#f59e0b"></i> Filtros
        </div>
        <div class="p-3">
            <form method="get" action="audit.php" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold mb-1" style="color:#9ca3af;">Acción</label>
                    <select name="action" class="form-select form-select-sm bg-dark border-secondary text-light">
                        <option value="">Todas las acciones</option>
                        <?php foreach ($actions as $a): ?>
                        <option value="<?= htmlspecialchars($a) ?>" <?= $filterAction === $a ? 'selected' : '' ?>>
                            <?= htmlspecialchars($a) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold mb-1" style="color:#9ca3af;">Usuario</label>
                    <input type="text" name="user" class="form-control form-control-sm bg-dark border-secondary text-light"
                           placeholder="Buscar usuario..." value="<?= htmlspecialchars($filterUser) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-funnel me-1"></i>Filtrar
                    </button>
                </div>
                <div class="col-md-2">
                    <a href="audit.php" class="btn btn-sm w-100" style="background:rgba(255,255,255,.06);color:#9ca3af;border:1px solid rgba(255,255,255,.1);">
                        <i class="bi bi-x me-1"></i>Limpiar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla de logs -->
    <div class="hub-card">
        <div class="hub-card-header justify-content-between">
            <span><i class="bi bi-list-ul me-2" style="color:#4f8ef7"></i>Eventos de Auditoría</span>
            <div class="d-flex align-items-center gap-3">
                <span style="font-size:.75rem;color:#6b7280;font-weight:400;"><?= number_format($total) ?> registros</span>
                <?php if (in_array($role, ['Admin','Manager'])): ?>
                <a href="exportar_csv.php<?= ($filterAction||$filterUser) ? '?'.http_build_query(array_filter(['action'=>$filterAction,'user'=>$filterUser])) : '' ?>"
                   class="btn btn-sm" style="background:rgba(16,185,129,.15);color:#10b981;border:1px solid rgba(16,185,129,.3);font-size:.75rem;padding:3px 10px;">
                    <i class="bi bi-download me-1"></i>Exportar CSV
                </a>
                <?php endif; ?>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table mb-0" style="font-size:.82rem;">
                <thead style="background:rgba(255,255,255,.03);">
                    <tr style="color:#6b7280;">
                        <th class="px-4 py-3 fw-semibold">Fecha</th>
                        <th class="py-3 fw-semibold">Acción</th>
                        <th class="py-3 fw-semibold">Documento</th>
                        <th class="py-3 fw-semibold">Usuario</th>
                        <th class="py-3 fw-semibold">Cambio</th>
                        <th class="py-3 fw-semibold">IP</th>
                        <th class="py-3 fw-semibold text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                    <tr><td colspan="7" class="text-center py-5" style="color:#6b7280;">
                        <i class="bi bi-inbox fs-2 d-block mb-2"></i>Sin registros con ese filtro
                    </td></tr>
                    <?php else: ?>
                    <?php
                    $actionMap = [
                        'Created'=>['bi-plus-circle-fill','#10b981'],
                        'Updated'=>['bi-pencil-fill','#f59e0b'],
                        'StatusChanged'=>['bi-arrow-repeat','#4f8ef7'],
                        'Approved'=>['bi-check-circle-fill','#10b981'],
                        'Rejected'=>['bi-x-circle-fill','#ef4444'],
                        'Downloaded'=>['bi-download','#14b8a6'],
                        'ApprovalAdded'=>['bi-person-check-fill','#4f8ef7'],
                        'ApprovalReviewed'=>['bi-patch-check-fill','#10b981'],
                    ];
                    foreach ($logs as $log):
                        [$ico, $col] = $actionMap[$log['action']] ?? ['bi-circle','#6b7280'];
                    ?>
                    <tr style="border-color:rgba(255,255,255,.05);">
                        <td class="px-4 py-2" style="white-space:nowrap;color:#6b7280;">
                            <?= date('d/m/Y H:i', strtotime($log['created_at'])) ?>
                        </td>
                        <td class="py-2">
                            <span style="display:inline-flex;align-items:center;gap:5px;background:<?= $col ?>18;color:<?= $col ?>;padding:3px 9px;border-radius:99px;font-size:.75rem;font-weight:600;">
                                <i class="bi <?= $ico ?>"></i><?= htmlspecialchars($log['action']) ?>
                            </span>
                        </td>
                        <td class="py-2">
                            <?php if ($log['document_code']): ?>
                            <span class="font-monospace small me-1" style="color:#6b7280;"><?= htmlspecialchars($log['document_code']) ?></span>
                            <span style="color:#d1d5db;"><?= htmlspecialchars(mb_strimwidth($log['document_title'] ?? '', 0, 28, '…')) ?></span>
                            <?php else: ?><span style="color:#6b7280;">—</span><?php endif; ?>
                        </td>
                        <td class="py-2" style="color:#c8d0e0;"><?= htmlspecialchars($log['username'] ?? 'Sistema') ?></td>
                        <td class="py-2 small" style="color:#6b7280;">
                            <?php if ($log['old_value'] || $log['new_value']): ?>
                                <?= htmlspecialchars(mb_strimwidth($log['old_value'] ?? '—', 0, 20, '…')) ?>
                                <i class="bi bi-arrow-right mx-1" style="color:#4f8ef7;font-size:.65rem;"></i>
                                <?= htmlspecialchars(mb_strimwidth($log['new_value'] ?? '—', 0, 20, '…')) ?>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td class="py-2 small" style="color:#6b7280;"><?= htmlspecialchars($log['ip_address'] ?? '—') ?></td>
                        <td class="py-2 text-center" style="white-space:nowrap;">
                            <a href="detalles.php?id=<?= $log['id'] ?>"
                               class="btn btn-sm" title="Ver detalle"
                               style="background:rgba(79,142,247,.12);color:#4f8ef7;border:1px solid rgba(79,142,247,.2);padding:3px 8px;font-size:.75rem;">
                                <i class="bi bi-eye"></i>
                            </a>
                            <?php if (in_array($role, ['Admin','Manager'])): ?>
                            <a href="exportar_csv.php?id=<?= $log['id'] ?>"
                               class="btn btn-sm ms-1" title="Exportar este log"
                               style="background:rgba(16,185,129,.12);color:#10b981;border:1px solid rgba(16,185,129,.2);padding:3px 8px;font-size:.75rem;">
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
        <div style="padding:12px 18px;border-top:1px solid rgba(255,255,255,.07);display:flex;justify-content:space-between;align-items:center;">
            <small style="color:#6b7280;">Página <?= $page ?> de <?= $totalPages ?></small>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $page<=1?'disabled':'' ?>">
                        <a class="page-link bg-dark border-secondary text-light" href="<?= buildPageUrl($page-1,$filterAction,$filterUser) ?>">‹</a>
                    </li>
                    <?php for ($i=max(1,$page-2); $i<=min($totalPages,$page+2); $i++): ?>
                    <li class="page-item <?= $i===$page?'active':'' ?>">
                        <a class="page-link bg-dark border-secondary text-light" href="<?= buildPageUrl($i,$filterAction,$filterUser) ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>">
                        <a class="page-link bg-dark border-secondary text-light" href="<?= buildPageUrl($page+1,$filterAction,$filterUser) ?>">›</a>
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
