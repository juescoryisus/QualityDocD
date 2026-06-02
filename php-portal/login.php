<?php
// =============================================================================
// QualityDoc PHP Portal — Login usando el endpoint .NET
// Llamada: POST /api/auth/login → recibe JWT → guarda en sesión
// =============================================================================
require_once 'config.php';

startPortalSession();

// Si ya está logueado, redirigir
if (getSessionUser() !== null) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Usuario y contraseña son obligatorios.';
    } else {
        $result = loginToApi($username, $password);

        if (isset($result['token']) && isset($result['user'])) {
            setSessionUser($result['user'], $result['token']);
            header('Location: index.php');
            exit;
        } elseif (isset($result['error'])) {
            $error = $result['error'];
        } else {
            $error = 'Credenciales incorrectas o servicio no disponible.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar Sesión — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f0f2f5; display: flex; align-items: center; min-height: 100vh; }
        .login-card { max-width: 420px; width: 100%; }
    </style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col login-card">

            <div class="text-center mb-4">
                <i class="bi bi-file-earmark-check2 text-primary" style="font-size:3rem;"></i>
                <h4 class="fw-bold mt-2"><?= APP_NAME ?></h4>
                <p class="text-muted small">Acceso al Portal de Documentos</p>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">

                    <?php if ($error): ?>
                        <div class="alert alert-danger py-2 small">
                            <i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="post">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Usuario</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" name="username" class="form-control"
                                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                       placeholder="Tu nombre de usuario" autofocus required />
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" name="password" class="form-control"
                                       placeholder="••••••••" required />
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Iniciar Sesión
                        </button>
                    </form>

                </div>
            </div>

            <p class="text-center text-muted small mt-3">
                Autenticación segura vía API .NET
            </p>
        </div>
    </div>
</div>
</body>
</html>
