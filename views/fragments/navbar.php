<?php
$segBasePath = $segBasePath ?? '';
?>
<nav class="seg-navbar">
    <div class="seg-navbar-left">
        <button class="seg-navbar-burger" id="seg-navbar-burger" type="button" aria-label="Menu">
            <i class="bi bi-list"></i>
        </button>
        <div class="seg-navbar-title">
            <span>SEG GUERRERO</span>
            <strong>Sistema de Consolidacion Educativa</strong>
        </div>
    </div>
    <div class="seg-navbar-actions">
        <span class="seg-status-dot"></span>
        <span class="seg-badge">Base local seg</span>
    </div>
</nav>
<script>
(function() {
    const burger = document.getElementById('seg-navbar-burger');
    if (burger) {
        burger.addEventListener('click', function() {
            const toggle = document.getElementById('seg-sidebar-toggle');
            if (toggle) toggle.click();
        });
    }
})();
</script>
