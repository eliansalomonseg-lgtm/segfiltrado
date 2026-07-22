<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../services/conexion.php';

$segBasePath = '';
$anioActual = (int) date('Y');
$mesActual = (int) date('n');
$anioExportacion = $anioActual;
$mesExportacion = $mesActual;
$reportesDisponibles = [];

try {
    $conexionVista = Conexion::conectar();
    $ultimoReporte = $conexionVista->query('SELECT anio, mes FROM cfe_reportes ORDER BY anio DESC, mes DESC, id DESC LIMIT 1')->fetch();
    $reportesDisponibles = $conexionVista->query('SELECT id, archivo, anio, mes, total_registros FROM cfe_reportes ORDER BY anio DESC, mes DESC, id DESC')->fetchAll();
    if ($ultimoReporte) {
        $anioExportacion = (int) $ultimoReporte['anio'];
        $mesExportacion = (int) $ultimoReporte['mes'];
    }
} catch (Throwable) {
}

if (empty($_SESSION['seg_csrf'])) {
    $_SESSION['seg_csrf'] = bin2hex(random_bytes(24));
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['seg_csrf'], ENT_QUOTES, 'UTF-8') ?>">
    <title>Ajustes CFE | SEG Guerrero</title>
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
            <span class="eyebrow">AUDITORIA CFE</span>
            <h1>Ajustes y cobros atipicos</h1>
                <p>Consulta los reportes CFE ya guardados para revisar periodos, ajustes y cobros atipicos.</p>
        </div>
        <span class="alert-gold">Valida fechas contra calendario real del mes</span>
    </section>

    <section class="results-card adjustment-uploader">
        <form id="adjustment-form" class="adjustment-form saved-report-form">
            <input type="hidden" name="accion" value="consultar_reporte_guardado">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['seg_csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="period-controls report-picker">
                <label>
                    <span>Reporte CFE guardado</span>
                    <select name="reporte_id" required <?= $reportesDisponibles ? '' : 'disabled' ?>>
                        <option value="">Selecciona un reporte cargado</option>
                        <?php foreach ($reportesDisponibles as $reporte): ?>
                            <option value="<?= (int) $reporte['id'] ?>"><?= htmlspecialchars(sprintf('%04d-%02d | %s | %s registros', (int) $reporte['anio'], (int) $reporte['mes'], (string) $reporte['archivo'], number_format((int) $reporte['total_registros'])), ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <button class="btn-seg compact-action" type="submit" <?= $reportesDisponibles ? '' : 'disabled' ?>><i class="bi bi-search me-2"></i>Ver ajustes</button>
        </form>
        <div id="adjustment-status" class="adjustment-status"><?= $reportesDisponibles ? 'Selecciona un reporte guardado para consultar su revision.' : 'Aun no hay reportes CFE guardados. Cargalos desde Consolidacion masiva.' ?></div>
    </section>

    <section class="results-card adjustment-uploader">
        <div class="results-head">
            <div>
                <span class="eyebrow">EXPORTACIONES</span>
                <h2>Reportes por mes</h2>
                <p>Elige mes y anio para exportar desde la base local sin volver a importar archivos.</p>
            </div>
        </div>
        <form id="export-form" class="adjustment-form" method="POST" action="../controllers/ajustesController.php">
            <input type="hidden" name="accion" value="exportar_excel_directores">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['seg_csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="period-controls">
                <label>
                    <span>Mes a exportar</span>
                    <select name="mes_exportacion" required>
                        <?php foreach ([1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'] as $numero => $nombre): ?>
                            <option value="<?= $numero ?>" <?= $numero === $mesExportacion ? 'selected' : '' ?>><?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Anio</span>
                    <input type="number" name="anio_exportacion" min="2020" max="2100" value="<?= $anioExportacion ?>" required>
                </label>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button class="btn-seg compact-action btn-sync-catalogs" type="submit" name="exportar_tipo" value="ajustes_mes"><i class="bi bi-file-earmark-excel me-2"></i>Ajustes por fechas</button>
                <button class="btn-seg compact-action btn-sync-catalogs" type="submit" name="exportar_tipo" value="bajo_consumo_mes"><i class="bi bi-battery me-2"></i>Consumo muy bajo</button>
            </div>
        </form>
    </section>

    <section class="quick-actions adjustment-summary" id="adjustment-summary" hidden>
        <article class="quick-card">
            <span class="quick-icon"><i class="bi bi-receipt"></i></span>
            <div><strong data-summary="registros">0</strong><span>Registros leidos</span></div>
            <small>Total</small>
        </article>
        <article class="quick-card">
            <span class="quick-icon"><i class="bi bi-exclamation-triangle"></i></span>
            <div><strong data-summary="con_alerta">0</strong><span>Con alerta</span></div>
            <small>Revision</small>
        </article>
        <article class="quick-card">
            <span class="quick-icon"><i class="bi bi-fire"></i></span>
            <div><strong data-summary="severos">0</strong><span>Severidad alta</span></div>
            <small>Prioridad</small>
        </article>
        <article class="quick-card">
            <span class="quick-icon"><i class="bi bi-calendar2-check"></i></span>
            <div><strong data-summary="periodo_bimestral">0</strong><span>Periodo correcto</span></div>
            <small>Correctos</small>
        </article>
        <article class="quick-card">
            <span class="quick-icon"><i class="bi bi-calendar2-x"></i></span>
            <div><strong data-summary="ajuste_muchos_dias">0</strong><span>Muchos dias</span></div>
            <small>Ajuste</small>
        </article>
        <article class="quick-card">
            <span class="quick-icon"><i class="bi bi-graph-up-arrow"></i></span>
            <div><strong data-summary="periodo_correcto_con_aumento">0</strong><span>Correctos que subieron</span></div>
            <small>Sin ajuste de dias</small>
        </article>
        <article class="quick-card">
            <span class="quick-icon"><i class="bi bi-check2-circle"></i></span>
            <div><strong data-summary="sin_alerta_con_aumento">0</strong><span>Sin alerta y subio</span></div>
            <small>Revisar consumo</small>
        </article>
        <article class="quick-card">
            <span class="quick-icon"><i class="bi bi-cash-coin"></i></span>
            <div><strong data-summary="importe_total">0</strong><span>Importe total</span></div>
            <small>Facturado</small>
        </article>
        <article class="quick-card">
            <span class="quick-icon"><i class="bi bi-building-check"></i></span>
            <div><strong data-summary="rpu_con_vinculo">0</strong><span>RPU vinculados</span></div>
            <small>Escuelas</small>
        </article>
        <article class="quick-card">
            <span class="quick-icon"><i class="bi bi-stars"></i></span>
            <div><strong data-summary="rpu_sugeridos_por_historial">0</strong><span>Sugeridos por historial</span></div>
            <small>Sin vinculo</small>
        </article>
        <article class="quick-card">
            <span class="quick-icon"><i class="bi bi-graph-down-arrow"></i></span>
            <div><strong data-summary="rpu_mejorando">0</strong><span>RPU mejorando</span></div>
            <small>Vs anterior</small>
        </article>
    </section>

    <section class="results-card" id="adjustment-results" hidden>
        <div class="results-head">
            <div>
                <span class="eyebrow">RESULTADOS</span>
                <h2>Alertas detectadas</h2>
            </div>
            <span class="alert-gold" id="adjustment-file">Sin archivo</span>
        </div>
        <div class="table-wrap">
            <table class="control-table">
                <thead>
                    <tr>
                        <th>RPU</th>
                        <th>Recibo</th>
                        <th>Periodo</th>
                        <th>Escuela</th>
                        <th>Consumo</th>
                        <th>Total</th>
                        <th>Ajustes</th>
                        <th>Alertas</th>
                    </tr>
                </thead>
                <tbody id="adjustment-body"></tbody>
            </table>
        </div>
    </section>
</main>
<script>
const token = document.querySelector('meta[name="csrf-token"]').content;
const form = document.getElementById('adjustment-form');
const statusBox = document.getElementById('adjustment-status');
const summary = document.getElementById('adjustment-summary');
const results = document.getElementById('adjustment-results');
const body = document.getElementById('adjustment-body');
const fileLabel = document.getElementById('adjustment-file');
let currentRows = [];
let currentReport = {};

const money = new Intl.NumberFormat('es-MX', {style: 'currency', currency: 'MXN'});
const number = new Intl.NumberFormat('es-MX');

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
}

function problemRows() {
    return currentRows.filter((row) => row.alertas.length > 0 || (row.tendencia && Number(row.tendencia.diferencia_total || 0) > 0));
}

function rowCase(row) {
    const maxDays = row.tipo_periodo === 'mensual' ? 35 : 75;
    const minDays = row.tipo_periodo === 'mensual' ? 25 : 50;
    const days = Number(row.dias || 0);
    const periodOk = days >= minDays && days <= maxDays;
    const wentUp = row.tendencia && Number(row.tendencia.diferencia_total || 0) > 0;
    if (days > maxDays) return 'MUCHOS DIAS';
    if (periodOk && wentUp && !row.alertas.length) return 'PERIODO CORRECTO / SUBIO';
    if (periodOk && wentUp) return 'CON AJUSTE Y SUBIO';
    return row.alertas.length ? 'CON AJUSTE' : 'REVISION';
}

function render(data) {
    currentRows = data.registros || [];
    currentReport = data;
    Object.entries(data.resumen || {}).forEach(([key, value]) => {
        const target = summary.querySelector(`[data-summary="${key}"]`);
        if (target) {
            target.textContent = key === 'importe_total' ? money.format(value) : number.format(value);
        }
    });
    fileLabel.textContent = `${data.archivo || 'Reporte'} ${data.mes_reporte ? ' - ' + data.mes_reporte : ''} - ${data.modo_periodo || 'automatico'}`;
    body.innerHTML = problemRows().slice(0, 200).map((row) => {
        const level = row.severidad >= 7 ? 'status-warn' : row.severidad >= 4 ? '' : 'status-ok';
        const linked = row.escuelas_vinculadas?.[0];
        const suggested = row.sugerencias_escuela?.[0];
        const simpleCase = rowCase(row).toLowerCase().replace(/\b\w/g, (letter) => letter.toUpperCase());
        const school = linked
            ? `<strong>${escapeHtml(linked.cct)} - ${escapeHtml(linked.nombre)}</strong><small>Vinculo confirmado</small>`
            : suggested
                ? `<strong>${escapeHtml(suggested.cct)} - ${escapeHtml(suggested.nombre)}</strong><small>Sugerida por historial (${escapeHtml(suggested.apariciones)} apariciones)</small>`
                : '<strong>Sin vinculo</strong><small>Sin sugerencia previa</small>';
        const trend = row.tendencia
            ? `${row.tendencia.diferencia_total <= 0 ? 'Bajo' : 'Subio'} ${money.format(Math.abs(row.tendencia.diferencia_total || 0))} vs ${escapeHtml(row.tendencia.periodo_anterior)}`
            : 'Sin historial previo';
        return `<tr>
            <td><strong>${escapeHtml(row.rpu)}</strong><small>${escapeHtml(row.tarifa || 'Sin tarifa')}</small></td>
            <td><strong>${escapeHtml(row.nombre)}</strong><small>${escapeHtml(row.poblacion)}</small></td>
            <td><strong>${escapeHtml(simpleCase)}</strong><small>${escapeHtml(row.desde)} / ${escapeHtml(row.hasta)}<br>${escapeHtml(row.tipo_periodo || '')} - ${escapeHtml(row.dias)} dias</small></td>
            <td>${school}</td>
            <td><strong>${number.format(row.consumo || 0)}</strong><small>kWh</small></td>
            <td><strong>${money.format(row.total || 0)}</strong><small>${escapeHtml(trend)}<br>Diferencia ${money.format(row.diferencia || 0)}</small></td>
            <td><strong>${money.format(row.cargos_depositos || 0)}</strong><small>Creditos ${money.format(row.creditos_redondeos || 0)}</small></td>
            <td><span class="status-pill ${level}">Sev. ${escapeHtml(row.severidad)}</span><small>${escapeHtml(row.alertas.join(' | '))}</small></td>
        </tr>`;
    }).join('');
    summary.hidden = false;
    results.hidden = false;
}

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const data = new FormData(form);
    statusBox.textContent = 'Consultando ajustes y tendencias del reporte guardado...';
    try {
        const response = await fetch('../controllers/ajustesController.php', {
            method: 'POST',
            headers: {'X-CSRF-Token': token},
            body: data
        });
        const json = await response.json();
        if (!json.ok) {
            throw new Error(json.error || 'No fue posible analizar el reporte.');
        }
        statusBox.textContent = `Consulta lista: ${number.format(json.resumen.con_alerta)} registros con alerta en el reporte seleccionado.`;
        render(json);
    } catch (error) {
        statusBox.textContent = error.message;
    }
});

</script>
</body>
</html>
