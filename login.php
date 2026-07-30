<?php

declare(strict_types=1);

require_once __DIR__ . '/services/auth.php';

segStartSession();
if (segIsLoggedIn()) {
    header('Location: views/dashboard.php');
    exit;
}

$mensajeError = (string) ($_SESSION['seg_login_error'] ?? '');
unset($_SESSION['seg_login_error']);
$csrf = segCsrfToken();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SIEE Guerrero | Acceso</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="views/css/login.css" rel="stylesheet">
</head>
<body>
    <main class="login-shell">
        <header class="login-brand-header">
            <img class="login-seg-logo" src="assets/img/logoSeg.png" alt="Secretaria de Educacion Guerrero">
            <span class="login-brand-divider" aria-hidden="true"></span>
            <img class="login-siee-logo" src="assets/img/logoSIEE.png" alt="SIEE Guerrero, Sistema Inteligente de Energia Educativa">
        </header>
        <section class="login-panel" aria-labelledby="login-title">
            <div class="login-panel-mark"><i class="bi bi-shield-lock"></i></div>
            <div class="login-heading">
                <span>Acceso institucional</span>
                <h1 id="login-title">Iniciar sesion</h1>
                <p>Ingresa con las credenciales asignadas para continuar.</p>
            </div>
            <?php if ($mensajeError !== ''): ?>
                <div class="login-error" role="alert"><i class="bi bi-exclamation-circle"></i><?= htmlspecialchars($mensajeError, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <form method="post" action="controllers/authController.php" class="login-form">
                <input type="hidden" name="accion" value="iniciar_sesion">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <label for="usuario">Usuario</label>
                <div class="login-field"><i class="bi bi-person"></i><input id="usuario" name="usuario" type="text" autocomplete="username" required autofocus></div>
                <label for="contrasena">Contrasena</label>
                <div class="login-field"><i class="bi bi-key"></i><input id="contrasena" name="contrasena" type="password" autocomplete="current-password" required></div>
                <button type="submit"><i class="bi bi-box-arrow-in-right"></i>Ingresar al sistema</button>
            </form>
            <p class="login-footer"><i class="bi bi-lock-fill"></i>Acceso exclusivo para personal autorizado</p>
        </section>
    </main>
</body>
</html>
