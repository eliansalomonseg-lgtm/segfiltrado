<?php
$usuarioActual = segCurrentUser();
?>
<div class="seg-topline">
    <div class="seg-topline-inner">
        <span class="seg-topline-label">Secretaria de Educacion Guerrero</span>
        <div class="seg-user-session">
            <span><i class="bi bi-person-circle"></i><?= htmlspecialchars((string) ($usuarioActual['nombre'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
            <form method="post" action="<?= htmlspecialchars(($segBasePath ?? '') . '../controllers/authController.php', ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="accion" value="cerrar_sesion">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(segCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit"><i class="bi bi-box-arrow-right"></i>Cerrar sesion</button>
            </form>
        </div>
    </div>
</div>
