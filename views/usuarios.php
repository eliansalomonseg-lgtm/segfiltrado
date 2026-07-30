<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/services/auth.php';
require_once dirname(__DIR__) . '/services/conexion.php';

segRequireAdmin('../dashboard.php');

$segBasePath = '';
$conexion = Conexion::conectar();
$usuarios = $conexion->query(
    'SELECT u.id, u.usuario, u.nombre, u.apellido_paterno, u.apellido_materno, u.activo, u.creado_en, r.nombre AS rol
     FROM usuarios u
     INNER JOIN usuario_roles ur ON ur.usuario_id = u.id
     INNER JOIN roles r ON r.id = ur.rol_id
     ORDER BY u.creado_en DESC, u.id DESC'
)->fetchAll();
$csrf = segCsrfToken();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <title>Usuarios | SEG Guerrero</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/seg-executive.css" rel="stylesheet">
</head>
<body>
<?php include_once __DIR__ . '/fragments/navbar.php'; ?>
<?php include_once __DIR__ . '/fragments/sidebar.php'; ?>
<main class="content user-admin-view">
    <section class="heading">
        <div>
            <span class="eyebrow">ADMINISTRACION</span>
            <h1>Usuarios y accesos</h1>
            <p>Crea cuentas para consultar o administrar la plataforma institucional.</p>
        </div>
        <span class="alert-gold"><i class="bi bi-shield-lock"></i>Solo administradores</span>
    </section>

    <section class="user-admin-grid">
        <article class="results-card user-form-card">
            <div class="results-head">
                <div><span class="eyebrow">NUEVA CUENTA</span><h2>Agregar usuario</h2></div>
            </div>
            <form id="user-create-form" class="user-create-form" autocomplete="off">
                <input type="hidden" name="accion" value="crear_usuario">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                <label>Nombre<input name="nombre" type="text" maxlength="100" required></label>
                <label>Apellido paterno<input name="apellido_paterno" type="text" maxlength="100" required></label>
                <label>Apellido materno<input name="apellido_materno" type="text" maxlength="100"></label>
                <label>Usuario<input name="usuario" type="text" maxlength="60" autocomplete="off" required></label>
                <label>Contrasena<input name="contrasena" type="password" minlength="8" autocomplete="new-password" required></label>
                <label>Rol<select name="rol" required><option value="consultor">Consultor</option><option value="admin">Administrador</option></select></label>
                <button class="btn-seg" type="submit"><i class="bi bi-person-plus me-2"></i>Crear usuario</button>
                <p id="user-create-status" class="user-create-status" aria-live="polite"></p>
            </form>
        </article>

        <article class="results-card user-list-card">
            <div class="results-head">
                <div><span class="eyebrow">CUENTAS REGISTRADAS</span><h2><?= count($usuarios) ?> usuarios</h2></div>
            </div>
            <div class="user-table-wrap">
                <table class="user-table">
                    <thead><tr><th>Usuario</th><th>Nombre</th><th>Rol</th><th>Estado</th><th>Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($usuarios as $usuario): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars((string) $usuario['usuario'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                            <td><?= htmlspecialchars(trim((string) $usuario['nombre'] . ' ' . $usuario['apellido_paterno'] . ' ' . $usuario['apellido_materno']), ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="user-role <?= $usuario['rol'] === 'admin' ? 'admin' : 'consultor' ?>"><?= htmlspecialchars((string) $usuario['rol'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><span class="user-state <?= (int) $usuario['activo'] === 1 ? 'active' : '' ?>"><?= (int) $usuario['activo'] === 1 ? 'Activo' : 'Inactivo' ?></span></td>
                            <td class="user-actions">
                                <button type="button" class="user-icon-button edit-user" title="Editar usuario" aria-label="Editar usuario" data-user="<?= htmlspecialchars((string) json_encode($usuario, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-pencil-square"></i></button>
                                <button type="button" class="user-icon-button delete-user" title="Eliminar usuario" aria-label="Eliminar usuario" data-user-id="<?= (int) $usuario['id'] ?>" data-user-name="<?= htmlspecialchars((string) $usuario['usuario'], ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-trash3"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </article>
    </section>
</main>
<div id="user-edit-modal" class="user-modal" aria-hidden="true">
    <div class="user-modal-backdrop" data-close-user-modal></div>
    <section class="user-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="user-edit-title">
        <div class="user-modal-head">
            <div><span class="eyebrow">EDICION</span><h2 id="user-edit-title">Editar usuario</h2></div>
            <button type="button" class="user-modal-close" data-close-user-modal aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
        </div>
        <form id="user-edit-form" class="user-create-form" autocomplete="off">
            <input type="hidden" name="accion" value="actualizar_usuario">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="usuario_id" value="">
            <div class="user-form-columns">
                <label>Nombre<input name="nombre" type="text" maxlength="100" required></label>
                <label>Apellido paterno<input name="apellido_paterno" type="text" maxlength="100" required></label>
                <label>Apellido materno<input name="apellido_materno" type="text" maxlength="100"></label>
                <label>Usuario<input name="usuario" type="text" maxlength="60" required></label>
                <label>Rol<select name="rol" required><option value="consultor">Consultor</option><option value="admin">Administrador</option></select></label>
                <label>Estado<select name="activo" required><option value="1">Activo</option><option value="0">Inactivo</option></select></label>
            </div>
            <label>Nueva contrasena <small>Deja este campo vacio para conservar la actual.</small><input name="contrasena" type="password" minlength="8" autocomplete="new-password"></label>
            <button class="btn-seg" type="submit"><i class="bi bi-check2-circle me-2"></i>Guardar cambios</button>
            <p id="user-edit-status" class="user-create-status" aria-live="polite"></p>
        </form>
    </section>
</div>
<script>
const userCreateForm = document.getElementById('user-create-form');
const userCreateStatus = document.getElementById('user-create-status');
const userEditForm = document.getElementById('user-edit-form');
const userEditStatus = document.getElementById('user-edit-status');
const userEditModal = document.getElementById('user-edit-modal');

async function sendUserForm(form, status) {
    const submitButton = form.querySelector('button[type="submit"]');
    submitButton.disabled = true;
    status.className = 'user-create-status';
    status.textContent = 'Guardando cambios...';
    try {
        const response = await fetch('../controllers/authController.php', { method: 'POST', body: new FormData(form) });
        const data = await response.json();
        if (!response.ok || !data.ok) throw new Error(data.error || 'No fue posible guardar el usuario.');
        status.className = 'user-create-status success';
        status.textContent = data.mensaje;
        window.setTimeout(() => window.location.reload(), 700);
    } catch (error) {
        status.className = 'user-create-status error';
        status.textContent = error.message;
        submitButton.disabled = false;
    }
}

userCreateForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    await sendUserForm(userCreateForm, userCreateStatus);
});

userEditForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    await sendUserForm(userEditForm, userEditStatus);
});

document.querySelectorAll('.edit-user').forEach((button) => {
    button.addEventListener('click', () => {
        const user = JSON.parse(button.dataset.user);
        userEditForm.elements.usuario_id.value = user.id;
        userEditForm.elements.nombre.value = user.nombre || '';
        userEditForm.elements.apellido_paterno.value = user.apellido_paterno || '';
        userEditForm.elements.apellido_materno.value = user.apellido_materno || '';
        userEditForm.elements.usuario.value = user.usuario || '';
        userEditForm.elements.rol.value = user.rol || 'consultor';
        userEditForm.elements.activo.value = String(user.activo || 0);
        userEditForm.elements.contrasena.value = '';
        userEditStatus.textContent = '';
        userEditModal.classList.add('is-open');
        userEditModal.setAttribute('aria-hidden', 'false');
    });
});

document.querySelectorAll('[data-close-user-modal]').forEach((button) => {
    button.addEventListener('click', () => {
        userEditModal.classList.remove('is-open');
        userEditModal.setAttribute('aria-hidden', 'true');
    });
});

document.querySelectorAll('.delete-user').forEach((button) => {
    button.addEventListener('click', async () => {
        if (!window.confirm(`Eliminar al usuario ${button.dataset.userName}? Esta accion no se puede deshacer.`)) return;
        button.disabled = true;
        try {
            const body = new URLSearchParams({ accion: 'eliminar_usuario', csrf: document.querySelector('meta[name="csrf-token"]').content, usuario_id: button.dataset.userId });
            const response = await fetch('../controllers/authController.php', { method: 'POST', body });
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.error || 'No fue posible eliminar el usuario.');
            window.location.reload();
        } catch (error) {
            window.alert(error.message);
            button.disabled = false;
        }
    });
});
</script>
</body>
</html>
