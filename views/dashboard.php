<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/services/conexion.php';

$conexion = Conexion::conectar();
$segBasePath = '';

function dashboardCount(PDO $conexion, string $query): int
{
    return (int) $conexion->query($query)->fetchColumn();
}

$totalEscuelas = dashboardCount($conexion, 'SELECT COUNT(*) FROM escuelas');
$totalVinculos = dashboardCount($conexion, 'SELECT COUNT(*) FROM escuelas_rpu');
$rpusVinculados = dashboardCount($conexion, 'SELECT COUNT(DISTINCT RPU) FROM escuelas_rpu');
$avance = $totalEscuelas > 0 ? min(100, round($totalVinculos / $totalEscuelas * 100, 1)) : 0;
?>
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
            <span class="quick-icon"><i class="bi bi-building-check"></i></span>
            <div><strong><?= number_format($totalEscuelas) ?></strong><span>Escuelas insertadas</span></div>
            <small>Catalogo</small>
        </article>
        <article class="quick-card">
            <span class="quick-icon"><i class="bi bi-diagram-3"></i></span>
            <div><strong><?= number_format($totalVinculos) ?></strong><span>Vinculos guardados</span></div>
            <small>RPU-CCT</small>
        </article>
        <article class="quick-card">
            <span class="quick-icon"><i class="bi bi-speedometer2"></i></span>
            <div><strong><?= number_format($avance, 1) ?>%</strong><span>Avance de vinculacion</span></div>
            <small><?= number_format($rpusVinculados) ?> RPU</small>
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
                <div><b>4</b><span><strong>Audita ajustes</strong><small>Detecta cobros no bimestrales y cargos atipicos.</small></span></div>
            </div>
        </article>
        <article class="panel-card">
            <div class="panel-head">
                <div><span class="eyebrow">MODULOS</span><h2>Accesos principales</h2></div>
            </div>
            <div class="module-list">
                <a href="consolidacion/consolidacion.php"><i class="bi bi-lightning-charge"></i><span><strong>Consolidacion Masiva</strong><small>Cruce predictivo y vinculacion.</small></span></a>
                <a href="importaciones.php"><i class="bi bi-table"></i><span><strong>Importaciones</strong><small>Resumen de tablas locales.</small></span></a>
                <a href="ajustes.php"><i class="bi bi-exclamation-diamond"></i><span><strong>Ajustes CFE</strong><small>Auditoria de cobros y periodos.</small></span></a>
            </div>
        </article>
    </section>
</main>
</body>
</html>
