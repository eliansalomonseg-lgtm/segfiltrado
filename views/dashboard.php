<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel de Control | SEG Guerrero</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/seg-executive.css" rel="stylesheet">
</head>
<body>
<?php $segBasePath = ''; ?>
<?php include_once __DIR__ . '/fragments/navbar.php'; ?>
<?php include_once __DIR__ . '/fragments/sidebar.php'; ?>
<main class="content">
    <section class="heading">
        <div>
            <span class="eyebrow">SISTEMA INTEGRAL SEG</span>
            <h1>Panel de Control</h1>
            <p>Administra la consolidacion de escuelas, medidores RPU y catalogos institucionales de la base seg.</p>
        </div>
        <a class="btn-seg compact-action" href="consolidacion/consolidacion.php">Iniciar consolidacion</a>
    </section>
    <section class="quick-actions">
        <article class="quick-card">
            <span class="quick-icon"><i class="bi bi-file-earmark-spreadsheet"></i></span>
            <div><strong>4</strong><span>Archivos de trabajo</span></div>
            <small>SEG/CFE</small>
        </article>
        <article class="quick-card">
            <span class="quick-icon"><i class="bi bi-diagram-3"></i></span>
            <div><strong>RPU-CCT</strong><span>Vinculacion controlada</span></div>
            <small>Padron</small>
        </article>
        <article class="quick-card">
            <span class="quick-icon"><i class="bi bi-shield-check"></i></span>
            <div><strong>Publico</strong><span>Filtro de control oficial</span></div>
            <small>Validacion</small>
        </article>
    </section>
    <section class="dashboard-grid">
        <article class="panel-card">
            <div class="panel-head">
                <div><span class="eyebrow">FLUJO</span><h2>Proceso operativo</h2></div>
            </div>
            <div class="process-list">
                <div><b>1</b><span><strong>Sincroniza catalogos</strong><small>Carga SEG y Oficializacion para poblar escuelas.</small></span></div>
                <div><b>2</b><span><strong>Procesa reportes CFE</strong><small>Analiza uno o dos periodos de recibos.</small></span></div>
                <div><b>3</b><span><strong>Confirma vinculos</strong><small>Guarda coincidencias seguras o revisadas.</small></span></div>
            </div>
        </article>
        <article class="panel-card">
            <div class="panel-head">
                <div><span class="eyebrow">MODULOS</span><h2>Accesos principales</h2></div>
            </div>
            <div class="module-list">
                <a href="consolidacion/consolidacion.php"><i class="bi bi-lightning-charge"></i><span><strong>Consolidacion Masiva</strong><small>Cruce predictivo y vinculacion.</small></span></a>
                <a href="importaciones.php"><i class="bi bi-table"></i><span><strong>Importaciones</strong><small>Resumen de tablas locales.</small></span></a>
            </div>
        </article>
    </section>
</main>
</body>
</html>
