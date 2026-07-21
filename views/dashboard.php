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
$totalReportesCfe = dashboardCount($conexion, 'SELECT COUNT(*) FROM cfe_reportes');
$totalLecturasCfe = dashboardCount($conexion, 'SELECT COUNT(*) FROM cfe_consumos');
$casosCfe = dashboardCount($conexion, 'SELECT COUNT(*) FROM cfe_consumos WHERE severidad >= 3');
$ultimoReporte = $conexion->query('SELECT anio, mes FROM cfe_reportes ORDER BY anio DESC, mes DESC, id DESC LIMIT 1')->fetch();
$avance = $totalEscuelas > 0 ? min(100, round($totalVinculos / $totalEscuelas * 100, 1)) : 0;
$meses = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];
$historialMensual = $conexion->query(
    'SELECT anio, mes, SUM(importe_total) AS total_pagado, SUM(ajuste_muchos_dias) AS ajustes
     FROM cfe_reportes
     GROUP BY anio, mes
     ORDER BY anio ASC, mes ASC'
)->fetchAll(PDO::FETCH_ASSOC);
$etiquetasMensuales = [];
$pagosMensuales = [];
$ajustesMensuales = [];
$mesMayorPago = null;
$mesMayorAjustes = null;
foreach ($historialMensual as $registroMensual) {
    $etiqueta = ($meses[(int) $registroMensual['mes']] ?? 'Mes') . ' ' . $registroMensual['anio'];
    $totalPagado = (float) $registroMensual['total_pagado'];
    $ajustes = (int) $registroMensual['ajustes'];
    $etiquetasMensuales[] = $etiqueta;
    $pagosMensuales[] = $totalPagado;
    $ajustesMensuales[] = $ajustes;
    if ($mesMayorPago === null || $totalPagado > $mesMayorPago['valor']) {
        $mesMayorPago = ['etiqueta' => $etiqueta, 'valor' => $totalPagado];
    }
    if ($mesMayorAjustes === null || $ajustes > $mesMayorAjustes['valor']) {
        $mesMayorAjustes = ['etiqueta' => $etiqueta, 'valor' => $ajustes];
    }
}
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
            <h1>Resumen de operacion</h1>
            <p>Consulta en un vistazo las escuelas cargadas, los medidores vinculados y el historial de cobros CFE.</p>
        </div>
        <a class="btn-seg compact-action" href="consolidacion/consolidacion.php"><i class="bi bi-lightning-charge me-2"></i>Consolidar archivos</a>
    </section>
    <section class="quick-actions">
        <article class="quick-card">
            <span class="quick-icon"><i class="bi bi-building-check"></i></span>
            <div><strong><?= number_format($totalEscuelas) ?></strong><span>Escuelas insertadas</span></div>
            <small>Catalogos</small>
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
        <article class="quick-card">
            <span class="quick-icon"><i class="bi bi-file-earmark-bar-graph"></i></span>
            <div><strong><?= number_format($totalReportesCfe) ?></strong><span>Reportes CFE cargados</span></div>
            <small><?= $ultimoReporte ? htmlspecialchars(sprintf('%02d/%d', $ultimoReporte['mes'], $ultimoReporte['anio']), ENT_QUOTES, 'UTF-8') : 'Sin carga' ?></small>
        </article>
        <article class="quick-card <?= $casosCfe > 0 ? 'quick-card-warning' : '' ?>">
            <span class="quick-icon"><i class="bi bi-exclamation-triangle"></i></span>
            <div><strong><?= number_format($casosCfe) ?></strong><span>Casos CFE por revisar</span></div>
            <small><?= number_format($totalLecturasCfe) ?> lecturas</small>
        </article>
    </section>
    <section class="director-overview">
        <article class="director-chart-card">
            <div class="director-card-head">
                <div><span class="eyebrow">PRESUPUESTO</span><h2>Pago total por mes</h2></div>
                <i class="bi bi-cash-stack"></i>
            </div>
            <div class="chart-area"><canvas id="payments-chart"></canvas><p class="chart-empty" id="payments-empty">Carga reportes CFE para ver el comportamiento mensual.</p></div>
        </article>
        <article class="director-chart-card">
            <div class="director-card-head">
                <div><span class="eyebrow">REVISION</span><h2>Ajustes por mes</h2></div>
                <i class="bi bi-calendar2-x"></i>
            </div>
            <div class="chart-area"><canvas id="adjustments-chart"></canvas><p class="chart-empty" id="adjustments-empty">Los ajustes apareceran al cargar reportes con periodos fuera de rango.</p></div>
        </article>
        <article class="director-insights">
            <span class="eyebrow">PARA DIRECCION</span>
            <h2>Lo mas importante</h2>
            <div class="insight-row">
                <i class="bi bi-graph-up-arrow"></i>
                <span><small>Mes con mayor pago</small><strong><?= $mesMayorPago ? htmlspecialchars($mesMayorPago['etiqueta'], ENT_QUOTES, 'UTF-8') : 'Sin reportes' ?></strong><b><?= $mesMayorPago ? '$' . number_format($mesMayorPago['valor'], 2) : '$0.00' ?></b></span>
            </div>
            <div class="insight-row">
                <i class="bi bi-exclamation-circle"></i>
                <span><small>Mes con mas ajustes</small><strong><?= $mesMayorAjustes ? htmlspecialchars($mesMayorAjustes['etiqueta'], ENT_QUOTES, 'UTF-8') : 'Sin reportes' ?></strong><b><?= $mesMayorAjustes ? number_format($mesMayorAjustes['valor']) . ' recibos' : '0 recibos' ?></b></span>
            </div>
            <a href="ajustes.php" class="director-link">Ver reportes CFE <i class="bi bi-arrow-right"></i></a>
        </article>
    </section>
    <section class="dashboard-grid">
        <article class="panel-card">
            <div class="panel-head">
                <div><span class="eyebrow">FLUJO</span><h2>Proceso operativo</h2></div>
            </div>
            <div class="process-list">
                <div><b>1</b><span><strong>Sincroniza catalogos</strong><small>Carga SEG y Oficializacion para poblar escuelas.</small></span></div>
                <div><b>2</b><span><strong>Carga un reporte CFE</strong><small>Guarda cada RPU con sus importes y periodo facturado.</small></span></div>
                <div><b>3</b><span><strong>Confirma vinculos</strong><small>Guarda coincidencias seguras o revisadas.</small></span></div>
                <div><b>4</b><span><strong>Revisa cobros</strong><small>Identifica ajustes, consumo bajo y aumentos importantes.</small></span></div>
            </div>
        </article>
        <article class="panel-card">
            <div class="panel-head">
                <div><span class="eyebrow">MODULOS</span><h2>Accesos principales</h2></div>
            </div>
            <div class="module-list">
                <a href="consolidacion/consolidacion.php"><i class="bi bi-lightning-charge"></i><span><strong>Consolidacion Masiva</strong><small>Cruce predictivo y vinculacion.</small></span></a>
                <a href="importaciones.php"><i class="bi bi-table"></i><span><strong>Importaciones</strong><small>Resumen de tablas locales.</small></span></a>
                <a href="rpus.php"><i class="bi bi-pin-map"></i><span><strong>Expediente RPU</strong><small>Mapa, sugerencias e historial.</small></span></a>
                <a href="ajustes.php"><i class="bi bi-exclamation-diamond"></i><span><strong>Ajustes CFE</strong><small>Auditoria de cobros y periodos.</small></span></a>
            </div>
        </article>
    </section>
</main>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
const dashboardLabels = <?= json_encode($etiquetasMensuales, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
const dashboardPayments = <?= json_encode($pagosMensuales, JSON_NUMERIC_CHECK | JSON_HEX_TAG) ?>;
const dashboardAdjustments = <?= json_encode($ajustesMensuales, JSON_NUMERIC_CHECK | JSON_HEX_TAG) ?>;

if (dashboardLabels.length && window.Chart) {
    document.getElementById('payments-empty').hidden = true;
    document.getElementById('adjustments-empty').hidden = true;
    new Chart(document.getElementById('payments-chart'), {
        type: 'bar',
        data: {labels: dashboardLabels, datasets: [{data: dashboardPayments, backgroundColor: '#8b1827', borderRadius: 3, maxBarThickness: 38}]},
        options: {
            maintainAspectRatio: false,
            plugins: {legend: {display: false}, tooltip: {callbacks: {label: context => new Intl.NumberFormat('es-MX', {style: 'currency', currency: 'MXN'}).format(context.raw)}}},
            scales: {x: {grid: {display: false}, ticks: {color: '#6b6570', font: {size: 10}}}, y: {beginAtZero: true, grid: {color: '#eee9e4'}, ticks: {color: '#6b6570', font: {size: 10}, callback: value => '$' + new Intl.NumberFormat('es-MX', {notation: 'compact', maximumFractionDigits: 1}).format(value)}}}
        }
    });
    new Chart(document.getElementById('adjustments-chart'), {
        type: 'line',
        data: {labels: dashboardLabels, datasets: [{data: dashboardAdjustments, borderColor: '#b17b20', backgroundColor: 'rgba(191, 162, 118, .18)', borderWidth: 3, fill: true, tension: .35, pointBackgroundColor: '#8b1827', pointRadius: 4}]},
        options: {
            maintainAspectRatio: false,
            plugins: {legend: {display: false}, tooltip: {callbacks: {label: context => context.raw + ' ajustes'}}},
            scales: {x: {grid: {display: false}, ticks: {color: '#6b6570', font: {size: 10}}}, y: {beginAtZero: true, ticks: {precision: 0, color: '#6b6570', font: {size: 10}}, grid: {color: '#eee9e4'}}}
        }
    });
}
</script>
</body>
</html>
