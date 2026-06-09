<?php
// Módulos y permisos 

require_once 'config.php';

const ROLE_WEIGHT = [
    'VIEWER'        => 1,
    'COMMENTER'     => 2,
    'CONTRIBUTOR'   => 3,
    'OPERATOR'      => 4,
    'COMPANY_ADMIN' => 5,
    'SUPER_ADMIN'   => 6,
];

const MODULE_ACCESS = [
    'MODULE_1' => ['OPERATOR', 'COMPANY_ADMIN', 'SUPER_ADMIN'],
    'MODULE_2' => ['VIEWER','COMMENTER','CONTRIBUTOR','OPERATOR','COMPANY_ADMIN','SUPER_ADMIN'],
    'MODULE_3' => ['OPERATOR', 'COMPANY_ADMIN', 'SUPER_ADMIN'],
];

const MODULE_WRITE = [
    'MODULE_1' => ['OPERATOR', 'COMPANY_ADMIN', 'SUPER_ADMIN'],
    'MODULE_2' => ['COMPANY_ADMIN', 'SUPER_ADMIN'],
    'MODULE_3' => ['SUPER_ADMIN'],
];

function getCurrentRole(): string {
    $user = getSessionUser();
    return $user['role'] ?? 'VIEWER';
}

function canAccess(string $module, bool $write = false): bool {
    $role = getCurrentRole();
    $list = $write ? (MODULE_WRITE[$module] ?? []) : (MODULE_ACCESS[$module] ?? []);
    return in_array($role, $list);
}

function requireModule(string $module, bool $write = false): void {
    if (!canAccess($module, $write)) {
        http_response_code(403);
        echo '<div class="alert alert-danger m-4"><b>Acceso denegado.</b> No tienes permisos para este módulo.</div>';
        exit;
    }
}

function hasMinRole(string $minRole): bool {
    $role = getCurrentRole();
    return (ROLE_WEIGHT[$role] ?? 0) >= (ROLE_WEIGHT[$minRole] ?? 999);
}

// Badge HTML para el rol del usuario
function roleBadge(string $role): string {
    $map = [
        'SUPER_ADMIN'   => ['danger',  'Súper Admin'],
        'COMPANY_ADMIN' => ['primary', 'Admin Empresa'],
        'OPERATOR'      => ['warning', 'Operador'],
        'CONTRIBUTOR'   => ['info',    'Contribuidor'],
        'COMMENTER'     => ['secondary','Comentador'],
        'VIEWER'        => ['light',   'Lector'],
    ];
    [$color, $label] = $map[$role] ?? ['light', $role];
    $text = in_array($color, ['light', 'warning', 'info']) ? 'text-dark' : 'text-white';
    return "<span class=\"badge bg-{$color} {$text}\">{$label}</span>";
}