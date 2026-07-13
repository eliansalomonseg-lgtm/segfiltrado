<?php
$segBasePath = $segBasePath ?? '';
$paginaActual = basename($_SERVER['PHP_SELF'] ?? '');
$logoPath = $segBasePath . '../assets/img/logo_seg.png';
?>
<aside class="seg-sidebar">
    <a class="seg-logo-box" href="<?= htmlspecialchars($segBasePath . 'dashboard.php', ENT_QUOTES, 'UTF-8') ?>">
        <img src="<?= htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8') ?>" alt="Logo Secretaria de Educacion Guerrero" onerror="this.style.display='none'">
        <span>LogoHere</span>
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
        <a href="#">
            <i class="bi bi-search"></i>
            <span>Auditoria de Ajustes</span>
            <small>Consumo y directores</small>
        </a>
        <a href="#">
            <i class="bi bi-gear"></i>
            <span>Configuracion</span>
            <small>Parametros del sistema</small>
        </a>
    </nav>
    <div class="seg-sidebar-foot">
        <span>Secretaria de Educacion Guerrero</span>
        <strong>Produccion local</strong>
    </div>
</aside>
