<?php
// =============================================================================
// QualityDoc Portal — Detalle de entrada de auditoría
// =============================================================================
require_once 'config.php';
requireLogin();

$user = getSessionUser();
$role = $user['role'] ?? 'Viewer';

$id    = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$log   = null;
$error = null;

function resolveCol(PDO $pdo, string $table, array $candidates): string {
    $stmt = $pdo->prepare(
        "SELECT column_name FROM information_schema.columns
         WHERE table_name = ? AND column_name = ANY(?)"
    );
    $stmt->execute([$table, '{' . implode(',', $candidates) . '}']);
    return $stmt->fetchColumn() ?: $candidates[0];
}

if ($id > 0) {
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

        $stmt = $pdo->prepare(
            "SELECT \"{$cId}\" AS id, \"{$cAction}\" AS action,
                    \"{$cDocId}\" AS document_id, \"{$cDocCode}\" AS document_code,
                    \"{$cDocTitle}\" AS document_title, \"{$cUsername}\" AS username,
                    \"{$cOldVal}\" AS old_value, \"{$cNewVal}\" AS new_value,
                    \"{$cIp}\" AS ip_address, \"{$cCreated}\" AS created_at
             FROM audit_entries WHERE \"{$cId}\" = :id"
        );
        $stmt->execute([':id' => $id]);
        $log = $stmt->fetch();

        if (!$log) {
            $error = "No se encontró el registro de auditoría con ID {$id}.";
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
} else {
    $error = 'ID de registro inválido.';
}

$actionMap = [
    'Created'          => ['bi-plus-circle-fill',  '#10b981'],
    'Updated'          => ['bi-pencil-fill',        '#f59e0b'],
    'StatusChanged'    => ['bi-arrow-repeat',       '#4f8ef7'],
    'Approved'         => ['bi-check-circle-fill',  '#10b981'],
    'Rejected'         => ['bi-x-circle-fill',      '#ef4444'],
    'Downloaded'       => ['bi-download',           '#14b8a6'],
    'ApprovalAdded'    => ['bi-person-check-fill',  '#4f8ef7'],
    'ApprovalReviewed' => ['bi-patch-check-fill',   '#10b981'],
];

$pgOk = ($log !== null);
$currentPage = 'audit';
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Detalle de Auditoría — <?= APP_NAME ?></title>
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
        .sidebar-footer { margin-top:auto; padding:14px 12px; border-top:1px solid rgba(255,255,255,.07); }
        #topbar { position:fixed; top:0; left:var(--sidebar-w); right:0; height:var(--topbar-h); background:var(--bg-sidebar); border-bottom:1px solid rgba(255,255,255,.07); display:flex; align-items:center; padding:0 24px; z-index:100; gap:12px; }
        .page-title { font-size:1rem; font-weight:600; color:#e0e2ea; }
        #main { margin-left:var(--sidebar-w); margin-top:var(--topbar-h); padding:28px 28px 40px; min-height:calc(100vh - var(--topbar-h)); }
        .hub-card { background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08); border-radius:12px; overflow:hidden; }
        .hub-card-header { padding:14px 18px; border-bottom:1px solid rgba(255,255,255,.07); display:flex; align-items:center; gap:8px; font-size:.85rem; font-weight:600; color:#c8d0e0; }
        .detail-row { display:flex; border-bottom:1px solid rgba(255,255,255,.05); }
        .detail-label { width:200px; min-width:200px; padding:13px 18px; font-size:.8rem; font-weight:600; color:#6b7280; background:rgba(255,255,255,.02); text-transform:uppercase; letter-spacing:.05em; }
        .detail-value { flex:1; padding:13px 18px; font-size:.87rem; color:#d1d5db; word-break:break-all; }
        pre.json-block { background:#0a0c10; border:1px solid rgba(255,255,255,.07); border-radius:8px; padding:14px; font-size:.78rem; color:#a5f3fc; margin:0; white-space:pre-wrap; word-break:break-all; max-height:300px; overflow-y:auto; }
        @media(max-width:768px){ #sidebar{transform:translateX(-100%);} #topbar,#main{left:0;margin-left:0;} }
    </style>
</head>
<body>
<?php require_once '_sidebar.php'; ?>

<div id="topbar">
    <span class="page-title"><i class="bi bi-shield-check me-2"></i>Detalle de Auditoría</span>
    <div class="ms-auto d-flex gap-2">
        <a href="audit.php" class="btn btn-sm" style="background:rgba(255,255,255,.06);color:#9ca3af;border:1px solid rgba(255,255,255,.1);">
            <i class="bi bi-arrow-left me-1"></i>Volver a Auditoría
        </a>
    </div>
</div>

<main id="main">

<?php if ($error): ?>
    <div class="alert alert-danger border-0" style="background:rgba(239,68,68,.1);color:#fca5a5;">
        <i class="bi bi-x-circle me-2"></i><?= htmlspecialchars($error) ?>
    </div>
<?php else:
    [$ico, $col] = $actionMap[$log['action']] ?? ['bi-circle', '#6b7280'];
    $newVal = $log['new_value'];
    $oldVal = $log['old_value'];
    $newJson = null;
    $oldJson = null;
    if ($newVal) { $decoded = json_decode($newVal, true); if (json_last_error() === JSON_ERROR_NONE) $newJson = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); }
    if ($oldVal) { $decoded = json_decode($oldVal, true); if (json_last_error() === JSON_ERROR_NONE) $oldJson = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); }
?>

    <div class="mb-4 d-flex align-items-center gap-3">
        <span style="display:inline-flex;align-items:center;gap:7px;background:<?= $col ?>18;color:<?= $col ?>;padding:6px 14px;border-radius:99px;font-size:.85rem;font-weight:700;">
            <i class="bi <?= $ico ?> fs-6"></i><?= htmlspecialchars($log['action']) ?>
        </span>
        <span style="color:#6b7280;font-size:.82rem;">ID #<?= $log['id'] ?></span>
    </div>

    <!-- Datos principales -->
    <div class="hub-card mb-4">
        <div class="hub-card-header">
            <i class="bi bi-info-circle" style="color:#4f8ef7"></i> Información del Evento
        </div>
        <div class="detail-row">
            <div class="detail-label">Fecha / Hora</div>
            <div class="detail-value"><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Acción</div>
            <div class="detail-value">
                <span style="color:<?= $col ?>;font-weight:600;"><?= htmlspecialchars($log['action']) ?></span>
            </div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Usuario</div>
            <div class="detail-value">
                <i class="bi bi-person-circle me-1" style="color:#4f8ef7"></i>
                <?= htmlspecialchars($log['username'] ?? '—') ?>
            </div>
        </div>
        <div class="detail-row">
            <div class="detail-label">IP de Origen</div>
            <div class="detail-value">
                <span class="font-monospace" style="color:#a5f3fc;"><?= htmlspecialchars($log['ip_address'] ?? '—') ?></span>
            </div>
        </div>
        <div class="detail-row">
            <div class="detail-label">Código Documento</div>
            <div class="detail-value">
                <span class="font-monospace"><?= htmlspecialchars($log['document_code'] ?? '—') ?></span>
            </div>
        </div>
        <div class="detail-row" style="border-bottom:none;">
            <div class="detail-label">Título Documento</div>
            <div class="detail-value"><?= htmlspecialchars($log['document_title'] ?? '—') ?></div>
        </div>
    </div>

    <!-- Payload de cambios -->
    <div class="row g-4">
        <?php if ($newVal): ?>
        <div class="col-md-<?= $oldVal ? '6' : '12' ?>">
            <div class="hub-card">
                <div class="hub-card-header">
                    <i class="bi bi-arrow-down-circle" style="color:#10b981"></i> Valores Nuevos (new_value)
                </div>
                <div class="p-3">
                    <pre class="json-block"><?= htmlspecialchars($newJson ?? $newVal) ?></pre>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($oldVal): ?>
        <div class="col-md-<?= $newVal ? '6' : '12' ?>">
            <div class="hub-card">
                <div class="hub-card-header">
                    <i class="bi bi-arrow-up-circle" style="color:#f59e0b"></i> Valores Anteriores (old_value)
                </div>
                <div class="p-3">
                    <pre class="json-block"><?= htmlspecialchars($oldJson ?? $oldVal) ?></pre>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!$newVal && !$oldVal): ?>
        <div class="col-12">
            <div class="hub-card">
                <div class="p-4 text-center" style="color:#6b7280;">
                    <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                    Este evento no registró payload de datos.
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Botón enviar correo -->
    <div class="mt-4 d-flex gap-2">
        <a href="audit.php" class="btn btn-sm" style="background:rgba(255,255,255,.06);color:#9ca3af;border:1px solid rgba(255,255,255,.1);">
            <i class="bi bi-arrow-left me-1"></i>Volver
        </a>
        <button class="btn btn-sm" style="background:rgba(79,142,247,.15);color:#4f8ef7;border:1px solid rgba(79,142,247,.3);"
                onclick="enviarCorreo(<?= $log['id'] ?>)" id="btn-correo">
            <i class="bi bi-envelope me-1"></i>Enviar por correo
        </button>
        <a href="exportar_csv.php?id=<?= $log['id'] ?>" class="btn btn-sm" style="background:rgba(16,185,129,.15);color:#10b981;border:1px solid rgba(16,185,129,.3);">
            <i class="bi bi-download me-1"></i>Exportar este log
        </a>
    </div>

    <!-- Modal correo -->
    <div class="modal fade" id="modalCorreo" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background:#1a1d26;border:1px solid rgba(255,255,255,.1);">
                <div class="modal-header border-0">
                    <h6 class="modal-title text-white"><i class="bi bi-envelope me-2"></i>Enviar Reporte por Correo</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label small fw-semibold mb-1" style="color:#9ca3af;">Correo destinatario</label>
                    <input type="email" id="correo-destino" class="form-control bg-dark border-secondary text-light"
                           placeholder="auditor@empresa.com">
                    <div id="correo-msg" class="mt-2 small"></div>
                </div>
                <div class="modal-footer border-0">
                    <button class="btn btn-sm" style="background:rgba(255,255,255,.06);color:#9ca3af;border:1px solid rgba(255,255,255,.1);"
                            data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-sm" style="background:#4f8ef7;color:#fff;border:none;"
                            id="btn-enviar">
                        <i class="bi bi-send me-1"></i>Enviar
                    </button>
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
let logId = null;
const modal = new bootstrap.Modal(document.getElementById('modalCorreo'));

function enviarCorreo(id) {
    logId = id;
    document.getElementById('correo-destino').value = '';
    document.getElementById('correo-msg').innerHTML = '';
    modal.show();
}

document.getElementById('btn-enviar')?.addEventListener('click', async () => {
    const email = document.getElementById('correo-destino').value.trim();
    const msg   = document.getElementById('correo-msg');
    if (!email) { msg.innerHTML = '<span style="color:#ef4444">Ingresa un correo válido.</span>'; return; }

    document.getElementById('btn-enviar').disabled = true;
    msg.innerHTML = '<span style="color:#6b7280"><i class="bi bi-hourglass-split me-1"></i>Enviando...</span>';

    try {
        const res  = await fetch('api/enviar_detalle_correo.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ log_id: logId, email }),
        });
        const data = await res.json();
        if (data.ok) {
            msg.innerHTML = '<span style="color:#10b981"><i class="bi bi-check-circle me-1"></i>' + data.message + '</span>';
            setTimeout(() => modal.hide(), 2000);
        } else {
            msg.innerHTML = '<span style="color:#ef4444"><i class="bi bi-x-circle me-1"></i>' + (data.error ?? 'Error desconocido') + '</span>';
        }
    } catch (e) {
        msg.innerHTML = '<span style="color:#ef4444">Error de red.</span>';
    } finally {
        document.getElementById('btn-enviar').disabled = false;
    }
});
</script>
</body>
</html>
