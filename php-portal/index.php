<?php
require_once 'config.php';

$pgOk     = false;
$searchOk = false;
$stats    = [];
$recentActivity = [];

// Detecta el nombre real de una columna (soporta snake_case y PascalCase)
function resolveColumn(PDO $pdo, string $table, array $candidates): string {
    $stmt = $pdo->prepare(
        "SELECT column_name FROM information_schema.columns
         WHERE table_name = ? AND column_name = ANY(?)"
    );
    $stmt->execute([$table, '{' . implode(',', $candidates) . '}']);
    $found = $stmt->fetchColumn();
    return $found ?: $candidates[0];
}

// Conectar a PostgreSQL y obtener estadísticas
try {
    $pdo  = getPgConnection();
    $pgOk = true;

    // Detecta nombres reales de columnas
    $colAction   = resolveColumn($pdo, 'audit_entries', ['action',       'Action']);
    $colDocId    = resolveColumn($pdo, 'audit_entries', ['document_id',  'DocumentId']);
    $colUserId   = resolveColumn($pdo, 'audit_entries', ['user_id',      'UserId']);
    $colDocCode  = resolveColumn($pdo, 'audit_entries', ['document_code','DocumentCode']);
    $colDocTitle = resolveColumn($pdo, 'audit_entries', ['document_title','DocumentTitle']);
    $colUsername = resolveColumn($pdo, 'audit_entries', ['username',     'Username']);
    $colNewVal   = resolveColumn($pdo, 'audit_entries', ['new_value',    'NewValue']);
    $colCreated  = resolveColumn($pdo, 'audit_entries', ['created_at',   'CreatedAt']);

    // Total de eventos
    $stats['total_events'] = (int) $pdo
        ->query("SELECT COUNT(*) FROM audit_entries")
        ->fetchColumn();

    // Documentos únicos
    $stats['unique_docs'] = (int) $pdo
        ->query("SELECT COUNT(DISTINCT \"{$colDocId}\") FROM audit_entries")
        ->fetchColumn();

    // Usuarios únicos
    $stats['unique_users'] = (int) $pdo
        ->query("SELECT COUNT(DISTINCT \"{$colUserId}\") FROM audit_entries
                 WHERE \"{$colUserId}\" IS NOT NULL")
        ->fetchColumn();

    // Eventos por acción
    $stats['by_action'] = $pdo
        ->query("SELECT \"{$colAction}\" AS action, COUNT(*) AS cnt
                 FROM audit_entries
                 GROUP BY \"{$colAction}\"
                 ORDER BY cnt DESC
                 LIMIT 5")
        ->fetchAll();

    // Actividad reciente
    $recentActivity = $pdo
        ->query("SELECT \"{$colAction}\"    AS action,
                        \"{$colDocCode}\"   AS document_code,
                        \"{$colDocTitle}\"  AS document_title,
                        \"{$colUsername}\"  AS username,
                        \"{$colNewVal}\"    AS new_value,
                        \"{$colCreated}\"   AS created_at
                 FROM audit_entries
                 ORDER BY \"{$colCreated}\" DESC
                 LIMIT 10")
        ->fetchAll();

} catch (Exception $e) {
    $pgError = $e->getMessage();
    $stats   = ['total_events' => 0, 'unique_docs' => 0,
                'unique_users' => 0, 'by_action'   => []];
}

// Verificar microservicio Node.js
$health = callSearchApi('/health');
$searchOk = !empty($health['ok']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= APP_NAME ?> — Panel de Estado</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f0f2f5; }
        .navbar-brand { font-weight: 700; letter-spacing: -.5px; }
        .stat-card { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .status-dot { width:10px;height:10px;border-radius:50%;display:inline-block; }
    </style>
</head>
<body>

<!-- Barra de navegación -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <i class="bi bi-file-earmark-check2 me-2"></i><?= APP_NAME ?>
        </a>
        <div class="navbar-nav ms-auto">
            <a class="nav-link active" href="index.php">
                <i class="bi bi-speedometer2 me-1"></i>Panel
            </a>
            <a class="nav-link" href="documents.php">
                <i class="bi bi-files me-1"></i>Documentos
            </a>
            <a class="nav-link" href="audit.php">
                <i class="bi bi-journal-text me-1"></i>Auditoría
            </a>
        </div>
    </div>
</nav>

<div class="container py-4">

    <!-- Cabecera -->
    <div class="mb-4">
        <h4 class="fw-bold mb-1">Panel de Estado del Sistema</h4>
        <p class="text-muted mb-0">
            Portal de consulta pública — arquitectura polyglot (.NET + Node.js + PHP)
        </p>
    </div>

    <!-- Estado de los servicios -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card stat-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-<?= $pgOk ? 'success' : 'danger' ?> bg-opacity-10
                                p-3 fs-4 text-<?= $pgOk ? 'success' : 'danger' ?>">
                        <i class="bi bi-database"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">PostgreSQL</div>
                        <small class="text-<?= $pgOk ? 'success' : 'danger' ?>">
                            <span class="status-dot bg-<?= $pgOk ? 'success' : 'danger' ?> me-1"></span>
                            <?= $pgOk ? 'Conectado' : 'Sin conexión' ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-<?= $searchOk ? 'success' : 'danger' ?> bg-opacity-10
                                p-3 fs-4 text-<?= $searchOk ? 'success' : 'danger' ?>">
                        <i class="bi bi-search"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">Search Service (Node.js)</div>
                        <small class="text-<?= $searchOk ? 'success' : 'danger' ?>">
                            <span class="status-dot bg-<?= $searchOk ? 'success' : 'danger' ?> me-1"></span>
                            <?= $searchOk ? 'Activo · MongoDB conectado' : 'Sin conexión' ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-info bg-opacity-10 p-3 fs-4 text-info">
                        <i class="bi bi-filetype-php"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">Portal PHP</div>
                        <small class="text-success">
                            <span class="status-dot bg-success me-1"></span>
                            Activo · PHP <?= PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($pgOk): ?>
    <!-- Métricas de auditoría desde PostgreSQL -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card stat-card text-center p-3">
                <div class="display-6 fw-bold text-primary"><?= number_format($stats['total_events']) ?></div>
                <div class="text-muted small mt-1">Eventos de auditoría</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card text-center p-3">
                <div class="display-6 fw-bold text-success"><?= number_format($stats['unique_docs']) ?></div>
                <div class="text-muted small mt-1">Documentos registrados</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card text-center p-3">
                <div class="display-6 fw-bold text-warning"><?= number_format($stats['unique_users']) ?></div>
                <div class="text-muted small mt-1">Usuarios activos</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Actividad reciente -->
        <div class="col-lg-8">
            <div class="card stat-card">
                <div class="card-header bg-white border-bottom fw-semibold">
                    <i class="bi bi-clock-history me-2 text-secondary"></i>
                    Actividad Reciente
                    <small class="text-muted fw-normal ms-2">desde PostgreSQL</small>
                </div>
                <ul class="list-group list-group-flush">
                    <?php if (empty($recentActivity)): ?>
                        <li class="list-group-item text-muted text-center py-4">
                            Sin actividad registrada aún
                        </li>
                    <?php else: ?>
                        <?php foreach ($recentActivity as $log): ?>
                        <li class="list-group-item py-2">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="badge bg-light text-dark border me-2 font-monospace">
                                        <?= htmlspecialchars($log['document_code'] ?? '—') ?>
                                    </span>
                                    <span class="small fw-semibold">
                                        <?= htmlspecialchars($log['action']) ?>
                                    </span>
                                    <?php if ($log['new_value']): ?>
                                        <span class="text-muted small ms-1">
                                            → <?= htmlspecialchars($log['new_value']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted">
                                    <?= date('d/m H:i', strtotime($log['created_at'])) ?>
                                </small>
                            </div>
                            <div class="text-muted" style="font-size:.75rem">
                                <?= htmlspecialchars($log['username'] ?? 'Sistema') ?>
                                <?php if ($log['document_title']): ?>
                                    · <?= htmlspecialchars($log['document_title']) ?>
                                <?php endif; ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- Eventos por tipo -->
        <div class="col-lg-4">
            <div class="card stat-card">
                <div class="card-header bg-white border-bottom fw-semibold">
                    <i class="bi bi-bar-chart me-2 text-primary"></i>
                    Eventos por Tipo
                </div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($stats['by_action'] as $row): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                        <span class="small"><?= htmlspecialchars($row['action']) ?></span>
                        <span class="badge bg-primary rounded-pill"><?= $row['cnt'] ?></span>
                    </li>
                    <?php endforeach; ?>
                    <?php if (empty($stats['by_action'])): ?>
                    <li class="list-group-item text-muted text-center py-3 small">
                        Sin datos aún
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <?php else: ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong>PostgreSQL no disponible.</strong>
        Verifica que el contenedor <code>qualitydoc_postgres</code> esté corriendo.
    </div>
    <?php endif; ?>

    <!-- Arquitectura -->
    <div class="card stat-card mt-4">
        <div class="card-header bg-white border-bottom fw-semibold">
            <i class="bi bi-diagram-3 me-2 text-primary"></i>
            Arquitectura Polyglot
        </div>
        <div class="card-body">
            <div class="row text-center g-3">
                <div class="col-md-3">
                    <div class="p-3 bg-primary bg-opacity-10 rounded-3">
                        <div class="fs-3 mb-1">🌐</div>
                        <div class="fw-semibold small">.NET Core</div>
                        <div class="text-muted" style="font-size:.72rem">App principal<br>C# + MVC</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 bg-warning bg-opacity-10 rounded-3">
                        <div class="fs-3 mb-1">🟨</div>
                        <div class="fw-semibold small">Node.js</div>
                        <div class="text-muted" style="font-size:.72rem">Microservicio<br>Búsqueda + MongoDB</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 bg-info bg-opacity-10 rounded-3">
                        <div class="fs-3 mb-1">🐘</div>
                        <div class="fw-semibold small">PHP</div>
                        <div class="text-muted" style="font-size:.72rem">Portal público<br>+ PostgreSQL</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="p-3 bg-success bg-opacity-10 rounded-3">
                        <div class="fs-3 mb-1">🐳</div>
                        <div class="fw-semibold small">Docker</div>
                        <div class="text-muted" style="font-size:.72rem">Orquestación<br>4 contenedores</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<footer class="text-center text-muted py-3 mt-4" style="font-size:.8rem">
    <?= APP_NAME ?> · Portal PHP · PostgreSQL · <?= date('Y') ?>
</footer>

</body>
</html>