<?php
require_once 'config.php';

$query    = trim($_GET['q']        ?? '');
$category = trim($_GET['category'] ?? '');
$results  = [];
$total    = 0;
$error    = null;

// Categorías disponibles desde el search service
$categoriesData = callSearchApi('/api/categories');
$categories     = $categoriesData['categories'] ?? [];

// Buscar documentos vía Node.js → MongoDB
if ($query !== '' || $category !== '') {
    $path = '/api/search?q=' . urlencode($query);
    if ($category !== '') $path .= '&category=' . urlencode($category);
    $path .= '&status=Approved';

    $data    = callSearchApi($path);
    $results = $data['results'] ?? [];
    $total   = $data['total']   ?? count($results);

    if (empty($data) && ($query !== '' || $category !== '')) {
        $error = 'El servicio de búsqueda no está disponible. Verifica que los contenedores Docker estén corriendo.';
    }
} else {
    // Sin filtros: mostrar todos los aprobados
    $data    = callSearchApi('/api/search?q=&status=Approved');
    $results = $data['results'] ?? [];
    $total   = $data['total']   ?? count($results);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Documentos Públicos — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f0f2f5; }
        .navbar-brand { font-weight: 700; letter-spacing: -.5px; }
        .doc-card { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,.08);
                    transition: transform .15s, box-shadow .15s; }
        .doc-card:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,.12); }
        .tag-pill { font-size: .7rem; }
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
            <a class="nav-link active" href="documents.php">
                <i class="bi bi-files me-1"></i>Documentos
            </a>
            <a class="nav-link" href="audit.php">
                <i class="bi bi-journal-text me-1"></i>Auditoría
            </a>
        </div>
    </div>
</nav>

<div class="container py-4">

    <div class="mb-4">
        <h4 class="fw-bold mb-1">Documentos Públicos Aprobados</h4>
        <p class="text-muted mb-0">
            Búsqueda indexada en <strong>MongoDB</strong> via microservicio Node.js
        </p>
    </div>

    <!-- Formulario de búsqueda -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="get" action="documents.php" class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Buscar</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white">
                            <i class="bi bi-search text-muted"></i>
                        </span>
                        <input type="text" name="q" class="form-control"
                               placeholder="Título, código, descripción, etiquetas..."
                               value="<?= htmlspecialchars($query) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold">Categoría</label>
                    <select name="category" class="form-select">
                        <option value="">Todas las categorías</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>"
                            <?= $category === $cat ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search me-1"></i>Buscar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <!-- Resultados -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <span class="text-muted small">
            <?= $total ?> documento<?= $total !== 1 ? 's' : '' ?> encontrado<?= $total !== 1 ? 's' : '' ?>
        </span>
        <?php if ($query || $category): ?>
        <a href="documents.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-x me-1"></i>Limpiar filtros
        </a>
        <?php endif; ?>
    </div>

    <?php if (empty($results)): ?>
    <div class="text-center py-5 text-muted">
        <i class="bi bi-inbox fs-1 d-block mb-3 opacity-25"></i>
        <div>No hay documentos aprobados disponibles.</div>
        <small>Los documentos deben estar en estado <strong>Aprobado</strong> para aparecer aquí.</small>
    </div>
    <?php else: ?>
    <div class="row g-3">
        <?php foreach ($results as $doc): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card doc-card h-100 p-3">
                <div class="d-flex align-items-start justify-content-between mb-2">
                    <span class="badge bg-light text-dark border font-monospace">
                        <?= htmlspecialchars($doc['code'] ?? '—') ?>
                    </span>
                    <?= statusBadge($doc['status'] ?? 'Approved') ?>
                </div>

                <div class="d-flex align-items-center gap-2 mb-2">
                    <?php if (!empty($doc['fileExtension'])): ?>
                        <span class="fs-5"><?= extIcon($doc['fileExtension']) ?></span>
                    <?php endif; ?>
                    <div class="fw-semibold lh-sm">
                        <?= htmlspecialchars($doc['title'] ?? 'Sin título') ?>
                    </div>
                </div>

                <div class="text-muted small mb-2">
                    <?php if (!empty($doc['category'])): ?>
                    <i class="bi bi-folder me-1"></i><?= htmlspecialchars($doc['category']) ?>
                    <?php endif; ?>
                    <?php if (!empty($doc['standard'])): ?>
                    &nbsp;·&nbsp;
                    <i class="bi bi-award me-1"></i><?= htmlspecialchars($doc['standard']) ?>
                    <?php endif; ?>
                </div>

                <?php if (!empty($doc['tags'])): ?>
                <div class="mt-auto pt-2">
                    <?php foreach ((array)$doc['tags'] as $tag): ?>
                    <span class="badge bg-light text-secondary border tag-pill me-1 mb-1">
                        <?= htmlspecialchars($tag) ?>
                    </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>

<footer class="text-center text-muted py-3 mt-4" style="font-size:.8rem">
    Datos desde MongoDB vía Node.js Search Service · <?= APP_NAME ?>
</footer>

</body>
</html>
