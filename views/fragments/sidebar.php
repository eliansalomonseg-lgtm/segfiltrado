<?php
$segBasePath = $segBasePath ?? '';
$paginaActual = basename($_SERVER['PHP_SELF'] ?? '');
$logoPath = $segBasePath . '../assets/img/logoSeg.png';
$logoSieePath = $segBasePath . '../assets/img/logoSIEE.png';
$esAdmin = segIsAdmin();
?>
<header class="seg-institutional-header">
    <a class="seg-institutional-logo" href="<?= htmlspecialchars($segBasePath . 'dashboard.php', ENT_QUOTES, 'UTF-8') ?>">
        <img src="<?= htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8') ?>" alt="Secretaria de Educacion Guerrero">
    </a>
    <a class="seg-system-lockup" href="<?= htmlspecialchars($segBasePath . 'dashboard.php', ENT_QUOTES, 'UTF-8') ?>">
        <img src="<?= htmlspecialchars($logoSieePath, ENT_QUOTES, 'UTF-8') ?>" alt="SIEE Guerrero, Sistema Inteligente de Energia Educativa">
    </a>
</header>
<nav class="seg-primary-nav" aria-label="Navegacion principal">
    <div class="seg-primary-nav-inner">
        <a class="<?= $paginaActual === 'dashboard.php' ? 'active' : '' ?>" href="<?= htmlspecialchars($segBasePath . 'dashboard.php', ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-house-door"></i>Inicio</a>
        <?php if ($esAdmin): ?>
            <a class="<?= $paginaActual === 'consolidacion.php' ? 'active' : '' ?>" href="<?= htmlspecialchars($segBasePath . 'consolidacion/consolidacion.php', ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-lightning-charge"></i>Consolidacion</a>
            <a class="<?= $paginaActual === 'importaciones.php' ? 'active' : '' ?>" href="<?= htmlspecialchars($segBasePath . 'importaciones.php', ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-table"></i>Padron de vinculos</a>
            <a class="<?= $paginaActual === 'exportacion_rpus.php' ? 'active' : '' ?>" href="<?= htmlspecialchars($segBasePath . 'exportacion_rpus.php', ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-file-earmark-spreadsheet"></i>Exportar RPUs</a>
            <a class="<?= $paginaActual === 'usuarios.php' ? 'active' : '' ?>" href="<?= htmlspecialchars($segBasePath . 'usuarios.php', ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-people"></i>Usuarios</a>
        <?php endif; ?>
        <a class="<?= $paginaActual === 'rpus.php' ? 'active' : '' ?>" href="<?= htmlspecialchars($segBasePath . 'rpus.php', ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-search"></i>Consulta RPU</a>
        <a class="<?= $paginaActual === 'mapa_rpus.php' ? 'active' : '' ?>" href="<?= htmlspecialchars($segBasePath . 'mapa_rpus.php', ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-buildings"></i>RPUs con escuelas</a>
        <a class="<?= $paginaActual === 'ajustes.php' ? 'active' : '' ?>" href="<?= htmlspecialchars($segBasePath . 'ajustes.php', ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-clipboard2-pulse"></i>Reportes CFE</a>
    </div>
</nav>
