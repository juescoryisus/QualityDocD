<?php
require_once 'config.php';

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

// Detecta el nombre real de una columna (soporta snake_case y PascalCase)
function resolveCol(PDO $pdo, string $table, array $candidates): string {
    $stmt = $pdo->prepare(
        "SELECT column_name FROM information_schema.columns
         WHERE table_name = ? AND column_name = ANY(?)"
    );
    $stmt->execute([$table, '{' . implode(',', $candidates) . '}']);
    $found = $stmt->fetchColumn();
    return $found ?: $candidates[0];
}

try {
    $pdo = getPgConnection();

    // Detecta nombres de columnas reales
    $cAction   = resolveCol($pdo, 'audit_entries', ['action',        'Action']);
    $cDocId    = resolveCol($pdo, 'audit_entries', ['document_id',   'DocumentId']);
    $cDocCode  = resolveCol($pdo, 'audit_entries', ['document_code', 'DocumentCode']);
    $cDocTitle = resolveCol($pdo, 'audit_entries', ['document_title','DocumentTitle']);
    $cUsername = resolveCol($pdo, 'audit_entries', ['username',      'Username']);
    $cOldVal   = resolveCol($pdo, 'audit_entries', ['old_value',     'OldValue']);
    $cNewVal   = resolveCol($pdo, 'audit_entries', ['new_value',     'NewValue']);
    $cIp       = resolveCol($pdo, 'audit_entries', ['ip_address',    'IpAddress']);
    $cCreated  = resolveCol($pdo, 'audit_entries', ['created_at',    'CreatedAt']);

    // Acciones disponibles para filtro
    $actions = $pdo
        ->query("SELECT DISTINCT \"{$cAction}\" FROM audit_entries ORDER BY \"{$cAction}\"")
        ->fetchAll(PDO::FETCH_COLUMN);

    // Filtros dinámicos
    $where  = [];
    $params = [];

    if ($filterAction !== '') {
        $where[]           = "\"{$cAction}\" = :action";
        $params[':action'] = $filterAction;
    }
    if ($filterUser !== '') {
        $where[]        = "\"{$cUsername}\" ILIKE :user";
        $params[':user'] = '%' . $filterUser . '%';
    }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // Total con filtros
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM audit_entries $whereSQL");
    $stmtCount->execute($params);
    $total = (int) $stmtCount->fetchColumn();

    // Logs paginados
    $stmtLogs = $pdo->prepare(
        "SELECT \"{$cAction}\"   AS action,
                \"{$cDocCode}\"  AS document_code,
                \"{$cDocTitle}\" AS document_title,
                \"{$cUsername}\" AS username,
                \"{$cOldVal}\"   AS old_value,
                \"{$cNewVal}\"   AS new_value,
                \"{$cIp}\"       AS ip_address,
                \"{$cCreated}\"  AS created_at
         FROM audit_entries
         $whereSQL
         ORDER BY \"{$cCreated}\" DESC
         LIMIT :limit OFFSET :offset"
    );
    foreach ($params as $k => $v) $stmtLogs->bindValue($k, $v);
    $stmtLogs->bindValue(':limit',  $pageSize, PDO::PARAM_INT);
    $stmtLogs->bindValue(':offset', $offset,   PDO::PARAM_INT);
    $stmtLogs->execute();
    $logs = $stmtLogs->fetchAll();

    // Resumen por tipo de acción
    $compliance = $pdo
        ->query("SELECT \"{$cAction}\" AS action,
                        COUNT(*) AS total,
                        COUNT(DISTINCT \"{$cDocId}\") AS docs,
                        MAX(\"{$cCreated}\") AS last_event
                 FROM audit_entries
                 GROUP BY \"{$cAction}\"
                 ORDER BY total DESC")
        ->fetchAll();

} catch (Exception $e) {
    $error = $e->getMessage();
}

$totalPages = $total > 0 ? ceil($total / $pageSize) : 1;

function buildPageUrl(int $p, string $action, string $user): string {
    $params = array_filter(['page' => $p, 'action' => $action, 'user' => $user]);
    return 'audit.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registro de Auditoría — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f0f2f5; }
        .navbar-brand { font-weight: 700; letter-spacing: -.5px; }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .table th { font-size: .78rem; text-transform: uppercase;
                    letter-spacing: .04em; color: #6c757d; }
        .table td { font-size: .85rem; vertical-align: middle; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <i class="bi bi-file-earmark-check2 me-2"></i><?= APP_NAME ?>
        </a>
        <div class="navbar-nav ms-auto">
            <a class="nav-link" href="index.php">
                <i class="bi bi-speedometer2 me-1"></i>Panel
            </a>
            <a class="nav-link" href="documents.php">
                <i class="bi bi-files me-1"></i>Documentos
            </a>
            <a class="nav-link active" href="audit.php">
                <i class="bi bi-journal-text me-1"></i>Auditoría
            </a>
        </div>
    </div>
</nav>

<div class="container py-4">

    <div class="mb-4">
        <h4 class="fw-bold mb-1">Registro de Auditoría</h4>
        <p class="text-muted mb-0">
            Datos leídos directamente desde <strong>PostgreSQL</strong>
            <code>(qualitydoc_audit)</code>
        </p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="bi bi-x-circle me-2"></i>
        Error de conexión a PostgreSQL: <code><?= htmlspecialchars($error) ?></code>
        <br><small>Verifica que el contenedor <code>qualitydoc_postgres</code> esté corriendo.</small>
    </div>
    <?php else: ?>

    <!-- Resumen de cumplimiento -->
    <?php if (!empty($compliance)): ?>
    <div class="card mb-4">
        <div class="card-header bg-white border-bottom fw-semibold">
            <i class="bi bi-clipboard-data me-2 text-primary"></i>
            Resumen por Tipo de Evento
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Acción</th>
                        <th class="text-center">Total Eventos</th>
                        <th class="text-center">Documentos Únicos</th>
                        <th>Último Evento</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($compliance as $row): ?>
                    <tr>
                        <td>
                            <span class="fw-semibold"><?= htmlspecialchars($row['action']) ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-primary rounded-pill"><?= $row['total'] ?></span>
                        </td>
                        <td class="text-center text-muted"><?= $row['docs'] ?></td>
                        <td class="text-muted small">
                            <?= date('d/m/Y H:i', strtotime($row['last_event'])) ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body py-3">
            <form method="get" action="audit.php" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small fw-semibold mb-1">Acción</label>
                    <select name="action" class="form-select form-select-sm">
                        <option value="">Todas las acciones</option>
                        <?php foreach ($actions as $a): ?>
                        <option value="<?= htmlspecialchars($a) ?>"
                            <?= $filterAction === $a ? 'selected' : '' ?>>
                            <?= htmlspecialchars($a) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold mb-1">Usuario</label>
                    <input type="text" name="user" class="form-control form-control-sm"
                           placeholder="Buscar por usuario..."
                           value="<?= htmlspecialchars($filterUser) ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-funnel me-1"></i>Filtrar
                    </button>
                </div>
                <div class="col-md-2">
                    <a href="audit.php" class="btn btn-outline-secondary btn-sm w-100">
                        <i class="bi bi-x me-1"></i>Limpiar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla de logs -->
    <div class="card">
        <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center">
            <span class="fw-semibold">
                <i class="bi bi-list-ul me-2 text-secondary"></i>
                Eventos de Auditoría
            </span>
            <small class="text-muted"><?= number_format($total) ?> registros</small>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Fecha</th>
                        <th>Acción</th>
                        <th>Documento</th>
                        <th>Usuario</th>
                        <th>Cambio</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            Sin registros que coincidan con el filtro
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td class="text-muted" style="white-space:nowrap">
                            <?= date('d/m/Y H:i', strtotime($log['created_at'])) ?>
                        </td>
                        <td>
                            <?php
                            $actionColors = [
                                'Created'      => 'success',
                                'Updated'      => 'warning',
                                'StatusChange' => 'primary',
                                'Approved'     => 'success',
                                'Rejected'     => 'danger',
                                'Obsolete'     => 'secondary',
                                'Downloaded'   => 'info',
                            ];
                            $color = $actionColors[$log['action']] ?? 'dark';
                            ?>
                            <span class="badge bg-<?= $color ?> bg-opacity-10
                                              text-<?= $color ?> border border-<?= $color ?>
                                              border-opacity-25">
                                <?= htmlspecialchars($log['action']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($log['document_code']): ?>
                            <span class="font-monospace small text-muted me-1">
                                <?= htmlspecialchars($log['document_code']) ?>
                            </span>
                            <span class="small">
                                <?= htmlspecialchars(
                                    mb_strimwidth($log['document_title'] ?? '', 0, 30, '…')
                                ) ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="small">
                            <?= htmlspecialchars($log['username'] ?? 'Sistema') ?>
                        </td>
                        <td class="small text-muted">
                            <?php if ($log['old_value'] || $log['new_value']): ?>
                                <?= htmlspecialchars($log['old_value'] ?? '—') ?>
                                <i class="bi bi-arrow-right mx-1"></i>
                                <?= htmlspecialchars($log['new_value'] ?? '—') ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted">
                            <?= htmlspecialchars($log['ip_address'] ?? '—') ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Paginación -->
        <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
            <small class="text-muted">
                Página <?= $page ?> de <?= $totalPages ?>
            </small>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link"
                           href="<?= buildPageUrl($page - 1, $filterAction, $filterUser) ?>">
                            ‹
                        </a>
                    </li>
                    <?php
                    $start = max(1, $page - 2);
                    $end   = min($totalPages, $page + 2);
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link"
                           href="<?= buildPageUrl($i, $filterAction, $filterUser) ?>">
                            <?= $i ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link"
                           href="<?= buildPageUrl($page + 1, $filterAction, $filterUser) ?>">
                            ›
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>

    <?php endif; ?>
</div>

<footer class="text-center text-muted py-3 mt-4" style="font-size:.8rem">
    Datos desde PostgreSQL <code>(qualitydoc_audit)</code> · <?= APP_NAME ?>
</footer>

</body>
</html>