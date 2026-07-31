<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/services/auth.php';
segRequireLogin('../login.php');

require_once dirname(__DIR__) . '/services/conexion.php';

$conexion = Conexion::conectar();
$segBasePath = '';

if (empty($_SESSION['seg_csrf'])) {
    $_SESSION['seg_csrf'] = bin2hex(random_bytes(24));
}

function dashboardCount(PDO $conexion, string $query): int
{
    return (int) $conexion->query($query)->fetchColumn();
}

$totalEscuelas = dashboardCount($conexion, 'SELECT COUNT(*) FROM escuelas');
$totalVinculos = dashboardCount($conexion, 'SELECT COUNT(*) FROM escuelas_rpu');
$rpusVinculados = dashboardCount($conexion, 'SELECT COUNT(DISTINCT RPU) FROM escuelas_rpu');
$totalReportesCfe = dashboardCount($conexion, 'SELECT COUNT(*) FROM cfe_reportes');
$totalLecturasCfe = dashboardCount($conexion, 'SELECT COALESCE(SUM(total_registros), 0) FROM cfe_reportes');
$casosCfe = dashboardCount($conexion, 'SELECT COALESCE(SUM(con_alerta), 0) FROM cfe_reportes');
$reportesCeroRecientes = $conexion->query('SELECT id FROM cfe_reportes ORDER BY anio DESC, mes DESC, id DESC LIMIT 6')->fetchAll();
$totalReportesCeroRecientes = count($reportesCeroRecientes);
$ultimoReporte = $conexion->query('SELECT anio, mes FROM cfe_reportes ORDER BY anio DESC, mes DESC, id DESC LIMIT 1')->fetch();
$avance = $totalEscuelas > 0 ? min(100, round($totalVinculos / $totalEscuelas * 100, 1)) : 0;
$meses = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];
$historialMensual = $conexion->query(
    'SELECT anio, mes, SUM(importe_total) AS total_pagado, SUM(ajuste_muchos_dias) AS ajustes
     FROM cfe_reportes
     GROUP BY anio, mes
     ORDER BY anio ASC, mes ASC'
)->fetchAll(PDO::FETCH_ASSOC);
$pagosReales = [];
try {
    $consultaPagosReales = $conexion->query('SELECT anio, mes, importe_facturado, importe_pagado, referencia FROM cfe_pagos_reales');
    foreach ($consultaPagosReales->fetchAll(PDO::FETCH_ASSOC) as $pagoReal) {
        $pagosReales[(int) $pagoReal['anio'] . '-' . (int) $pagoReal['mes']] = $pagoReal;
    }
} catch (Throwable) {
}
$historialGraficas = [];
$aniosGraficas = [];
$mesMayorPago = null;
$mesMayorAjustes = null;
foreach ($historialMensual as $registroMensual) {
    $etiqueta = ($meses[(int) $registroMensual['mes']] ?? 'Mes') . ' ' . $registroMensual['anio'];
    $totalPagado = (float) $registroMensual['total_pagado'];
    $llavePeriodo = (int) $registroMensual['anio'] . '-' . (int) $registroMensual['mes'];
    $pagoReal = $pagosReales[$llavePeriodo] ?? null;
    $ajustes = (int) $registroMensual['ajustes'];
    $historialGraficas[] = [
        'anio' => (int) $registroMensual['anio'],
        'mes' => (int) $registroMensual['mes'],
        'etiqueta' => $meses[(int) $registroMensual['mes']] ?? 'Mes',
        'facturado' => $totalPagado,
        'pagado' => $pagoReal ? (float) $pagoReal['importe_pagado'] : null,
        'referencia' => $pagoReal['referencia'] ?? '',
        'ajustes' => $ajustes
    ];
    $aniosGraficas[(int) $registroMensual['anio']] = true;
    $pagoParaResumen = $pagoReal ? (float) $pagoReal['importe_pagado'] : $totalPagado;
    if ($mesMayorPago === null || $pagoParaResumen > $mesMayorPago['valor']) {
        $mesMayorPago = ['etiqueta' => $etiqueta, 'valor' => $pagoParaResumen, 'conciliado' => $pagoReal !== null];
    }
    if ($mesMayorAjustes === null || $ajustes > $mesMayorAjustes['valor']) {
        $mesMayorAjustes = ['etiqueta' => $etiqueta, 'valor' => $ajustes];
    }
}
$aniosGraficas = array_keys($aniosGraficas);
sort($aniosGraficas);
$aniosExportacion = array_reverse($aniosGraficas);
$anioGraficaInicial = $aniosGraficas ? max($aniosGraficas) : 0;
$periodosConciliacion = $historialGraficas;
usort($periodosConciliacion, static fn (array $a, array $b): int => ($b['anio'] <=> $a['anio']) ?: ($b['mes'] <=> $a['mes']));
$periodoConciliacionInicial = $periodosConciliacion[0] ?? null;
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SIEE Guerrero | Panel de Control</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/seg-executive.css" rel="stylesheet">
</head>
<body>
<?php include_once __DIR__ . '/fragments/navbar.php'; ?>
<?php include_once __DIR__ . '/fragments/sidebar.php'; ?>
<main class="content dashboard-view">
    <section class="heading">
        <div>
            <span class="eyebrow">SIEE GUERRERO</span>
            <h1>Inteligencia energetica educativa</h1>
            <p>Consulta escuelas, medidores vinculados y el historial de cobros CFE desde una sola plataforma institucional.</p>
        </div>
        <?php if (segIsAdmin()): ?>
            <a class="btn-seg compact-action" href="consolidacion/consolidacion.php"><i class="bi bi-lightning-charge me-2"></i>Consolidar archivos</a>
        <?php endif; ?>
    </section>
    <section class="quick-actions">
        <article class="quick-card dashboard-metric-schools">
            <span class="quick-icon"><i class="bi bi-building-check"></i></span>
            <div><strong><?= number_format($totalEscuelas) ?></strong><span>Escuelas insertadas</span></div>
            <small>Catalogos</small>
        </article>
        <article class="quick-card dashboard-metric-links">
            <span class="quick-icon"><i class="bi bi-diagram-3"></i></span>
            <div><strong><?= number_format($totalVinculos) ?></strong><span>Vinculos guardados</span></div>
            <small>RPU-CCT</small>
        </article>
        <article class="quick-card dashboard-metric-progress">
            <span class="quick-icon"><i class="bi bi-speedometer2"></i></span>
            <div><strong><?= number_format($avance, 1) ?>%</strong><span>Avance de vinculacion</span></div>
            <small><?= number_format($rpusVinculados) ?> RPU</small>
        </article>
        <article class="quick-card dashboard-metric-reports">
            <span class="quick-icon"><i class="bi bi-file-earmark-bar-graph"></i></span>
            <div><strong><?= number_format($totalReportesCfe) ?></strong><span>Reportes CFE cargados</span></div>
            <small><?= $ultimoReporte ? htmlspecialchars(sprintf('%02d/%d', $ultimoReporte['mes'], $ultimoReporte['anio']), ENT_QUOTES, 'UTF-8') : 'Sin carga' ?></small>
        </article>
        <article class="quick-card dashboard-metric-alerts <?= $casosCfe > 0 ? 'quick-card-warning' : '' ?>">
            <span class="quick-icon"><i class="bi bi-exclamation-triangle"></i></span>
            <div><strong><?= number_format($casosCfe) ?></strong><span>Casos CFE por revisar</span></div>
            <small><?= number_format($totalLecturasCfe) ?> lecturas</small>
        </article>
    </section>
    <section class="dashboard-annual-export">
        <div>
            <span class="eyebrow">CONCENTRADO ANUAL</span>
            <h2>Todos los servicios CFE por año</h2>
            <p>Descarga un Excel con cada RPU, nombre, población, domicilio, consumo acumulado y costo total del año elegido.</p>
        </div>
        <form method="post" action="../controllers/rpuController.php" class="dashboard-annual-export-form">
            <input type="hidden" name="accion" value="exportar_resumen_anual_cfe">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['seg_csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <label><span>Año a exportar</span><select name="anio" <?= $aniosExportacion ? '' : 'disabled' ?>><?php foreach ($aniosExportacion as $anioExportacion): ?><option value="<?= (int) $anioExportacion ?>"><?= (int) $anioExportacion ?></option><?php endforeach; ?></select></label>
            <button class="btn-seg compact-action" type="submit" <?= $aniosExportacion ? '' : 'disabled' ?>><i class="bi bi-file-earmark-spreadsheet me-2"></i>Exportar Excel</button>
        </form>
    </section>
    <section class="analytics-overview">
        <div class="analytics-section-head">
            <div><span class="eyebrow">PANORAMA FINANCIERO CFE</span><h2>Comportamiento mensual</h2><p>Consulta un año a la vez para comparar pagos y ajustes sin saturar las gráficas.</p></div>
            <label class="analytics-year-filter"><span>Año a consultar</span><select id="dashboard-year-filter"><option value="all">Todos los años</option><?php foreach ($aniosGraficas as $anioGrafica): ?><option value="<?= (int) $anioGrafica ?>" <?= $anioGrafica === $anioGraficaInicial ? 'selected' : '' ?>><?= (int) $anioGrafica ?></option><?php endforeach; ?></select></label>
        </div>
        <div id="analytics-totals" class="analytics-totals" hidden></div>
        <article class="analytics-chart-card">
            <div class="director-card-head">
                <div><span class="eyebrow">PRESUPUESTO</span><h2>Pago total por mes</h2></div>
                <i class="bi bi-cash-stack"></i>
            </div>
            <div class="analytics-chart-scroll"><div class="analytics-chart-area"><canvas id="payments-chart"></canvas><p class="chart-empty" id="payments-empty">Carga reportes CFE para ver el comportamiento mensual.</p></div></div>
        </article>
        <article class="analytics-chart-card">
            <div class="director-card-head">
                <div><span class="eyebrow">REVISION</span><h2>Ajustes por mes</h2></div>
                <i class="bi bi-calendar2-x"></i>
            </div>
            <div class="analytics-chart-scroll"><div class="analytics-chart-area analytics-chart-area-adjustments"><canvas id="adjustments-chart"></canvas><p class="chart-empty" id="adjustments-empty">Los ajustes apareceran al cargar reportes con periodos fuera de rango.</p></div></div>
        </article>
    </section>
    <section class="director-insights dashboard-insights">
        <div><span class="eyebrow">PARA DIRECCION</span><h2>Lo mas importante</h2></div>
        <div class="dashboard-insight-list">
            <div class="insight-row">
                <i class="bi bi-graph-up-arrow"></i>
                <span><small>Mes con mayor pago<?= !empty($mesMayorPago['conciliado']) ? ' conciliado' : '' ?></small><strong><?= $mesMayorPago ? htmlspecialchars($mesMayorPago['etiqueta'], ENT_QUOTES, 'UTF-8') : 'Sin reportes' ?></strong><b><?= $mesMayorPago ? '$' . number_format($mesMayorPago['valor'], 2) : '$0.00' ?></b></span>
            </div>
            <div class="insight-row">
                <i class="bi bi-exclamation-circle"></i>
                <span><small>Mes con mas ajustes</small><strong><?= $mesMayorAjustes ? htmlspecialchars($mesMayorAjustes['etiqueta'], ENT_QUOTES, 'UTF-8') : 'Sin reportes' ?></strong><b><?= $mesMayorAjustes ? number_format($mesMayorAjustes['valor']) . ' recibos' : '0 recibos' ?></b></span>
            </div>
            <a href="ajustes.php" class="director-link">Ver reportes CFE <i class="bi bi-arrow-right"></i></a>
        </div>
    </section>
    <section class="payment-reconciliation-card">
        <div class="payment-reconciliation-head">
            <div>
                <span class="eyebrow">CONCILIACION DE PAGO</span>
                <h2 id="payment-period-title"><?= $periodoConciliacionInicial ? htmlspecialchars(($meses[$periodoConciliacionInicial['mes']] ?? 'Mes') . ' ' . $periodoConciliacionInicial['anio'], ENT_QUOTES, 'UTF-8') : 'Selecciona un periodo' ?></h2>
                <p>El importe facturado permanece como referencia; el pago real confirmado se muestra por separado en la gráfica.</p>
            </div>
            <label class="payment-period-select"><span>Mes a conciliar</span><select id="payment-period-select" <?= $periodosConciliacion ? '' : 'disabled' ?>><?php foreach ($periodosConciliacion as $periodo): ?><option value="<?= (int) $periodo['anio'] ?>-<?= (int) $periodo['mes'] ?>"><?= htmlspecialchars(($meses[$periodo['mes']] ?? 'Mes') . ' ' . $periodo['anio'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label>
        </div>
        <div class="payment-reconciliation-grid">
            <div class="payment-amount"><span>Facturado por CFE</span><strong id="payment-facturado">$<?= number_format((float) ($periodoConciliacionInicial['facturado'] ?? 0), 2) ?></strong></div>
            <div class="payment-amount payment-amount-real"><span>Pagado real</span><strong id="payment-pagado"><?= isset($periodoConciliacionInicial['pagado']) && $periodoConciliacionInicial['pagado'] !== null ? '$' . number_format((float) $periodoConciliacionInicial['pagado'], 2) : 'Sin registrar' ?></strong></div>
            <div class="payment-amount payment-amount-difference"><span>Diferencia</span><strong id="payment-diferencia"><?= isset($periodoConciliacionInicial['pagado']) && $periodoConciliacionInicial['pagado'] !== null ? '$' . number_format(max(0, (float) $periodoConciliacionInicial['facturado'] - (float) $periodoConciliacionInicial['pagado']), 2) : 'Pendiente' ?></strong></div>
        </div>
        <form id="payment-reconciliation-form" class="payment-reconciliation-form">
            <input id="payment-year" type="hidden" name="anio" value="<?= (int) ($periodoConciliacionInicial['anio'] ?? 0) ?>">
            <input id="payment-month" type="hidden" name="mes" value="<?= (int) ($periodoConciliacionInicial['mes'] ?? 0) ?>">
            <label><span>Pago real MXN</span><input id="payment-amount-input" name="importe_pagado" type="number" min="0" step="0.01" value="<?= isset($periodoConciliacionInicial['pagado']) && $periodoConciliacionInicial['pagado'] !== null ? htmlspecialchars(number_format((float) $periodoConciliacionInicial['pagado'], 2, '.', ''), ENT_QUOTES, 'UTF-8') : '' ?>" required <?= $periodoConciliacionInicial ? '' : 'disabled' ?>></label>
            <label><span>Referencia o comprobante</span><input id="payment-reference-input" name="referencia" type="text" maxlength="255" value="<?= htmlspecialchars((string) ($periodoConciliacionInicial['referencia'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" placeholder="Folio, oficio o referencia" <?= $periodoConciliacionInicial ? '' : 'disabled' ?>></label>
            <button class="btn-seg compact-action" type="submit" <?= $periodoConciliacionInicial ? '' : 'disabled' ?>><i class="bi bi-check2-circle me-2"></i>Guardar pago real</button>
        </form>
        <div id="payment-reconciliation-status" class="payment-reconciliation-status"></div>
    </section>
    <section class="zero-consumption-card">
        <div class="zero-consumption-head">
            <div>
                <span class="eyebrow">SERVICIOS SIN CONSUMO</span>
                <h2>Posibles escuelas o servicios sin operación</h2>
                <p>Exporta únicamente los RPUs que registran consumo de energía en cero en los reportes recientes.</p>
            </div>
            <span class="zero-consumption-period"><i class="bi bi-calendar3"></i><?= $totalReportesCeroRecientes ?> de 6 reportes disponibles</span>
        </div>
        <div class="zero-consumption-options">
            <article>
                <span class="zero-consumption-icon"><i class="bi bi-lightning-charge"></i></span>
                <div>
                    <strong>3 o más reportes en cero</strong>
                    <span>Identifica servicios con consumo 0 recurrente</span>
                </div>
                <form method="post" action="../controllers/ajustesController.php">
                    <input type="hidden" name="accion" value="exportar_consumo_cero_recurrente">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['seg_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                    <button class="btn-seg compact-action" type="submit" name="exportar_tipo" value="cero_tres_reportes" <?= $totalReportesCeroRecientes < 3 ? 'disabled' : '' ?>><i class="bi bi-download me-2"></i>Exportar CSV</button>
                </form>
            </article>
            <article>
                <span class="zero-consumption-icon zero-consumption-icon-critical"><i class="bi bi-exclamation-triangle"></i></span>
                <div>
                    <strong>6 últimos reportes en cero</strong>
                    <span>Identifica servicios sin consumo durante todo el periodo</span>
                </div>
                <form method="post" action="../controllers/ajustesController.php">
                    <input type="hidden" name="accion" value="exportar_consumo_cero_recurrente">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['seg_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                    <button class="btn-seg compact-action" type="submit" name="exportar_tipo" value="cero_seis_reportes" <?= $totalReportesCeroRecientes < 6 ? 'disabled' : '' ?>><i class="bi bi-download me-2"></i>Exportar CSV</button>
                </form>
            </article>
        </div>
    </section>
</main>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
const dashboardHistory = <?= json_encode($historialGraficas, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
const dashboardCsrf = <?= json_encode($_SESSION['seg_csrf'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
const dashboardMonths = <?= json_encode($meses, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
let paymentsChart;
let adjustmentsChart;
const moneyFormat = new Intl.NumberFormat('es-MX', {style: 'currency', currency: 'MXN'});
const compactMoneyFormat = new Intl.NumberFormat('es-MX', {notation: 'compact', maximumFractionDigits: 1});
const analyticsTotals = document.getElementById('analytics-totals');

function paymentSummary(history) {
    const facturado = history.reduce((total, item) => total + Number(item.facturado || 0), 0);
    const pagosConfirmados = history.filter((item) => item.pagado !== null && item.pagado !== undefined);
    return {
        periodos: history.length,
        facturado,
        pagado: pagosConfirmados.reduce((total, item) => total + Number(item.pagado || 0), 0),
        pagosConfirmados: pagosConfirmados.length
    };
}

function renderAnalyticsTotals(history, year) {
    if (!history.length) {
        analyticsTotals.hidden = true;
        analyticsTotals.innerHTML = '';
        return;
    }
    const total = paymentSummary(history);
    const byYear = Object.values(history.reduce((groups, item) => {
        const key = String(item.anio);
        (groups[key] ||= []).push(item);
        return groups;
    }, {})).map((items) => ({year: items[0].anio, ...paymentSummary(items)})).sort((a, b) => a.year - b.year);
    const annualCards = year === 'all' ? byYear.map((item) => `<article><span>${item.year}</span><strong>${moneyFormat.format(item.facturado)}</strong><small>${item.periodos} reportes${item.pagosConfirmados ? ` · Pago real ${moneyFormat.format(item.pagado)}` : ''}</small></article>`).join('') : '';
    analyticsTotals.innerHTML = `<article class="analytics-total-main"><span>${year === 'all' ? 'Facturado en todos los años' : `Facturado en ${year}`}</span><strong>${moneyFormat.format(total.facturado)}</strong><small>${total.periodos} reportes incluidos</small></article><article class="analytics-total-main is-confirmed"><span>Pago real confirmado</span><strong>${total.pagosConfirmados ? moneyFormat.format(total.pagado) : 'Sin registro'}</strong><small>${total.pagosConfirmados ? `${total.pagosConfirmados} periodo${total.pagosConfirmados === 1 ? '' : 's'} conciliado${total.pagosConfirmados === 1 ? '' : 's'}` : 'Captura pagos reales para compararlos'}</small></article>${annualCards}`;
    analyticsTotals.hidden = false;
}

function renderDashboardCharts(year) {
    if (!window.Chart) return;
    const history = year === 'all' ? dashboardHistory : dashboardHistory.filter(item => String(item.anio) === year);
    const paymentsEmpty = document.getElementById('payments-empty');
    const adjustmentsEmpty = document.getElementById('adjustments-empty');
    if (paymentsChart) paymentsChart.destroy();
    if (adjustmentsChart) adjustmentsChart.destroy();
    renderAnalyticsTotals(history, year);
    if (!history.length) {
        paymentsEmpty.hidden = false;
        adjustmentsEmpty.hidden = false;
        return;
    }
    paymentsEmpty.hidden = true;
    adjustmentsEmpty.hidden = true;
    const series = year === 'all'
        ? Object.values(history.reduce((groups, item) => {
            const key = String(item.anio);
            if (!groups[key]) groups[key] = {label: key, facturado: 0, pagado: 0, tienePago: false, ajustes: 0};
            groups[key].facturado += Number(item.facturado || 0);
            groups[key].ajustes += Number(item.ajustes || 0);
            if (item.pagado !== null && item.pagado !== undefined) {
                groups[key].pagado += Number(item.pagado || 0);
                groups[key].tienePago = true;
            }
            return groups;
        }, {})).sort((a, b) => Number(a.label) - Number(b.label))
        : history.map((item) => ({label: item.etiqueta, facturado: Number(item.facturado || 0), pagado: Number(item.pagado || 0), tienePago: item.pagado !== null && item.pagado !== undefined, ajustes: Number(item.ajustes || 0)}));
    const labels = series.map(item => item.label);
    const chartCompact = window.matchMedia('(max-width: 760px)').matches;
    const xTicks = {color: '#5d5860', font: {size: chartCompact ? 10 : 11, weight: '600'}, maxRotation: 0, minRotation: 0, autoSkip: true, maxTicksLimit: chartCompact ? 5 : 12};
    paymentsChart = new Chart(document.getElementById('payments-chart'), {
        type: 'bar',
        data: {labels, datasets: [{label: 'Facturado CFE', data: series.map(item => item.facturado), backgroundColor: '#7d1b2b', borderRadius: 4, maxBarThickness: 48}, {label: 'Pago real confirmado', data: series.map(item => item.tienePago ? item.pagado : null), backgroundColor: '#bfa276', borderRadius: 4, maxBarThickness: 48}]},
        options: {responsive: true, maintainAspectRatio: false, interaction: {mode: 'index', intersect: false}, plugins: {legend: {position: 'bottom', labels: {boxWidth: 12, padding: 18, font: {size: chartCompact ? 10 : 12, weight: '600'}}}, tooltip: {callbacks: {label: context => `${context.dataset.label}: ${moneyFormat.format(context.raw || 0)}`}}}, scales: {x: {grid: {display: false}, ticks: xTicks}, y: {beginAtZero: true, grid: {color: '#eee9e4'}, ticks: {color: '#5d5860', font: {size: chartCompact ? 10 : 11}, callback: value => '$' + compactMoneyFormat.format(value)}}}}
    });
    adjustmentsChart = new Chart(document.getElementById('adjustments-chart'), {
        type: 'line',
        data: {labels, datasets: [{label: 'Ajustes detectados', data: series.map(item => item.ajustes), borderColor: '#9a6314', backgroundColor: 'rgba(191, 162, 118, .2)', borderWidth: 3, fill: true, tension: .28, pointBackgroundColor: '#6a1b29', pointBorderColor: '#fff', pointBorderWidth: 2, pointRadius: 5, pointHoverRadius: 7}]},
        options: {responsive: true, maintainAspectRatio: false, interaction: {mode: 'index', intersect: false}, plugins: {legend: {position: 'bottom', labels: {boxWidth: 12, padding: 18, font: {size: chartCompact ? 10 : 12, weight: '600'}}}, tooltip: {callbacks: {label: context => `${context.raw || 0} ajustes`}}}, scales: {x: {grid: {display: false}, ticks: xTicks}, y: {beginAtZero: true, ticks: {precision: 0, color: '#5d5860', font: {size: chartCompact ? 10 : 11}}, grid: {color: '#eee9e4'}}}}
    });
}

const dashboardYearFilter = document.getElementById('dashboard-year-filter');
renderDashboardCharts(dashboardYearFilter?.value || 'all');
dashboardYearFilter?.addEventListener('change', () => renderDashboardCharts(dashboardYearFilter.value));

const paymentForm = document.getElementById('payment-reconciliation-form');
const paymentStatus = document.getElementById('payment-reconciliation-status');
const paymentPeriodSelect = document.getElementById('payment-period-select');
const paymentPeriodTitle = document.getElementById('payment-period-title');
const paymentYear = document.getElementById('payment-year');
const paymentMonth = document.getElementById('payment-month');
const paymentAmount = document.getElementById('payment-amount-input');
const paymentReference = document.getElementById('payment-reference-input');
const paymentFacturado = document.getElementById('payment-facturado');
const paymentPagado = document.getElementById('payment-pagado');
const paymentDiferencia = document.getElementById('payment-diferencia');

function actualizarConciliacionPeriodo() {
    const [anio, mes] = String(paymentPeriodSelect?.value || '').split('-').map(Number);
    const periodo = dashboardHistory.find((item) => Number(item.anio) === anio && Number(item.mes) === mes);
    if (!periodo) return;
    const pagado = periodo.pagado === null || periodo.pagado === undefined ? null : Number(periodo.pagado);
    paymentYear.value = String(anio);
    paymentMonth.value = String(mes);
    paymentPeriodTitle.textContent = `${dashboardMonths[mes] || 'Mes'} ${anio}`;
    paymentFacturado.textContent = moneyFormat.format(periodo.facturado || 0);
    paymentPagado.textContent = pagado === null ? 'Sin registrar' : moneyFormat.format(pagado);
    paymentDiferencia.textContent = pagado === null ? 'Pendiente' : moneyFormat.format(Math.max(0, Number(periodo.facturado || 0) - pagado));
    paymentAmount.value = pagado === null ? '' : pagado.toFixed(2);
    paymentReference.value = periodo.referencia || '';
    paymentStatus.textContent = '';
}

paymentPeriodSelect?.addEventListener('change', actualizarConciliacionPeriodo);
paymentForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    const button = paymentForm.querySelector('button');
    button.disabled = true;
    paymentStatus.textContent = `Guardando pago real de ${paymentPeriodTitle.textContent}...`;
    try {
        const body = new URLSearchParams(new FormData(paymentForm));
        body.set('accion', 'guardar_pago_real');
        body.set('csrf', dashboardCsrf);
        const response = await fetch('../controllers/ajustesController.php', {method: 'POST', body});
        const data = await response.json();
        if (!response.ok || !data.ok) throw new Error(data.error || 'No fue posible guardar el pago real.');
        paymentStatus.textContent = `${data.mensaje} Diferencia conciliada: ${new Intl.NumberFormat('es-MX', {style: 'currency', currency: 'MXN'}).format(data.diferencia)}.`;
        window.setTimeout(() => window.location.reload(), 700);
    } catch (error) {
        paymentStatus.textContent = error.message;
        button.disabled = false;
    }
});
</script>
</body>
</html>
