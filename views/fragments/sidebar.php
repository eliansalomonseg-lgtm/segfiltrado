<?php
$segBasePath = $segBasePath ?? '';
$paginaActual = basename($_SERVER['PHP_SELF'] ?? '');
?>
<aside class="seg-sidebar">
    <div class="seg-sidebar-head">
        <span class="seg-sidebar-title">SEG GUERRERO</span>
        <strong>Consolidacion escolar</strong>
        <small>Padron, RPU y catastro educativo</small>
    </div>
    <nav class="seg-menu" aria-label="Navegacion principal">
        <a class="<?= $paginaActual === 'dashboard.php' ? 'active' : '' ?>" href="<?= htmlspecialchars($segBasePath . 'dashboard.php', ENT_QUOTES, 'UTF-8') ?>">
            <span class="seg-menu-icon">PC</span>
            <span><strong>Panel de Control</strong><small>Resumen operativo</small></span>
        </a>
        <a class="<?= $paginaActual === 'consolidacion.php' ? 'active' : '' ?>" href="<?= htmlspecialchars($segBasePath . 'consolidacion/consolidacion.php', ENT_QUOTES, 'UTF-8') ?>">
            <span class="seg-menu-icon">CD</span>
            <span><strong>Consolidacion de Datos</strong><small>RPU, CCT y catalogos</small></span>
        </a>
    </nav>
    <div class="seg-sidebar-foot">
        <span>Base local SEG</span>
        <strong>Produccion</strong>
    </div>
</aside>
