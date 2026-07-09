<?php
$segBasePath = $segBasePath ?? '';
$paginaActual = basename($_SERVER['PHP_SELF'] ?? '');
?>
<aside class="seg-sidebar">
    <span class="seg-sidebar-title">SEG GUERRERO</span>
    <a class="<?= $paginaActual === 'dashboard.php' ? 'active' : '' ?>" href="<?= htmlspecialchars($segBasePath . 'dashboard.php', ENT_QUOTES, 'UTF-8') ?>">Panel de Control</a>
    <a class="<?= $paginaActual === 'consolidacion.php' ? 'active' : '' ?>" href="<?= htmlspecialchars($segBasePath . 'consolidacion/consolidacion.php', ENT_QUOTES, 'UTF-8') ?>">Consolidación de Datos</a>
</aside>
