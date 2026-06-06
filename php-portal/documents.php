<?php
// =============================================================================
// QualityDoc Portal — Documentos (con sidebar compartida)
// =============================================================================
require_once 'config.php';
requireLogin();

$user  = getSessionUser();
$token = getSessionToken();
$role  = $user['role'] ?? 'Viewer';
$pgOk  = false;

// Verificar PostgreSQL (para el indicador del topbar)
try { getPgConnection(); $pgOk = true; } catch (Exception $e) {}

$query    = trim($_GET['q']        ?? '');
$category = trim($_GET['category'] ?? '');
$results  = [];
$total    = 0;
$error    = null;

$categoriesData = callSearchApi('/api/categories');
$categories     = $categoriesData['categories'] ?? [];

if ($query !== '' || $category !== '') {
    $path = '/api/search?q=' . urlencode($query);
    if ($category !== '') $path .= '&category=' . urlencode($category);
    $path .= '&status=Approved';
    $data    = callSearchApi($path);
    $results = $data['results'] ?? [];
    $total   = $data['total']   ?? count($results);
    if (empty($data) && ($query !== '' || $category !== ''))
        $error = 'El servicio de búsqueda no está disponible.';
} else {
    $data    = callSearchApi('/api/search?q=&status=Approved');
    $results = $data['results'] ?? [];
    $total   = $data['total']   ?? count($results);
}

$currentPage = 'documents';
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Documentos — <?= APP_NAME ?></title>
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
        .doc-card { background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08); border-radius:12px; padding:18px; transition:border-color .2s, transform .2s; height:100%; }
        .doc-card:hover { border-color:var(--accent); transform:translateY(-2px); }
        .tag-pill { font-size:.68rem; background:rgba(255,255,255,.06); color:#9ca3af; border:1px solid rgba(255,255,255,.08); border-radius:99px; padding:2px 8px; }
        @media(max-width:768px){ #sidebar{transform:translateX(-100%);} #sidebar.open{transform:translateX(0);} #topbar,#main{left:0;margin-left:0;} }
    </style>
</head>
<body>
<?php require_once '_sidebar.php'; ?>

<main id="main">

    <div class="mb-4">
        <h5 class="fw-bold text-white mb-1">Documentos Aprobados</h5>
        <p class="text-muted small mb-0">Búsqueda indexada en <strong>MongoDB</strong> via microservicio Node.js</p>
    </div>

    <!-- Búsqueda -->
    <div class="hub-card mb-4">
        <div class="hub-card-header">
            <i class="bi bi-search" style="color:#10b981"></i> Buscar Documentos
        </div>
        <div class="p-3">
            <form method="get" action="documents.php" class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold mb-1" style="color:#9ca3af;">Búsqueda</label>
                    <div class="input-group">
                        <span class="input-group-text bg-dark border-secondary" style="color:#6b7280;">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" name="q" class="form-control bg-dark border-secondary text-light"
                               placeholder="Título, código, descripción, etiquetas..."
                               value="<?= htmlspecialchars($query) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold mb-1" style="color:#9ca3af;">Categoría</label>
                    <select name="category" class="form-select bg-dark border-secondary text-light">
                        <option value="">Todas las categorías</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>" <?= $category===$cat?'selected':'' ?>>
                            <?= htmlspecialchars($cat) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="bi bi-search me-1"></i>Buscar
                    </button>
                    <?php if ($query || $category): ?>
                    <a href="documents.php" class="btn" style="background:rgba(255,255,255,.06);color:#9ca3af;border:1px solid rgba(255,255,255,.1);" title="Limpiar">
                        <i class="bi bi-x"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="alert border-0 mb-4" style="background:rgba(245,158,11,.1);color:#fcd34d;">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <?= htmlspecialchars($error) ?>
        <br><small>Verifica que los contenedores Docker estén corriendo.</small>
    </div>
    <?php endif; ?>

    <!-- Encabezado resultados -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <span style="font-size:.82rem;color:#6b7280;">
            <i class="bi bi-files me-1"></i>
            <?= $total ?> documento<?= $total!==1?'s':'' ?> encontrado<?= $total!==1?'s':'' ?>
        </span>
        <?php if ($query || $category): ?>
        <div style="font-size:.78rem;color:#9ca3af;">
            <?php if ($query): ?>
            <span style="background:rgba(79,142,247,.15);color:#4f8ef7;padding:2px 8px;border-radius:99px;margin-right:4px;">
                <i class="bi bi-search me-1"></i><?= htmlspecialchars($query) ?>
            </span>
            <?php endif; ?>
            <?php if ($category): ?>
            <span style="background:rgba(16,185,129,.15);color:#10b981;padding:2px 8px;border-radius:99px;">
                <i class="bi bi-folder me-1"></i><?= htmlspecialchars($category) ?>
            </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Resultados -->
    <?php if (empty($results)): ?>
    <div class="hub-card p-5 text-center" style="color:#6b7280;">
        <i class="bi bi-inbox fs-1 d-block mb-3 opacity-25"></i>
        <div class="fw-semibold mb-1">No hay documentos disponibles</div>
        <small>Los documentos deben estar en estado <strong>Aprobado</strong> para aparecer aquí.</small>
    </div>
    <?php else: ?>
    <div class="row g-3">
        <?php foreach ($results as $doc): ?>
        <div class="col-md-6 col-xl-4">
            <div class="doc-card">
                <div class="d-flex align-items-start justify-content-between mb-2">
                    <span class="font-monospace" style="font-size:.72rem;background:rgba(255,255,255,.06);color:#9ca3af;padding:2px 8px;border-radius:6px;border:1px solid rgba(255,255,255,.08);">
                        <?= htmlspecialchars($doc['code'] ?? '—') ?>
                    </span>
                    <?= statusBadge($doc['status'] ?? 'Approved') ?>
                </div>

                <div class="d-flex align-items-start gap-2 mb-2">
                    <?php if (!empty($doc['fileExtension'])): ?>
                    <span class="fs-5 flex-shrink-0"><?= extIcon($doc['fileExtension']) ?></span>
                    <?php endif; ?>
                    <div class="fw-semibold" style="color:#e0e2ea;line-height:1.3;">
                        <?= htmlspecialchars($doc['title'] ?? 'Sin título') ?>
                    </div>
                </div>

                <div class="small mb-3" style="color:#6b7280;">
                    <?php if (!empty($doc['category'])): ?>
                    <i class="bi bi-folder me-1"></i><?= htmlspecialchars($doc['category']) ?>
                    <?php endif; ?>
                    <?php if (!empty($doc['standard'])): ?>
                    &nbsp;·&nbsp;<i class="bi bi-award me-1"></i><?= htmlspecialchars($doc['standard']) ?>
                    <?php endif; ?>
                    <?php if (!empty($doc['fileSize'])): ?>
                    &nbsp;·&nbsp;<i class="bi bi-hdd me-1"></i><?= formatSize((int)$doc['fileSize']) ?>
                    <?php endif; ?>
                </div>

                <?php if (!empty($doc['tags'])): ?>
                <div>
                    <?php foreach ((array)$doc['tags'] as $tag): ?>
                    <span class="tag-pill me-1 mb-1 d-inline-block"><?= htmlspecialchars($tag) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
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
