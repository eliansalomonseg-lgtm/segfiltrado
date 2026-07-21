<?php
$segBasePath = $segBasePath ?? '';
$paginaActual = basename($_SERVER['PHP_SELF'] ?? '');
$logoPath = $segBasePath . '../assets/img/logoSeg.png';
?>
<aside class="seg-sidebar" id="seg-sidebar">
    <button class="seg-sidebar-toggle" id="seg-sidebar-toggle" type="button" aria-label="Alternar sidebar">
        <i class="bi bi-chevron-left"></i>
    </button>
    <a class="seg-logo-box" href="<?= htmlspecialchars($segBasePath . 'dashboard.php', ENT_QUOTES, 'UTF-8') ?>">
        <img src="<?= htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8') ?>" alt="Logo Secretaria de Educacion Guerrero" onload="this.nextElementSibling.hidden=true" onerror="this.style.display='none'">
        <span>SEG Guerrero</span>
    </a>
    <nav class="seg-menu" aria-label="Navegacion principal">
        <a class="<?= $paginaActual === 'dashboard.php' ? 'active' : '' ?>" href="<?= htmlspecialchars($segBasePath . 'dashboard.php', ENT_QUOTES, 'UTF-8') ?>" data-tooltip="Dashboard">
            <i class="bi bi-bar-chart-line"></i>
            <span>Dashboard</span>
            <small>Resumen General</small>
        </a>
        <a class="<?= $paginaActual === 'consolidacion.php' ? 'active' : '' ?>" href="<?= htmlspecialchars($segBasePath . 'consolidacion/consolidacion.php', ENT_QUOTES, 'UTF-8') ?>" data-tooltip="Consolidacion Masiva">
            <i class="bi bi-lightning-charge"></i>
            <span>Consolidacion Masiva</span>
            <small>4 archivos</small>
        </a>
        <a class="<?= $paginaActual === 'importaciones.php' ? 'active' : '' ?>" href="<?= htmlspecialchars($segBasePath . 'importaciones.php', ENT_QUOTES, 'UTF-8') ?>" data-tooltip="Importaciones">
            <i class="bi bi-table"></i>
            <span>Importaciones</span>
            <small>Tablas cargadas</small>
        </a>
        <a class="<?= $paginaActual === 'rpus.php' ? 'active' : '' ?>" href="<?= htmlspecialchars($segBasePath . 'rpus.php', ENT_QUOTES, 'UTF-8') ?>" data-tooltip="Expediente RPU">
            <i class="bi bi-pin-map"></i>
            <span>Expediente RPU</span>
            <small>Mapa e historial</small>
        </a>
        <a class="<?= $paginaActual === 'ajustes.php' ? 'active' : '' ?>" href="<?= htmlspecialchars($segBasePath . 'ajustes.php', ENT_QUOTES, 'UTF-8') ?>" data-tooltip="Ajustes CFE">
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
<div class="seg-sidebar-overlay" id="seg-sidebar-overlay"></div>
<script>
(function() {
    const sidebar = document.getElementById('seg-sidebar');
    const toggle = document.getElementById('seg-sidebar-toggle');
    const overlay = document.getElementById('seg-sidebar-overlay');
    const STORAGE_KEY = 'seg_sidebar_collapsed';
    const isMobile = () => window.innerWidth <= 900;

    function applyState(collapsed) {
        sidebar.classList.toggle('collapsed', collapsed);
        document.body.classList.toggle('sidebar-collapsed', collapsed);
        document.body.classList.remove('sidebar-mobile-open');
    }

    function init() {
        if (isMobile()) {
            sidebar.classList.add('collapsed');
            document.body.classList.add('sidebar-collapsed');
        } else {
            const saved = localStorage.getItem(STORAGE_KEY);
            applyState(saved === 'true');
        }
    }

    toggle.addEventListener('click', function() {
        if (isMobile()) {
            const isOpen = document.body.classList.contains('sidebar-mobile-open');
            document.body.classList.toggle('sidebar-mobile-open', !isOpen);
        } else {
            const willCollapse = !sidebar.classList.contains('collapsed');
            applyState(willCollapse);
            localStorage.setItem(STORAGE_KEY, willCollapse);
        }
    });

    overlay.addEventListener('click', function() {
        document.body.classList.remove('sidebar-mobile-open');
    });

    window.addEventListener('resize', function() {
        if (isMobile()) {
            document.body.classList.remove('sidebar-mobile-open');
        } else {
            const saved = localStorage.getItem(STORAGE_KEY);
            applyState(saved === 'true');
        }
    });

    init();
})();
</script>
