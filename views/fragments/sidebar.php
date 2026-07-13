<?php
$segBasePath = $segBasePath ?? '';
$paginaActual = basename($_SERVER['PHP_SELF'] ?? '');
$logoPath = $segBasePath . '../assets/img/logoSeg.png';
?>
<aside class="seg-sidebar">
    <a class="seg-logo-box" href="<?= htmlspecialchars($segBasePath . 'dashboard.php', ENT_QUOTES, 'UTF-8') ?>">
        <img src="<?= htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8') ?>" alt="Logo Secretaria de Educacion Guerrero" onload="this.nextElementSibling.hidden=true" onerror="this.style.display='none'">
        <span>SEG Guerrero</span>
    </a>
    <nav class="seg-menu" aria-label="Navegacion principal">
        <a class="<?= $paginaActual === 'dashboard.php' ? 'active' : '' ?>" href="<?= htmlspecialchars($segBasePath . 'dashboard.php', ENT_QUOTES, 'UTF-8') ?>">
            <i class="bi bi-bar-chart-line"></i>
            <span>Dashboard</span>
            <small>Resumen General</small>
        </a>
        <a class="<?= $paginaActual === 'consolidacion.php' ? 'active' : '' ?>" href="<?= htmlspecialchars($segBasePath . 'consolidacion/consolidacion.php', ENT_QUOTES, 'UTF-8') ?>">
            <i class="bi bi-lightning-charge"></i>
            <span>Consolidacion Masiva</span>
            <small>4 archivos</small>
        </a>
        <a class="<?= $paginaActual === 'importaciones.php' ? 'active' : '' ?>" href="<?= htmlspecialchars($segBasePath . 'importaciones.php', ENT_QUOTES, 'UTF-8') ?>">
            <i class="bi bi-table"></i>
            <span>Importaciones</span>
            <small>Tablas cargadas</small>
        </a>
        <a class="<?= $paginaActual === 'ajustes.php' ? 'active' : '' ?>" href="<?= htmlspecialchars($segBasePath . 'ajustes.php', ENT_QUOTES, 'UTF-8') ?>">
            <i class="bi bi-exclamation-diamond"></i>
            <span>Ajustes CFE</span>
            <small>Cobros atipicos</small>
        </a>
    </nav>
    <div class="seg-sidebar-foot">
        <span>Secretaria de Educacion Guerrero</span>
        <strong>Produccion local</strong>
    </div>
</aside>
