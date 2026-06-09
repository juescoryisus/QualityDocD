<?php
// =============================================================================
// QualityDoc Portal — Gestión de Usuarios
// Acceso: COMPANY_ADMIN (su empresa) y SUPER_ADMIN (todas las empresas)
// =============================================================================
require_once 'config.php';
require_once '_roles.php';
requireLogin();
requireModule('MODULE_1', true); // Solo COMPANY_ADMIN+

$user  = getSessionUser();
$token = getSessionToken();
$role  = $user['role'] ?? 'VIEWER';
$pgOk  = false;
try { getPgConnection(); $pgOk = true; } catch (Exception $e) {}

// ── Configuración de roles ────────────────────────────────────────────────────
$ROLE_INFO = [
    'SUPER_ADMIN'   => ['color'=>'danger',   'text'=>'white', 'icon'=>'bi-shield-fill-exclamation', 'label'=>'Súper Admin'],
    'COMPANY_ADMIN' => ['color'=>'primary',  'text'=>'white', 'icon'=>'bi-building-fill-gear',       'label'=>'Admin Empresa'],
    'OPERATOR'      => ['color'=>'warning',  'text'=>'dark',  'icon'=>'bi-person-gear',              'label'=>'Operador'],
    'CONTRIBUTOR'   => ['color'=>'info',     'text'=>'dark',  'icon'=>'bi-pencil-fill',              'label'=>'Contribuidor'],
    'COMMENTER'     => ['color'=>'secondary','text'=>'white', 'icon'=>'bi-chat-fill',                'label'=>'Comentador'],
    'VIEWER'        => ['color'=>'dark',     'text'=>'white', 'icon'=>'bi-eye-fill',                 'label'=>'Lector'],
];

$MODULE_ACCESS_MAP = [
    'SUPER_ADMIN'   => ['MODULE_1','MODULE_2','MODULE_3'],
    'COMPANY_ADMIN' => ['MODULE_1','MODULE_2','MODULE_3'],
    'OPERATOR'      => ['MODULE_1','MODULE_2','MODULE_3'],
    'CONTRIBUTOR'   => ['MODULE_2'],
    'COMMENTER'     => ['MODULE_2'],
    'VIEWER'        => ['MODULE_2'],
];

$MODULE_NAMES = [
    'MODULE_1' => 'Gestión',
    'MODULE_2' => 'Consulta',
    'MODULE_3' => 'Búsq. Avanzada',
];

// Roles que COMPANY_ADMIN puede asignar (no puede asignar su nivel ni superior)
$assignableRoles = $role === 'SUPER_ADMIN'
    ? ['SUPER_ADMIN','COMPANY_ADMIN','OPERATOR','CONTRIBUTOR','COMMENTER','VIEWER']
    : ['OPERATOR','CONTRIBUTOR','COMMENTER','VIEWER'];

// ── Procesar acciones POST ────────────────────────────────────────────────────
$actionMsg   = null;
$actionError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['_action'] ?? '';

    if ($action === 'create') {
        $body = [
            'companyId' => (int)($user['companyId'] ?? 0),
            'name'      => trim($_POST['name'] ?? ''),
            'email'     => trim($_POST['email'] ?? ''),
            'password'  => $_POST['password'] ?? '',
            'role'      => $_POST['role'] ?? 'VIEWER',
        ];
        $res = callNodeApi('/users', 'POST', $body, $token ?? '');
        if (isset($res['error'])) $actionError = $res['error'];
        else $actionMsg = "Usuario <strong>{$res['name']}</strong> creado con rol {$res['role']}.";

    } elseif ($action === 'change_role') {
        $uid  = (int)($_POST['user_id'] ?? 0);
        $newR = $_POST['new_role'] ?? '';
        $res  = callNodeApi("/users/{$uid}/role", 'PUT', ['role' => $newR], $token ?? '');
        if (isset($res['error'])) $actionError = $res['error'];
        else $actionMsg = "Rol de <strong>{$res['name']}</strong> actualizado a <strong>{$res['role']}</strong>.";

    } elseif ($action === 'delete') {
        $uid = (int)($_POST['user_id'] ?? 0);
        $res = callNodeApi("/users/{$uid}", 'DELETE', [], $token ?? '');
        if (isset($res['error'])) $actionError = $res['error'];
        else $actionMsg = "Usuario eliminado correctamente.";
    }
}

// ── Cargar lista de usuarios ──────────────────────────────────────────────────
$users = callNodeApi('/users', 'GET', [], $token ?? '');
if (isset($users['error'])) { $users = []; $actionError = $users['error'] ?? 'No se pudo cargar la lista de usuarios.'; }

// Filtro de búsqueda rápida
$filterSearch = trim($_GET['q'] ?? '');
$filterRole   = trim($_GET['role'] ?? '');
if ($filterSearch !== '') {
    $users = array_filter($users, fn($u) =>
        stripos($u['name'] ?? '', $filterSearch) !== false ||
        stripos($u['email'] ?? '', $filterSearch) !== false
    );
}
if ($filterRole !== '') {
    $users = array_filter($users, fn($u) => ($u['role'] ?? '') === $filterRole);
}

$currentPage = 'users';
?>
<!DOCTYPE html>
<html lang="es" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Usuarios — <?= APP_NAME ?></title>
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
        .table-dark { --bs-table-bg: transparent; --bs-table-striped-bg: rgba(255,255,255,.02); }
        .table-dark thead th { border-color:rgba(255,255,255,.08); font-size:.75rem; text-transform:uppercase; letter-spacing:.06em; color:#6b7280; font-weight:600; }
        .table-dark td { border-color:rgba(255,255,255,.05); vertical-align:middle; font-size:.85rem; color:#c8d0e0; }
        .user-row-avatar { width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:.8rem; color:#fff; flex-shrink:0; }
        .module-pill { display:inline-block; font-size:.62rem; padding:2px 7px; border-radius:99px; border:1px solid rgba(255,255,255,.12); color:#9ca3af; margin:1px; }
        .module-pill.active { background:rgba(79,142,247,.15); border-color:rgba(79,142,247,.3); color:#4f8ef7; }
        .stat-chip { background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.08); border-radius:10px; padding:16px 20px; }
        @media(max-width:768px){ #sidebar{transform:translateX(-100%);} #sidebar.open{transform:translateX(0);} #topbar,#main{left:0;margin-left:0;} }
    </style>
</head>
<body>
<?php require_once '_sidebar.php'; ?>

<main id="main">

    <?php if ($actionMsg): ?>
    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?= $actionMsg ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if ($actionError): ?>
    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
        <i class="bi bi-x-circle-fill me-2"></i><?= htmlspecialchars($actionError) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Encabezado -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
        <div>
            <h5 class="fw-bold text-white mb-1">Gestión de Usuarios</h5>
            <p class="text-muted small mb-0">
                Administra los accesos y roles del sistema
                <?= $role === 'SUPER_ADMIN' ? '— <span style="color:#f59e0b">todas las empresas</span>' : '' ?>
            </p>
        </div>
        <button class="btn btn-sm" style="background:var(--accent);color:#fff"
                data-bs-toggle="modal" data-bs-target="#modalNuevoUsuario">
            <i class="bi bi-person-plus-fill me-1"></i> Nuevo Usuario
        </button>
    </div>

    <!-- Estadísticas por rol -->
    <?php
    $allUsers  = callNodeApi('/users', 'GET', [], $token ?? '');
    $roleCounts = array_count_values(array_column($allUsers ?: [], 'role'));
    ?>
    <div class="row g-3 mb-4">
        <?php foreach ($ROLE_INFO as $rKey => $rInfo): ?>
        <?php $cnt = $roleCounts[$rKey] ?? 0; if ($cnt === 0 && $role !== 'SUPER_ADMIN') continue; ?>
        <div class="col-6 col-md-4 col-xl-2">
            <div class="stat-chip text-center">
                <div class="mb-1">
                    <i class="bi <?= $rInfo['icon'] ?> fs-5" style="color:var(--accent)"></i>
                </div>
                <div style="font-size:1.4rem;font-weight:700;color:#e0e2ea"><?= $cnt ?></div>
                <div style="font-size:.7rem;color:#6b7280;margin-top:2px"><?= $rInfo['label'] ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Filtros -->
    <div class="hub-card mb-4">
        <div class="hub-card-header">
            <i class="bi bi-funnel" style="color:#10b981"></i> Filtrar Usuarios
        </div>
        <div class="p-3">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-dark border-secondary" style="color:#6b7280">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="text" name="q" class="form-control form-control-sm bg-dark border-secondary text-light"
                               placeholder="Nombre o email..." value="<?= htmlspecialchars($filterSearch) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="role" class="form-select form-select-sm bg-dark border-secondary text-light">
                        <option value="">Todos los roles</option>
                        <?php foreach ($ROLE_INFO as $rKey => $rInfo): ?>
                        <option value="<?= $rKey ?>" <?= $filterRole===$rKey?'selected':'' ?>>
                            <?= $rInfo['label'] ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-outline-secondary flex-fill">Filtrar</button>
                    <a href="usuarios.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x-lg"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla de usuarios -->
    <div class="hub-card">
        <div class="hub-card-header justify-content-between">
            <div><i class="bi bi-people-fill" style="color:#4f8ef7"></i> Usuarios</div>
            <span class="badge" style="background:rgba(79,142,247,.15);color:#4f8ef7;font-size:.72rem">
                <?= count($users) ?> encontrado(s)
            </span>
        </div>
        <div class="p-0">
            <?php if (empty($users)): ?>
            <div class="text-center py-5" style="color:#6b7280">
                <i class="bi bi-people fs-2 mb-2 d-block"></i>
                No se encontraron usuarios con los filtros aplicados.
            </div>
            <?php else: ?>
            <div class="table-responsive">
            <table class="table table-dark table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width:44px"></th>
                        <th>Nombre / Email</th>
                        <th>Rol</th>
                        <th>Módulos de Acceso</th>
                        <th style="width:60px">ID</th>
                        <th style="width:160px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u):
                    $uRole    = $u['role'] ?? 'VIEWER';
                    $uInfo    = $ROLE_INFO[$uRole] ?? $ROLE_INFO['VIEWER'];
                    $uModules = $MODULE_ACCESS_MAP[$uRole] ?? ['MODULE_2'];
                    $avatarColors = ['#4f8ef7','#7c3aed','#10b981','#f59e0b','#ef4444','#06b6d4'];
                    $avatarBg = $avatarColors[crc32($u['name'] ?? '') % count($avatarColors)];
                    $isSelf   = ($u['id'] ?? 0) === ($user['id'] ?? -1);
                    $canEdit  = !$isSelf && ($role === 'SUPER_ADMIN' || ($role === 'COMPANY_ADMIN' && $uRole !== 'SUPER_ADMIN' && $uRole !== 'COMPANY_ADMIN'));
                ?>
                <tr>
                    <td class="ps-3">
                        <div class="user-row-avatar" style="background:<?= $avatarBg ?>">
                            <?= strtoupper(substr($u['name'] ?? 'U', 0, 1)) ?>
                        </div>
                    </td>
                    <td>
                        <div class="fw-semibold" style="color:#e0e2ea"><?= htmlspecialchars($u['name'] ?? '') ?></div>
                        <div style="font-size:.78rem;color:#6b7280"><?= htmlspecialchars($u['email'] ?? '') ?></div>
                        <?php if ($isSelf): ?>
                        <span style="font-size:.65rem;color:#22c55e"><i class="bi bi-star-fill me-1"></i>Tú</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge bg-<?= $uInfo['color'] ?> text-<?= $uInfo['text'] ?>">
                            <i class="bi <?= $uInfo['icon'] ?> me-1"></i><?= $uInfo['label'] ?>
                        </span>
                    </td>
                    <td>
                        <?php foreach ($MODULE_NAMES as $mKey => $mName):
                            $active = in_array($mKey, $uModules); ?>
                        <span class="module-pill <?= $active ? 'active' : '' ?>">
                            <?php if ($active): ?><i class="bi bi-check me-1"></i><?php endif; ?>
                            <?= $mName ?>
                        </span>
                        <?php endforeach; ?>
                    </td>
                    <td>
                        <span style="color:#4b5563;font-size:.78rem">#<?= $u['id'] ?? '—' ?></span>
                    </td>
                    <td>
                        <?php if ($canEdit): ?>
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm" style="background:rgba(79,142,247,.12);color:#4f8ef7;font-size:.75rem"
                                    onclick="openRoleModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['name'] ?? '') ?>', '<?= $uRole ?>')">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm" style="background:rgba(239,68,68,.12);color:#ef4444;font-size:.75rem"
                                    onclick="confirmDelete(<?= $u['id'] ?>, '<?= htmlspecialchars($u['name'] ?? '') ?>')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                        <?php elseif ($isSelf): ?>
                        <span style="font-size:.75rem;color:#4b5563">—</span>
                        <?php else: ?>
                        <span style="font-size:.75rem;color:#4b5563"><i class="bi bi-lock-fill"></i></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- ═══════════════════════════════════════ MODAL: Nuevo Usuario ═══ -->
<div class="modal fade" id="modalNuevoUsuario" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background:#1a1d24;border:1px solid rgba(255,255,255,.1)">
            <form method="post">
                <input type="hidden" name="_action" value="create">
                <div class="modal-header" style="border-color:rgba(255,255,255,.08)">
                    <h6 class="modal-title text-white"><i class="bi bi-person-plus-fill me-2" style="color:#4f8ef7"></i>Nuevo Usuario</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small text-muted">Nombre completo</label>
                        <input type="text" name="name" class="form-control bg-dark border-secondary text-light" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted">Email</label>
                        <input type="email" name="email" class="form-control bg-dark border-secondary text-light" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted">Contraseña temporal</label>
                        <input type="password" name="password" class="form-control bg-dark border-secondary text-light" required minlength="6">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted">Rol</label>
                        <select name="role" class="form-select bg-dark border-secondary text-light" id="newUserRoleSelect">
                            <?php foreach ($assignableRoles as $r):
                                $ri = $ROLE_INFO[$r] ?? []; ?>
                            <option value="<?= $r ?>"><?= $ri['label'] ?? $r ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <!-- Preview de módulos según rol seleccionado -->
                    <div class="p-2" style="background:rgba(255,255,255,.03);border-radius:8px;border:1px solid rgba(255,255,255,.06)">
                        <div class="small text-muted mb-2"><i class="bi bi-grid me-1"></i>Acceso a módulos:</div>
                        <div id="modulePreview"></div>
                    </div>
                </div>
                <div class="modal-footer" style="border-color:rgba(255,255,255,.08)">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm" style="background:#4f8ef7;color:#fff">
                        <i class="bi bi-person-plus me-1"></i>Crear Usuario
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════ MODAL: Cambiar Rol ═══ -->
<div class="modal fade" id="modalCambiarRol" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content" style="background:#1a1d24;border:1px solid rgba(255,255,255,.1)">
            <form method="post">
                <input type="hidden" name="_action" value="change_role">
                <input type="hidden" name="user_id" id="changeRoleUserId">
                <div class="modal-header" style="border-color:rgba(255,255,255,.08)">
                    <h6 class="modal-title text-white"><i class="bi bi-pencil me-2" style="color:#4f8ef7"></i>Cambiar Rol</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">Usuario: <strong id="changeRoleUserName" class="text-white"></strong></p>
                    <label class="form-label small text-muted">Nuevo rol</label>
                    <select name="new_role" id="changeRoleSelect" class="form-select bg-dark border-secondary text-light">
                        <?php foreach ($assignableRoles as $r):
                            $ri = $ROLE_INFO[$r] ?? []; ?>
                        <option value="<?= $r ?>"><?= $ri['label'] ?? $r ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="modal-footer" style="border-color:rgba(255,255,255,.08)">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm" style="background:#4f8ef7;color:#fff">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════ MODAL: Confirmar Eliminar ═══ -->
<div class="modal fade" id="modalEliminar" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content" style="background:#1a1d24;border:1px solid rgba(255,255,255,.1)">
            <form method="post">
                <input type="hidden" name="_action" value="delete">
                <input type="hidden" name="user_id" id="deleteUserId">
                <div class="modal-header border-0">
                    <h6 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Eliminar Usuario</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-3">
                    <p class="text-muted small mb-1">¿Estás seguro de eliminar a</p>
                    <p class="fw-bold text-white" id="deleteUserName"></p>
                    <p class="text-danger small">Esta acción no se puede deshacer.</p>
                </div>
                <div class="modal-footer" style="border-color:rgba(255,255,255,.08)">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const MODULE_ACCESS = <?= json_encode($MODULE_ACCESS_MAP) ?>;
const MODULE_NAMES  = <?= json_encode($MODULE_NAMES) ?>;

function renderModules(role, containerId) {
    const modules  = MODULE_ACCESS[role] || ['MODULE_2'];
    const container = document.getElementById(containerId);
    if (!container) return;
    container.innerHTML = Object.entries(MODULE_NAMES).map(([k, name]) **...**

_This response is too long to display in full._