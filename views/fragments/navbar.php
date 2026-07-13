<?php
$segBasePath = $segBasePath ?? '';
?>
<nav class="seg-navbar">
    <a class="seg-brand" href="<?= htmlspecialchars($segBasePath . 'dashboard.php', ENT_QUOTES, 'UTF-8') ?>">
        <span class="seg-brand-mark">SEG</span>
        <span class="seg-brand-copy">
            <strong>Sistema Integral SEG</strong>
            <small>Secretaria de Educacion Guerrero</small>
        </span>
    </a>
    <div class="seg-navbar-actions">
        <span class="seg-status-dot"></span>
        <span class="seg-badge">Consolidacion Bilateral</span>
    </div>
</nav>
