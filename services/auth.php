<?php

declare(strict_types=1);

function segStartSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function segCsrfToken(): string
{
    segStartSession();
    if (empty($_SESSION['seg_csrf'])) {
        $_SESSION['seg_csrf'] = bin2hex(random_bytes(24));
    }
    return (string) $_SESSION['seg_csrf'];
}

function segCurrentUser(): ?array
{
    segStartSession();
    $usuario = $_SESSION['seg_usuario'] ?? null;
    return is_array($usuario) ? $usuario : null;
}

function segIsLoggedIn(): bool
{
    return segCurrentUser() !== null;
}

function segIsAdmin(): bool
{
    return (segCurrentUser()['rol'] ?? '') === 'admin';
}

function segLogin(array $usuario): void
{
    segStartSession();
    session_regenerate_id(true);
    $_SESSION['seg_usuario'] = [
        'id' => (int) $usuario['id'],
        'usuario' => (string) $usuario['usuario'],
        'nombre' => trim((string) $usuario['nombre'] . ' ' . (string) $usuario['apellido_paterno']),
        'rol' => (string) $usuario['rol']
    ];
    segCsrfToken();
}

function segLogout(): void
{
    segStartSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $parametros = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $parametros['path'], $parametros['domain'], $parametros['secure'], $parametros['httponly']);
    }
    session_destroy();
}

function segRequireLogin(?string $redirectTo = null): void
{
    if (segIsLoggedIn()) {
        return;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Inicia sesion para continuar.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: ' . ($redirectTo ?? '/seg/login.php'));
    exit;
}

function segRequireAdmin(?string $redirectTo = null): void
{
    segRequireLogin($redirectTo);
    if (segIsAdmin()) {
        return;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'No tienes permiso para realizar esta accion.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: ' . ($redirectTo ?? '/seg/views/dashboard.php'));
    exit;
}
