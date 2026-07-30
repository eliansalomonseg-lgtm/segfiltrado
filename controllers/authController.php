<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/services/auth.php';
require_once dirname(__DIR__) . '/services/conexion.php';

segStartSession();

function volverLogin(string $mensaje = ''): never
{
    if ($mensaje !== '') {
        $_SESSION['seg_login_error'] = $mensaje;
    }
    header('Location: ../login.php');
    exit;
}

function responderJson(array $datos, int $estado = 200): never
{
    http_response_code($estado);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php');
    exit;
}

$accion = $_POST['accion'] ?? '';

if ($accion === 'cerrar_sesion') {
    if (!hash_equals(segCsrfToken(), (string) ($_POST['csrf'] ?? ''))) {
        volverLogin('La solicitud de cierre no es valida.');
    }
    segLogout();
    header('Location: ../login.php');
    exit;
}

if ($accion === 'crear_usuario') {
    segRequireAdmin();
    if (!hash_equals(segCsrfToken(), (string) ($_POST['csrf'] ?? ''))) {
        responderJson(['ok' => false, 'error' => 'La sesion de seguridad no es valida.'], 419);
    }

    $nombre = trim((string) ($_POST['nombre'] ?? ''));
    $apellidoPaterno = trim((string) ($_POST['apellido_paterno'] ?? ''));
    $apellidoMaterno = trim((string) ($_POST['apellido_materno'] ?? ''));
    $usuario = trim((string) ($_POST['usuario'] ?? ''));
    $contrasena = (string) ($_POST['contrasena'] ?? '');
    $rol = trim((string) ($_POST['rol'] ?? 'consultor'));

    if (mb_strlen($nombre) < 2 || mb_strlen($apellidoPaterno) < 2) {
        responderJson(['ok' => false, 'error' => 'Captura nombre y apellido paterno.'], 422);
    }
    if (!preg_match('/^[A-Za-z0-9._-]{3,60}$/', $usuario)) {
        responderJson(['ok' => false, 'error' => 'El usuario debe tener de 3 a 60 caracteres: letras, numeros, punto, guion o guion bajo.'], 422);
    }
    if (strlen($contrasena) < 8) {
        responderJson(['ok' => false, 'error' => 'La contrasena debe tener al menos 8 caracteres.'], 422);
    }
    if (!in_array($rol, ['admin', 'consultor'], true)) {
        responderJson(['ok' => false, 'error' => 'Selecciona un rol valido.'], 422);
    }
    try {
        $conexion = Conexion::conectar();
        $consultaRol = $conexion->prepare('SELECT id FROM roles WHERE nombre = ? LIMIT 1');
        $consultaRol->execute([$rol]);
        $rolId = $consultaRol->fetchColumn();
        if (!$rolId) {
            responderJson(['ok' => false, 'error' => 'El rol seleccionado no esta disponible.'], 422);
        }

        $conexion->beginTransaction();
        $insertarUsuario = $conexion->prepare(
            'INSERT INTO usuarios (usuario, contrasena_hash, nombre, apellido_paterno, apellido_materno)
             VALUES (?, ?, ?, ?, ?)'
        );
        $insertarUsuario->execute([
            $usuario,
            password_hash($contrasena, PASSWORD_DEFAULT),
            $nombre,
            $apellidoPaterno,
            $apellidoMaterno !== '' ? $apellidoMaterno : null
        ]);
        $insertarRol = $conexion->prepare('INSERT INTO usuario_roles (usuario_id, rol_id) VALUES (?, ?)');
        $insertarRol->execute([(int) $conexion->lastInsertId(), (int) $rolId]);
        $conexion->commit();
        responderJson(['ok' => true, 'mensaje' => 'Usuario creado correctamente.']);
    } catch (PDOException $e) {
        if (isset($conexion) && $conexion->inTransaction()) {
            $conexion->rollBack();
        }
        if ($e->getCode() === '23000') {
            responderJson(['ok' => false, 'error' => 'Ese nombre de usuario ya existe.'], 422);
        }
        responderJson(['ok' => false, 'error' => 'No fue posible crear el usuario.'], 500);
    } catch (Throwable) {
        if (isset($conexion) && $conexion->inTransaction()) {
            $conexion->rollBack();
        }
        responderJson(['ok' => false, 'error' => 'No fue posible crear el usuario.'], 500);
    }
}

if ($accion === 'actualizar_usuario') {
    segRequireAdmin();
    if (!hash_equals(segCsrfToken(), (string) ($_POST['csrf'] ?? ''))) {
        responderJson(['ok' => false, 'error' => 'La sesion de seguridad no es valida.'], 419);
    }

    $usuarioId = (int) ($_POST['usuario_id'] ?? 0);
    $nombre = trim((string) ($_POST['nombre'] ?? ''));
    $apellidoPaterno = trim((string) ($_POST['apellido_paterno'] ?? ''));
    $apellidoMaterno = trim((string) ($_POST['apellido_materno'] ?? ''));
    $usuario = trim((string) ($_POST['usuario'] ?? ''));
    $contrasena = (string) ($_POST['contrasena'] ?? '');
    $rol = trim((string) ($_POST['rol'] ?? 'consultor'));
    $activo = (int) ($_POST['activo'] ?? 0) === 1 ? 1 : 0;

    if ($usuarioId < 1 || mb_strlen($nombre) < 2 || mb_strlen($apellidoPaterno) < 2) {
        responderJson(['ok' => false, 'error' => 'Completa los datos obligatorios del usuario.'], 422);
    }
    if (!preg_match('/^[A-Za-z0-9._-]{3,60}$/', $usuario)) {
        responderJson(['ok' => false, 'error' => 'El usuario debe tener de 3 a 60 caracteres: letras, numeros, punto, guion o guion bajo.'], 422);
    }
    if ($contrasena !== '' && strlen($contrasena) < 8) {
        responderJson(['ok' => false, 'error' => 'La nueva contrasena debe tener al menos 8 caracteres.'], 422);
    }
    if (!in_array($rol, ['admin', 'consultor'], true)) {
        responderJson(['ok' => false, 'error' => 'Selecciona un rol valido.'], 422);
    }
    if ($usuarioId === (int) (segCurrentUser()['id'] ?? 0) && ($rol !== 'admin' || $activo !== 1)) {
        responderJson(['ok' => false, 'error' => 'No puedes desactivar ni cambiar el rol de tu propia cuenta.'], 422);
    }

    try {
        $conexion = Conexion::conectar();
        $conexion->beginTransaction();
        $consultaUsuario = $conexion->prepare(
            'SELECT u.id, u.activo, r.nombre AS rol
             FROM usuarios u
             INNER JOIN usuario_roles ur ON ur.usuario_id = u.id
             INNER JOIN roles r ON r.id = ur.rol_id
             WHERE u.id = ?
             FOR UPDATE'
        );
        $consultaUsuario->execute([$usuarioId]);
        $registro = $consultaUsuario->fetch();
        if (!$registro) {
            $conexion->rollBack();
            responderJson(['ok' => false, 'error' => 'El usuario ya no existe.'], 404);
        }
        $consultaRol = $conexion->prepare('SELECT id FROM roles WHERE nombre = ? LIMIT 1');
        $consultaRol->execute([$rol]);
        $rolId = $consultaRol->fetchColumn();
        if (!$rolId) {
            $conexion->rollBack();
            responderJson(['ok' => false, 'error' => 'El rol seleccionado no esta disponible.'], 422);
        }

        $afectaAdmin = $registro['rol'] === 'admin' && ((int) $registro['activo'] === 1) && ($rol !== 'admin' || $activo !== 1);
        if ($afectaAdmin) {
            $adminsActivos = (int) $conexion->query(
                'SELECT COUNT(*) FROM usuarios u
                 INNER JOIN usuario_roles ur ON ur.usuario_id = u.id
                 INNER JOIN roles r ON r.id = ur.rol_id
                 WHERE r.nombre = "admin" AND u.activo = 1'
            )->fetchColumn();
            if ($adminsActivos <= 1) {
                $conexion->rollBack();
                responderJson(['ok' => false, 'error' => 'Debe permanecer al menos un administrador activo.'], 422);
            }
        }

        if ($contrasena !== '') {
            $actualizarUsuario = $conexion->prepare(
                'UPDATE usuarios SET usuario = ?, contrasena_hash = ?, nombre = ?, apellido_paterno = ?, apellido_materno = ?, activo = ? WHERE id = ?'
            );
            $actualizarUsuario->execute([$usuario, password_hash($contrasena, PASSWORD_DEFAULT), $nombre, $apellidoPaterno, $apellidoMaterno !== '' ? $apellidoMaterno : null, $activo, $usuarioId]);
        } else {
            $actualizarUsuario = $conexion->prepare(
                'UPDATE usuarios SET usuario = ?, nombre = ?, apellido_paterno = ?, apellido_materno = ?, activo = ? WHERE id = ?'
            );
            $actualizarUsuario->execute([$usuario, $nombre, $apellidoPaterno, $apellidoMaterno !== '' ? $apellidoMaterno : null, $activo, $usuarioId]);
        }
        $actualizarRol = $conexion->prepare('UPDATE usuario_roles SET rol_id = ? WHERE usuario_id = ?');
        $actualizarRol->execute([(int) $rolId, $usuarioId]);
        $conexion->commit();
        responderJson(['ok' => true, 'mensaje' => 'Usuario actualizado correctamente.']);
    } catch (PDOException $e) {
        if (isset($conexion) && $conexion->inTransaction()) {
            $conexion->rollBack();
        }
        if ($e->getCode() === '23000') {
            responderJson(['ok' => false, 'error' => 'Ese nombre de usuario ya existe.'], 422);
        }
        responderJson(['ok' => false, 'error' => 'No fue posible actualizar el usuario.'], 500);
    } catch (Throwable) {
        if (isset($conexion) && $conexion->inTransaction()) {
            $conexion->rollBack();
        }
        responderJson(['ok' => false, 'error' => 'No fue posible actualizar el usuario.'], 500);
    }
}

if ($accion === 'eliminar_usuario') {
    segRequireAdmin();
    if (!hash_equals(segCsrfToken(), (string) ($_POST['csrf'] ?? ''))) {
        responderJson(['ok' => false, 'error' => 'La sesion de seguridad no es valida.'], 419);
    }

    $usuarioId = (int) ($_POST['usuario_id'] ?? 0);
    if ($usuarioId < 1) {
        responderJson(['ok' => false, 'error' => 'Selecciona un usuario valido.'], 422);
    }
    if ($usuarioId === (int) (segCurrentUser()['id'] ?? 0)) {
        responderJson(['ok' => false, 'error' => 'No puedes eliminar tu propia cuenta.'], 422);
    }

    try {
        $conexion = Conexion::conectar();
        $conexion->beginTransaction();
        $consultaUsuario = $conexion->prepare(
            'SELECT u.id, u.activo, r.nombre AS rol
             FROM usuarios u
             INNER JOIN usuario_roles ur ON ur.usuario_id = u.id
             INNER JOIN roles r ON r.id = ur.rol_id
             WHERE u.id = ?
             FOR UPDATE'
        );
        $consultaUsuario->execute([$usuarioId]);
        $registro = $consultaUsuario->fetch();
        if (!$registro) {
            $conexion->rollBack();
            responderJson(['ok' => false, 'error' => 'El usuario ya no existe.'], 404);
        }
        if ($registro['rol'] === 'admin' && (int) $registro['activo'] === 1) {
            $adminsActivos = (int) $conexion->query(
                'SELECT COUNT(*) FROM usuarios u
                 INNER JOIN usuario_roles ur ON ur.usuario_id = u.id
                 INNER JOIN roles r ON r.id = ur.rol_id
                 WHERE r.nombre = "admin" AND u.activo = 1'
            )->fetchColumn();
            if ($adminsActivos <= 1) {
                $conexion->rollBack();
                responderJson(['ok' => false, 'error' => 'No puedes eliminar al ultimo administrador activo.'], 422);
            }
        }
        $eliminar = $conexion->prepare('DELETE FROM usuarios WHERE id = ?');
        $eliminar->execute([$usuarioId]);
        $conexion->commit();
        responderJson(['ok' => true, 'mensaje' => 'Usuario eliminado correctamente.']);
    } catch (Throwable) {
        if (isset($conexion) && $conexion->inTransaction()) {
            $conexion->rollBack();
        }
        responderJson(['ok' => false, 'error' => 'No fue posible eliminar el usuario.'], 500);
    }
}

if ($accion !== 'iniciar_sesion') {
    volverLogin('La accion solicitada no es valida.');
}

if (!hash_equals(segCsrfToken(), (string) ($_POST['csrf'] ?? ''))) {
    volverLogin('La sesion de seguridad no es valida.');
}

$usuario = trim((string) ($_POST['usuario'] ?? ''));
$contrasena = (string) ($_POST['contrasena'] ?? '');
if ($usuario === '' || $contrasena === '') {
    volverLogin('Escribe tu usuario y contrasena.');
}

$conexion = Conexion::conectar();
$consulta = $conexion->prepare(
    'SELECT u.id, u.usuario, u.contrasena_hash, u.nombre, u.apellido_paterno, r.nombre AS rol
     FROM usuarios u
     INNER JOIN usuario_roles ur ON ur.usuario_id = u.id
     INNER JOIN roles r ON r.id = ur.rol_id
     WHERE u.usuario = ? AND u.activo = 1
     ORDER BY r.nombre = "admin" DESC
     LIMIT 1'
);
$consulta->execute([$usuario]);
$registro = $consulta->fetch();

if (!$registro || !password_verify($contrasena, (string) $registro['contrasena_hash'])) {
    volverLogin('Usuario o contrasena incorrectos.');
}

segLogin($registro);
header('Location: ../views/dashboard.php');
exit;
