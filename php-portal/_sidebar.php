<?php
// =============================================================================
// _sidebar.php — Componente de navegación compartida
// Requiere que el padre haya definido:
//   $currentPage  string  ('dashboard' | 'documents' | 'audit' | etc.)
//   $user         array   getSessionUser()
//   $role         string  $user['role'] ?? 'Viewer'
//   $pgOk         bool    true si PostgreSQL está disponible
// =============================================================================
$navItems = [
    ['icon'=>'bi-speedometer2',    'label'=>'Dashboard',    'href'=>'dashboard.php', 'key'=>'dashboard', 'roles'=>['Admin','Manager','Reviewer','Viewer']],
    ['icon'=>'bi-file-earmark-text','label'=>'Documentos',  'href'=>'documents.php', 'key'=>'documents', 'roles'=>['Admin','Manager','Reviewer','Viewer']],
    ['icon'=>'bi-shield-check',    'label'=>'Auditoría',    'href'=>'audit.php',     'key'=>'audit',     'roles'=>['Admin','Manager','Reviewer']],
    ['icon'=>'bi-graph-up',        'label'=>'Reportes',     'href'=>'#',             'key'=>'reports',   'roles'=>['Admin','Manager']],
    ['icon'=>'bi-people-fill',     'label'=>'Usuarios',     'href'=>'#',             'key'=>'users',     'roles'=>['Admin']],
    ['icon'=>'bi-gear-fill',       'label'=>'Configuración','href'=>'#',             'key'=>'settings',  'roles'=>['Admin']],
];

$svcList = [
    ['label'=>'App .NET',      'check'=>DOTNET_API.'/health', 'href'=>'/',          'icon'=>'bi-filetype-cs'],
    ['label'=>'Portal PHP',    'check'=>null,                  'href'=>'index.php',  'icon'=>'bi-filetype-php'],
    ['label'=>'Búsqueda',      'check'=>SEARCH_API.'/health',  'href'=>'#',          'icon'=>'bi-search'],
    ['label'=>'Auditoría (PG)','check'=>null,                  'href'=>'audit.php',  'icon'=>'bi-database-fill'],
];

$pageTitles = [
    'dashboard' => ['icon'=>'bi-speedometer2', 'label'=>'Dashboard'],
    'documents' => ['icon'=>'bi-file-earmark-text','label'=>'Documentos'],
    'audit'     => ['icon'=>'bi-shield-check', 'label'=>'Auditoría'],
    'reports'   => ['icon'=>'bi-graph-up',     'label'=>'Reportes'],
    'users'     => ['icon'=>'bi-people-fill',  'label'=>'Usuarios'],
    'settings'  => ['icon'=>'bi-gear-fill',    'label'=>'Configuración'],
];
$pt = $pageTitles[$currentPage] ?? ['icon'=>'bi-circle','label'=>ucfirst($currentPage)];
?>
<!-- ══════════════════════════════════════════════════════ SIDEBAR ══ -->
<nav id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon"><i class="bi bi-file-earmark-check2"></i></div>
        <div>
            <div class="brand-name"><?= APP_NAME ?></div>
            <div class="brand-sub">Sistema de Gestión Documental</div>
        </div>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar"><?= strtoupper(substr($user['username'] ?? 'U', 0, 1)) ?></div>
        <div>
            <div class="user-name"><?= htmlspecialchars($user['username'] ?? '') ?></div>
            <div class="user-role"><?= htmlspecialchars($role) ?> · <?= htmlspecialchars($user['department'] ?? '') ?></div>
        </div>
    </div>

    <div class="sidebar-section">Navegación</div>
    <nav>
        <?php foreach ($navItems as $item):
            if (!in_array($role, $item['roles'])) continue;
            $isActive = ($item['key'] === $currentPage); ?>
        <a href="<?= $item['href'] ?>" class="nav-item-link <?= $isActive ? 'active' : '' ?>">
            <i class="bi <?= $item['icon'] ?>"></i>
            <?= $item['label'] ?>
            <?php if ($isActive): ?>
                <span class="ms-auto badge rounded-pill" style="background:rgba(79,142,247,.2);color:#4f8ef7;font-size:.65rem;">Activo</span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-section" style="margin-top:8px;">Servicios</div>
    <div class="sidebar-services">
        <?php foreach ($svcList as $svc):
            if ($svc['check'] !== null) {
                $ctx = stream_context_create(['http'=>['timeout'=>1,'ignore_errors'=>true]]);
                $ok  = @file_get_contents($svc['check'], false, $ctx) !== false;
            } else {
                $ok = ($svc['label'] === 'Portal PHP') ? true : $pgOk;
            }
        ?>
        <a href="<?= $svc['href'] ?>" class="service-badge">
            <span class="service-dot <?= $ok ? 'dot-ok' : 'dot-off' ?>"></span>
            <i class="bi <?= $svc['icon'] ?>" style="width:14px;text-align:center;"></i>
            <?= $svc['label'] ?>
            <span class="ms-auto" style="font-size:.68rem;color:<?= $ok ? '#22c55e' : '#6b7280' ?>">
                <?= $ok ? 'Online' : 'Offline' ?>
            </span>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="sidebar-footer">
        <a href="login.php?logout=1" class="nav-item-link text-danger">
            <i class="bi bi-box-arrow-left"></i> Cerrar sesión
        </a>
    </div>
</nav>

<!-- ══════════════════════════════════════════════════════ TOPBAR ═══ -->
<header id="topbar">
    <button class="btn btn-sm d-md-none me-2" onclick="document.getElementById('sidebar').classList.toggle('open')" style="color:#9ca3af">
        <i class="bi bi-list fs-5"></i>
    </button>
    <div class="page-title">
        <i class="bi <?= $pt['icon'] ?> me-2" style="color:var(--accent)"></i><?= $pt['label'] ?>
    </div>
    <div class="ms-auto d-flex align-items-center gap-3">
        <span class="badge" style="background:rgba(34,197,94,.15);color:#22c55e;font-size:.72rem;">
            <i class="bi bi-circle-fill me-1" style="font-size:.4rem;vertical-align:middle;"></i>
            <?= $pgOk ? 'PostgreSQL OK' : 'PostgreSQL Offline' ?>
        </span>
        <span class="text-muted small d-none d-md-inline"><?= date('d M Y, H:i') ?></span>
    </div>