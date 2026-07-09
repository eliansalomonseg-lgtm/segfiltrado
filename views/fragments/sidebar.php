<?php
$paginaActual = basename($_SERVER['PHP_SELF'] ?? '');
?>
<aside class="seg-sidebar">
    <span class="seg-sidebar-title">SEG GUERRERO</span>
    <a class="<?= $paginaActual === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php">Panel de Control</a>
    <a class="<?= $paginaActual === 'consolidacion.php' ? 'active' : '' ?>" href="consolidacion.php">Consolidación de Datos</a>
</aside>
