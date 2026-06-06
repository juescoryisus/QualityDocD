<?php
// =============================================================================
// QualityDoc Portal — Dashboard Hub (página principal tras login)
// =============================================================================
require_once 'config.php';
requireLogin();

$user  = getSessionUser();
$token = getSessionToken();
$role  = $user['role'] ?? 'Viewer';

// ── Estadísticas desde PostgreSQL ─────────────────────────────────────────────
$stats  = ['total_events' => 0, 'unique_docs' => 0, 'unique_users' => 0, 'by_action' => []];
$recent = [];
$pgOk   = false;

function resolveCol2(PDO $pdo, string $table, array $candidates): string {
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
    $cAct  = resolveCol2($pdo, 'audit_entries', ['action',        'Action']);
    $cDocI = resolveCol2($pdo, 'audit_entries', ['document_id',   'DocumentId']);
    $cDocT = resolveCol2($pdo, 'audit_entries', ['document_title','DocumentTitle']);
    $cDocC = resolveCol2($pdo, 'audit_entries', ['document_code', 'DocumentCode']);
    $cUser = resolveCol2($pdo, 'audit_entries', ['username',      'Username']);
    $cUsrI = resolveCol2($pdo, 'audit_entries', ['user_id',       'UserId']);
    $cCre  = resolveCol2($pdo, 'audit_entries', ['created_at',    'CreatedAt']);

    $stats['total_events']  = (int)$pdo->query("SELECT COUNT(*) FROM audit_entries")->fetchColumn();
    $stats['unique_docs']   = (int)$pdo->query("SELECT COUNT(DISTINCT \"{$cDocI}\") FROM audit_entries")->fetchColumn();
    $stats['unique_users']  = (int)$pdo->query("SELECT COUNT(DISTINCT \"{$cUsrI}\") FROM audit_entries WHERE \"{$cUsrI}\" IS NOT NULL")->fetchColumn();
    $stats['by_action']     = $pdo->query("SELECT \"{$cAct}\" AS action, COUNT(*) AS cnt FROM audit_entries GROUP BY \"{$cAct}\" ORDER BY cnt DESC LIMIT 6")->fetchAll();
    $recent = $pdo->query(
        "SELECT \"{$cAct}\" AS action, \"{$cDocC}\" AS doc_code,
                \"{$cDocT}\" AS doc_title, \"{$cUser}\" AS username,
                \"{$cCre}\" AS created_at
         FROM audit_entries ORDER BY \"{$cCre}\" DESC LIMIT 8"
    )->fetchAll();
} catch (Exception $e) { /* PostgreSQL no disponible */ }

// ── Estado de servicios ────────────────────────────────────────────────────────
function checkService(string $url): bool {
    $ctx = stream_context_create(['http' => ['timeout' => 2, 'header' => 'Accept: application/json']]);
    return @file_get_contents($url, false, $ctx) !== false;
}

$services = [
    ['name' => 'App .NET',       'url' => DOTNET_API  . '/health',   'link' => '/',          'icon' => 'bi-filetype-cs',    'color' => 'primary'],
    ['name' => 'Portal PHP',     'url' => '',                         'link' => 'index.php',  'icon' => 'bi-filetype-php',   'color' => 'info'],
    ['name' => 'Search Service', 'url' => SEARCH_API  . '/health',   'link' => '#',          'icon' => 'bi-search',         'color' => 'warning'],
    ['name' => 'PostgreSQL',     'url' => '',                         'link' => 'audit.php',  'icon' => 'bi-database',       'color' => 'success'],
];

// Iconos de acción para la actividad reciente
$actionIcons = [
    'Created'         => ['bi-plus-circle-fill',   'success'],
    'Updated'         => ['bi-pencil-fill',         'primary'],
    'StatusChanged'   => ['bi-arrow-repeat',        'warning'],
    'Approved'        => ['bi-check-circle-fill',   'success'],
    'Rejected'        => ['bi-x-circle-fill',       'danger'],
    'Downloaded'      => ['bi-download',            'info'],
    'ApprovalAdded'   => ['bi-person-check-fill',   'primary'],
    'ApprovalReviewed'=> ['bi-patch-check-fill',    'success'],
];

// Menú lateral (items según rol)
$navItems = [
    ['icon' => 'bi-speedometer2',   'label' => 'Dashboard',    'href' => 'dashboard.php', 'active' => true,  'roles' => ['Admin','Manager','Reviewer','Viewer']],
    ['icon' => 'bi-file-earmark-text','label'=>'Documentos',   'href' => 'documents.php', 'active' => false, 'roles' => ['Admin','Manager','Reviewer','Viewer']],
    ['icon' => 'bi-shield-check',   'label' => 'Auditoría',    'href' => 'audit.php',     'active' => false, 'roles' => ['Admin','Manager','Reviewer']],
    ['icon' => 'bi-graph-up',       'label' => 'Reportes',     'href' => '#',             'active' => false, 'roles' => ['Admin','Manager']],
    ['icon' => 'bi-people-fill',    'label' => 'Usuarios',     'href' => '#',             'active' => false, 'roles' => ['Admin']],
    ['icon' => 'bi-gear-fill',      'label' => 'Configuración','href' => '#',             'active' => false, 'roles' => ['Admin']],
];
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --sidebar-w: 260px;
            --topbar-h: 56px;
            --bg-sidebar: #0f1117;
            --bg-main: #13151a;
            --accent: #4f8ef7;
        }

        body { margin: 0; background: var(--bg-main); font-family: 'Segoe UI', system-ui, sans-serif; }

        /* ── Sidebar ── */
        #sidebar {
            position: fixed; top: 0; left: 0; bottom: 0;
            width: var(--sidebar-w);
            background: var(--bg-sidebar);
            border-right: 1px solid rgba(255,255,255,.07);
            display: flex; flex-direction: column;
            z-index: 200; transition: transform .25s ease;
        }
        .sidebar-brand {
            display: flex; align-items: center; gap: 10px;
            padding: 18px 20px 16px;
            border-bottom: 1px solid rgba(255,255,255,.07);
        }
        .sidebar-brand .brand-icon {
            width: 36px; height: 36px; border-radius: 8px;
            background: var(--accent);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; color: #fff;
        }
        .sidebar-brand .brand-name { font-weight: 700; font-size: .95rem; color: #e8eaf0; }
        .sidebar-brand .brand-sub  { font-size: .72rem; color: #6b7280; }

        .sidebar-user {
            display: flex; align-items: center; gap: 10px;
            padding: 14px 18px;
            border-bottom: 1px solid rgba(255,255,255,.07);
        }
        .user-avatar {
            width: 34px; height: 34px; border-radius: 50%;
            background: linear-gradient(135deg, #4f8ef7, #7c3aed);
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: .85rem; color: #fff; flex-shrink: 0;
        }
        .user-name   { font-size: .82rem; font-weight: 600; color: #e0e2ea; }
        .user-role   { font-size: .7rem; color: #6b7280; }

        .sidebar-section { padding: 14px 12px 4px; font-size: .66rem; font-weight: 700;
            letter-spacing: .08em; text-transform: uppercase; color: #4b5563; }

        .nav-item-link {
            display: flex; align-items: center; gap: 11px;
            padding: 9px 14px; margin: 1px 8px;
            border-radius: 8px; text-decoration: none;
            color: #9ca3af; font-size: .85rem; font-weight: 500;
            transition: background .15s, color .15s;
        }
        .nav-item-link:hover  { background: rgba(79,142,247,.12); color: #c8d4f5; }
        .nav-item-link.active { background: rgba(79,142,247,.18); color: var(--accent); }
        .nav-item-link i { font-size: 1rem; width: 18px; text-align: center; }

        .sidebar-services { padding: 0 12px 8px; }
        .service-badge {
            display: flex; align-items: center; gap: 8px;
            padding: 7px 10px; margin-bottom: 2px;
            border-radius: 8px; text-decoration: none;
            background: rgba(255,255,255,.03);
            border: 1px solid rgba(255,255,255,.06);
            font-size: .78rem; color: #9ca3af;
            transition: background .15s, color .15s;
        }
        .service-badge:hover { background: rgba(255,255,255,.07); color: #d1d5db; }
        .service-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
        .dot-ok   { background: #22c55e; }
        .dot-warn { background: #f59e0b; }
        .dot-off  { background: #6b7280; }

        .sidebar-footer {
            margin-top: auto; padding: 14px 12px;
            border-top: 1px solid rgba(255,255,255,.07);
        }

        /* ── Topbar ── */
        #topbar {
            position: fixed; top: 0; left: var(--sidebar-w); right: 0; height: var(--topbar-h);
            background: var(--bg-sidebar);
            border-bottom: 1px solid rgba(255,255,255,.07);
            display: flex; align-items: center; padding: 0 24px;
            z-index: 100; gap: 12px;
        }
        #topbar .page-title { font-size: 1rem; font-weight: 600; color: #e0e2ea; }
        #topbar .breadcrumb  { font-size: .75rem; margin: 0; }

        /* ── Main content ── */
        #main {
            margin-left: var(--sidebar-w);
            margin-top: var(--topbar-h);
            padding: 28px 28px 40px;
            min-height: calc(100vh - var(--topbar-h));
        }

        /* ── Stat cards ── */
        .stat-card {
            background: rgba(255,255,255,.04);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 12px; padding: 20px 22px;
            display: flex; align-items: center; gap: 16px;
            transition: border-color .2s, transform .2s;
        }
        .stat-card:hover { border-color: var(--accent); transform: translateY(-2px); }
        .stat-icon {
            width: 46px; height: 46px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.25rem; flex-shrink: 0;
        }
        .stat-value { font-size: 1.7rem; font-weight: 700; color: #e8eaf0; line-height: 1; }
        .stat-label { font-size: .75rem; color: #6b7280; margin-top: 3px; }

        /* ── Cards ── */
        .hub-card {
            background: rgba(255,255,255,.04);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 12px; overflow: hidden;
        }
        .hub-card-header {
            padding: 14px 18px;
            border-bottom: 1px solid rgba(255,255,255,.07);
            display: flex; align-items: center; gap: 8px;
            font-size: .85rem; font-weight: 600; color: #c8d0e0;
        }

        /* ── Quick actions ── */
        .quick-action {
            display: flex; flex-direction: column; align-items: center;
            padding: 18px 12px; border-radius: 10px;
            background: rgba(255,255,255,.04);
            border: 1px solid rgba(255,255,255,.07);
            text-decoration: none; color: #9ca3af;
            transition: background .2s, color .2s, transform .2s;
            text-align: center; gap: 8px;
        }
        .quick-action:hover { background: rgba(79,142,247,.15); color: #c8d4f5; transform: translateY(-2px); }
        .quick-action i { font-size: 1.5rem; }
        .quick-action span { font-size: .75rem; font-weight: 500; }

        /* ── Activity ── */
        .activity-row {
            display: flex; align-items: center; gap: 12px;
            padding: 10px 18px;
            border-bottom: 1px solid rgba(255,255,255,.05);
            font-size: .8rem;
        }
        .activity-row:last-child { border-bottom: none; }
        .activity-icon {
            width: 30px; height: 30px; border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: .85rem; flex-shrink: 0;
        }
        .activity-meta { color: #6b7280; font-size: .72rem; margin-top: 1px; }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            #sidebar { transform: translateX(-100%); }
            #sidebar.open { transform: translateX(0); }
            #topbar, #main { left: 0; margin-left: 0; }
        }
    </style>
</head>
<body>

<!-- ═══════════════════════════════════════════════════════ SIDEBAR ═══ -->
<nav id="sidebar">

    <!-- Brand -->
    <div class="sidebar-brand">
        <div class="brand-icon"><i class="bi bi-file-earmark-check2"></i></div>
        <div>
            <div class="brand-name"><?= APP_NAME ?></div>
            <div class="brand-sub">Sistema de Gestión Documental</div>
        </div>
    </div>

    <!-- Usuario -->
    <div class="sidebar-user">
        <div class="user-avatar"><?= strtoupper(substr($user['username'] ?? 'U', 0, 1)) ?></div>
        <div>
            <div class="user-name"><?= htmlspecialchars($user['username'] ?? '') ?></div>
            <div class="user-role"><?= htmlspecialchars($role) ?> · <?= htmlspecialchars($user['department'] ?? '') ?></div>
        </div>
    </div>

    <!-- Navegación principal -->
    <div class="sidebar-section">Navegación</div>
    <nav>
        <?php foreach ($navItems as $item):
            if (!in_array($role, $item['roles'])) continue; ?>
            <a href="<?= $item['href'] ?>"
               class="nav-item-link <?= $item['active'] ? 'active' : '' ?>">
                <i class="bi <?= $item['icon'] ?>"></i>
                <?= $item['label'] ?>
                <?php if ($item['active']): ?>
                    <span class="ms-auto badge rounded-pill" style="background:rgba(79,142,247,.2);color:#4f8ef7;font-size:.65rem;">Activo</span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- Servicios -->
    <div class="sidebar-section" style="margin-top:8px;">Servicios</div>
    <div class="sidebar-services">
        <?php
        $svcList = [
            ['label' => 'App .NET',        'check' => DOTNET_API . '/health',  'href' => '/',            'icon' => 'bi-filetype-cs'],
            ['label' => 'Portal PHP',      'check' => null,                    'href' => 'index.php',    'icon' => 'bi-filetype-php'],
            ['label' => 'Búsqueda',        'check' => SEARCH_API . '/health',  'href' => '#',            'icon' => 'bi-search'],
            ['label' => 'Auditoría (PG)',  'check' => null,                    'href' => 'audit.php',    'icon' => 'bi-database-fill'],
        ];
        foreach ($svcList as $svc):
            if ($svc['check'] !== null) {
                $ctx = stream_context_create(['http'=>['timeout'=>1,'ignore_errors'=>true]]);
                $ok  = @file_get_contents($svc['check'], false, $ctx) !== false;
            } else {
                $ok = ($svc['label'] === 'Portal PHP') ? true : $pgOk;
            }
            $dotClass = $ok ? 'dot-ok' : 'dot-off';
        ?>
        <a href="<?= $svc['href'] ?>" class="service-badge">
            <span class="service-dot <?= $dotClass ?>"></span>
            <i class="bi <?= $svc['icon'] ?>" style="width:14px;text-align:center;"></i>
            <?= $svc['label'] ?>
            <span class="ms-auto" style="font-size:.68rem;color:<?= $ok ? '#22c55e' : '#6b7280' ?>">
                <?= $ok ? 'Online' : 'Offline' ?>
            </span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Footer -->
    <div class="sidebar-footer">
        <a href="login.php?logout=1" class="nav-item-link text-danger"
           onclick="clearSessionUser(); return true;">
            <i class="bi bi-box-arrow-left"></i> Cerrar sesión
        </a>
    </div>
</nav>

<!-- ═══════════════════════════════════════════════════════ TOPBAR ════ -->
<header id="topbar">
    <button class="btn btn-sm d-md-none me-2" onclick="document.getElementById('sidebar').classList.toggle('open')" style="color:#9ca3af">
        <i class="bi bi-list fs-5"></i>
    </button>
    <div>
        <div class="page-title"><i class="bi bi-speedometer2 me-2" style="color:var(--accent)"></i>Dashboard</div>
    </div>
    <div class="ms-auto d-flex align-items-center gap-3">
        <span class="badge" style="background:rgba(34,197,94,.15);color:#22c55e;font-size:.72rem;">
            <i class="bi bi-circle-fill me-1" style="font-size:.4rem;vertical-align:middle;"></i>
            <?= $pgOk ? 'PostgreSQL OK' : 'PostgreSQL Offline' ?>
        </span>
        <span class="text-muted small"><?= date('d M Y, H:i') ?></span>
    </div>
</header>

<!-- ═══════════════════════════════════════════════════════ MAIN ══════ -->
<main id="main">

    <!-- Saludo -->
    <div class="mb-4">
        <h5 class="fw-bold text-white mb-1">
            <?php
            $h = (int)date('H');
            echo $h < 12 ? 'Buenos días' : ($h < 18 ? 'Buenas tardes' : 'Buenas noches');
            ?>, <?= htmlspecialchars($user['username'] ?? '') ?> 👋
        </h5>
        <p class="text-muted small mb-0">Resumen del sistema — <?= date('l, d \d\e F \d\e Y') ?></p>
    </div>

    <!-- ── Stats ── -->
    <div class="row g-3 mb-4">
        <?php
        $statCards = [
            ['icon' => 'bi-activity',         'color' => '#4f8ef7', 'bg' => 'rgba(79,142,247,.12)',  'value' => number_format($stats['total_events']),  'label' => 'Eventos de Auditoría'],
            ['icon' => 'bi-file-earmark-text', 'color' => '#10b981', 'bg' => 'rgba(16,185,129,.12)',  'value' => number_format($stats['unique_docs']),   'label' => 'Documentos Registrados'],
            ['icon' => 'bi-people-fill',       'color' => '#f59e0b', 'bg' => 'rgba(245,158,11,.12)',  'value' => number_format($stats['unique_users']),  'label' => 'Usuarios Activos'],
            ['icon' => 'bi-check2-circle',     'color' => '#8b5cf6', 'bg' => 'rgba(139,92,246,.12)',  'value' => count($stats['by_action']),             'label' => 'Tipos de Acción'],
        ];
        foreach ($statCards as $s): ?>
        <div class="col-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:<?= $s['bg'] ?>;color:<?= $s['color'] ?>">
                    <i class="bi <?= $s['icon'] ?>"></i>
                </div>
                <div>
                    <div class="stat-value"><?= $s['value'] ?></div>
                    <div class="stat-label"><?= $s['label'] ?></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- ── Acciones rápidas ── -->
    <div class="hub-card mb-4">
        <div class="hub-card-header">
            <i class="bi bi-lightning-charge-fill" style="color:#f59e0b"></i> Acciones Rápidas
        </div>
        <div class="p-3">
            <div class="row g-2">
                <?php
                $actions = [
                    ['href' => 'documents.php',     'icon' => 'bi-file-earmark-plus', 'label' => 'Nuevo Documento',  'color' => '#4f8ef7', 'roles' => ['Admin','Manager','Reviewer','Viewer']],
                    ['href' => 'documents.php',     'icon' => 'bi-search',             'label' => 'Buscar Docs',      'color' => '#10b981', 'roles' => ['Admin','Manager','Reviewer','Viewer']],
                    ['href' => 'audit.php',         'icon' => 'bi-shield-check',       'label' => 'Ver Auditoría',    'color' => '#8b5cf6', 'roles' => ['Admin','Manager','Reviewer']],
                    ['href' => '#',                 'icon' => 'bi-graph-up-arrow',     'label' => 'Reportes',         'color' => '#f59e0b', 'roles' => ['Admin','Manager']],
                    ['href' => '#',                 'icon' => 'bi-person-plus-fill',   'label' => 'Nuevo Usuario',    'color' => '#ec4899', 'roles' => ['Admin']],
                    ['href' => 'index.php',         'icon' => 'bi-house-fill',         'label' => 'Panel Info',       'color' => '#6b7280', 'roles' => ['Admin','Manager','Reviewer','Viewer']],
                ];
                foreach ($actions as $a):
                    if (!in_array($role, $a['roles'])) continue; ?>
                <div class="col-6 col-md-4 col-lg-2">
                    <a href="<?= $a['href'] ?>" class="quick-action">
                        <i class="bi <?= $a['icon'] ?>" style="color:<?= $a['color'] ?>"></i>
                        <span><?= $a['label'] ?></span>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- ── Actividad reciente ── -->
        <div class="col-lg-8">
            <div class="hub-card h-100">
                <div class="hub-card-header">
                    <i class="bi bi-clock-history" style="color:#4f8ef7"></i> Actividad Reciente
                </div>
                <?php if (empty($recent)): ?>
                    <div class="p-4 text-center text-muted small">
                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                        Sin eventos de auditoría aún.
                    </div>
                <?php else: ?>
                    <?php foreach ($recent as $row):
                        $act = $row['action'] ?? 'Unknown';
                        [$ico, $col] = $actionIcons[$act] ?? ['bi-circle', 'secondary'];
                        $dt = new DateTime($row['created_at'] ?? 'now');
                    ?>
                    <div class="activity-row">
                        <div class="activity-icon bg-<?= $col ?> bg-opacity-10 text-<?= $col ?>">
                            <i class="bi <?= $ico ?>"></i>
                        </div>
                        <div class="flex-grow-1 overflow-hidden">
                            <div style="color:#d1d5db;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                <strong><?= htmlspecialchars($act) ?></strong>
                                — <?= htmlspecialchars($row['doc_title'] ?: ($row['doc_code'] ?: '—')) ?>
                            </div>
                            <div class="activity-meta">
                                <i class="bi bi-person me-1"></i><?= htmlspecialchars($row['username'] ?? '—') ?>
                                &nbsp;·&nbsp;
                                <i class="bi bi-clock me-1"></i><?= $dt->format('d/m/Y H:i') ?>
                            </div>
                        </div>
                        <span class="badge bg-<?= $col ?> bg-opacity-20 text-<?= $col ?> flex-shrink-0" style="font-size:.67rem;">
                            <?= htmlspecialchars($act) ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── Acciones por tipo ── -->
        <div class="col-lg-4">
            <div class="hub-card mb-3">
                <div class="hub-card-header">
                    <i class="bi bi-pie-chart-fill" style="color:#8b5cf6"></i> Eventos por Tipo
                </div>
                <div class="p-3">
                    <?php if (empty($stats['by_action'])): ?>
                        <p class="text-muted small text-center mb-0">Sin datos.</p>
                    <?php else:
                        $maxCount = max(array_column($stats['by_action'], 'cnt')) ?: 1;
                        $colors   = ['#4f8ef7','#10b981','#f59e0b','#8b5cf6','#ec4899','#14b8a6'];
                        foreach ($stats['by_action'] as $i => $row):
                            $pct = round(($row['cnt'] / $maxCount) * 100);
                            $c   = $colors[$i % count($colors)];
                    ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1" style="font-size:.78rem;color:#d1d5db;">
                                <span><?= htmlspecialchars($row['action']) ?></span>
                                <span style="color:<?= $c ?>;font-weight:600;"><?= $row['cnt'] ?></span>
                            </div>
                            <div style="height:5px;background:rgba(255,255,255,.06);border-radius:99px;overflow:hidden;">
                                <div style="width:<?= $pct ?>%;height:100%;background:<?= $c ?>;border-radius:99px;transition:width .6s ease;"></div>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <!-- Info del usuario -->
            <div class="hub-card">
                <div class="hub-card-header">
                    <i class="bi bi-person-badge-fill" style="color:#10b981"></i> Mi Cuenta
                </div>
                <div class="p-3" style="font-size:.8rem;color:#9ca3af;">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Usuario</span>
                        <span style="color:#e0e2ea;"><?= htmlspecialchars($user['username'] ?? '—') ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Rol</span>
                        <span class="badge bg-primary bg-opacity-20 text-primary"><?= htmlspecialchars($role) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span>Departamento</span>
                        <span style="color:#e0e2ea;"><?= htmlspecialchars($user['department'] ?? '—') ?></span>
                    </div>
                    <hr style="border-color:rgba(255,255,255,.07)">
                    <a href="login.php?logout=1" class="btn btn-sm btn-outline-danger w-100">
                        <i class="bi bi-box-arrow-left me-1"></i>Cerrar sesión
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Cerrar sidebar en móvil al hacer clic fuera
document.addEventListener('click', e => {
    const sb = document.getElementById('sidebar');
    if (window.innerWidth < 768 && !sb.contains(e.target) && !e.target.closest('[onclick]')) {
        sb.classList.remove('open');
    }
});
</script>
</body>