<?php

declare(strict_types=1);

session_start();

$segBasePath = '';
$anioActual = (int) date('Y');
$mesActual = (int) date('n');

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
            <p>Sube un reporte CFE para detectar periodos fuera de regla, cargos, diferencias y cobros sin consumo.</p>
        </div>
        <span class="alert-gold">01, 02, 1A, 1B, 1C, 1E bimestral | 03, 68, 78 mensual</span>
    </section>

    <section class="results-card adjustment-uploader">
        <form id="adjustment-form" class="adjustment-form">
            <input type="hidden" name="accion" value="analizar_ajustes_cfe">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['seg_csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <label class="adjustment-drop">
                <input type="file" name="reporte_cfe" accept=".xlsx,.xls" required>
                <span><i class="bi bi-file-earmark-spreadsheet"></i></span>
                <strong id="adjustment-file-name">Selecciona reporte CFE</strong>
                <small>Ejemplo: 2025-04_M061_Reporte.xlsx</small>
            </label>
            <div class="period-controls">
                <label>
                    <span>Mes</span>
                    <select name="mes_reporte" required>
                        <?php foreach ([1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'] as $numero => $nombre): ?>
                            <option value="<?= $numero ?>" <?= $numero === $mesActual ? 'selected' : '' ?>><?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Anio</span>
                    <input type="number" name="anio_reporte" min="2020" max="2100" value="<?= $anioActual ?>" required>
                </label>
                <label>
                    <span>Periodo</span>
                    <select name="modo_periodo" required>
                        <option value="automatico">Automatico segun tarifa</option>
                        <option value="mensual">Todo mensual</option>
                        <option value="bimestral">Todo bimestral</option>
                    </select>
                </label>
            </div>
            <button class="btn-seg compact-action" type="submit"><i class="bi bi-search me-2"></i>Analizar ajustes</button>
            <button class="btn-seg compact-action btn-sync-catalogs" type="button" id="download-adjustments" disabled><i class="bi bi-file-earmark-excel me-2"></i>Exportar Excel</button>
        </form>
        <div id="adjustment-status" class="adjustment-status">Carga un reporte para iniciar la revision.</div>
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
const fileName = document.getElementById('adjustment-file-name');
const download = document.getElementById('download-adjustments');
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
    download.disabled = currentRows.length === 0;
}

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const data = new FormData(form);
    statusBox.textContent = 'Analizando reporte CFE contra el mes, anio y periodo seleccionado...';
    download.disabled = true;
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
        statusBox.textContent = `Analisis completado y guardado: ${number.format(json.resumen.con_alerta)} registros con alerta, reporte #${json.reporte_id}.`;
        render(json);
    } catch (error) {
        statusBox.textContent = error.message;
    }
});

form.reporte_cfe.addEventListener('change', () => {
    fileName.textContent = form.reporte_cfe.files[0]?.name || 'Selecciona reporte CFE';
});

download.addEventListener('click', () => {
    const rows = problemRows();
    const months = ['ENERO','FEBRERO','MARZO','ABRIL','MAYO','JUNIO','JULIO','AGOSTO','SEPTIEMBRE','OCTUBRE','NOVIEMBRE','DICIEMBRE'];
    const reportMonth = Number((currentReport.mes_reporte || '').split('-')[1] || 0);
    const summaryData = currentReport.resumen || {};
    const moneyCell = (value) => Number(value || 0).toLocaleString('es-MX', {style:'currency', currency:'MXN'});
    const rowsHtml = rows.map((row, index) => {
        const linked = row.escuelas_vinculadas?.[0];
        const suggested = row.sugerencias_escuela?.[0];
        const cct = linked?.cct || suggested?.cct || '';
        const school = linked?.nombre || suggested?.nombre || 'SIN ESCUELA VINCULADA';
        const estado = rowCase(row);
        const estadoClass = estado.includes('CORRECTO') ? 'status-ok' : estado.includes('MUCHOS') ? 'status-warn' : 'status-bad';
        const monthCells = months.map((_, monthIndex) => `<td class="money">${monthIndex + 1 === reportMonth ? moneyCell(row.total) : ''}</td>`).join('');
        return `<tr class="${index % 2 === 0 ? 'even' : 'odd'}">
            <td>${index + 1}</td>
            <td class="${estadoClass}">${escapeHtml(estado)}</td>
            <td>${escapeHtml(currentReport.mes_reporte || '')}</td>
            <td>${escapeHtml(row.rpu)}</td>
            <td>${escapeHtml(row.tarifa || '')}</td>
            <td>${escapeHtml(row.nombre || '')}</td>
            <td>${escapeHtml(row.poblacion || '')}</td>
            <td>${escapeHtml(cct)}</td>
            <td>${escapeHtml(school)}</td>
            <td>${escapeHtml(row.tipo_periodo || '')}</td>
            <td>${escapeHtml(row.desde || '')}</td>
            <td>${escapeHtml(row.hasta || '')}</td>
            <td>${escapeHtml(row.dias || '')}</td>
            <td class="number">${number.format(row.consumo || 0)}</td>
            ${monthCells}
            <td class="money total">${moneyCell(row.total)}</td>
            <td class="money">${moneyCell(row.tendencia?.diferencia_total || 0)}</td>
            <td>${escapeHtml(row.alertas?.join(' | ') || 'Sin ajuste: revisar aumento')}</td>
        </tr>`;
    }).join('');
    const html = `<!doctype html><html><head><meta charset="utf-8">
    <style>
        body{font-family:Arial,sans-serif}
        table{border-collapse:collapse;width:100%}
        td,th{border:1px solid #4aa8d8;font-size:10px;padding:4px;text-align:center;vertical-align:middle}
        .brand td{border:0;font-weight:bold}
        .brand-title{font-size:15px;text-align:center}
        .brand-sub{font-size:12px;text-align:center}
        .red-band td{background:#d60000;color:#fff;font-size:12px;font-weight:bold;text-align:center}
        .summary td{background:#f6f0df;border-color:#d8c894;font-weight:bold}
        th{background:#92d050;color:#000;font-weight:bold}
        .even td{background:#d9f2ff}
        .odd td{background:#ffffff}
        .status-ok{background:#c6efce!important;color:#006100;font-weight:bold}
        .status-warn{background:#ffeb9c!important;color:#9c6500;font-weight:bold}
        .status-bad{background:#ffc7ce!important;color:#9c0006;font-weight:bold}
        .money{text-align:right;mso-number-format:"\\0022$\\0022#,##0.00"}
        .number{text-align:right}
        .total{font-weight:bold;background:#e2f0d9!important}
    </style></head><body>
    <table>
        <tr class="brand"><td colspan="7" style="text-align:left">SECRETARIA DE EDUCACION GUERRERO</td><td colspan="15" class="brand-title">REPORTE CONCENTRADO DE REVISION CFE</td><td colspan="7" style="text-align:right">Sistema de Consolidacion Educativa</td></tr>
        <tr class="brand"><td colspan="29" class="brand-sub">SUBSECRETARIA DE ADMINISTRACION Y FINANZAS - DIRECCION DE RECURSOS MATERIALES</td></tr>
        <tr class="red-band"><td colspan="29">REPORTE DE CASOS CON AJUSTES, MUCHOS DIAS O AUMENTOS SIN AJUSTE</td></tr>
        <tr class="summary"><td colspan="4">Reporte: ${escapeHtml(currentReport.archivo || '')}</td><td colspan="3">Periodo: ${escapeHtml(currentReport.mes_reporte || '')}</td><td colspan="3">Casos exportados: ${number.format(rows.length)}</td><td colspan="4">Muchos dias: ${number.format(summaryData.ajuste_muchos_dias || 0)}</td><td colspan="6">Periodo correcto y subio: ${number.format(summaryData.periodo_correcto_con_aumento || 0)}</td><td colspan="9">Sin alerta y subio: ${number.format(summaryData.sin_alerta_con_aumento || 0)}</td></tr>
        <tr>
            <th>N.P.</th><th>ESTADO</th><th>REPORTE</th><th>RPU</th><th>TARIFA</th><th>RECIBO CFE</th><th>POBLACION</th><th>CCT</th><th>ESCUELA</th><th>TIPO</th><th>DESDE</th><th>HASTA</th><th>DIAS</th><th>CONSUMO KWH</th>
            ${months.map((month) => `<th>${month}</th>`).join('')}
            <th>TOTAL</th><th>DIF. ANTERIOR</th><th>OBSERVACIONES</th>
        </tr>
        ${rowsHtml || '<tr><td colspan="29">Sin casos para exportar</td></tr>'}
    </table></body></html>`;
    const blob = new Blob(['\ufeff' + html], {type: 'application/vnd.ms-excel;charset=utf-8;'});
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `reporte_cfe_problemas_${String(currentReport.mes_reporte || 'sin_periodo').replace(/[^0-9-]/g, '')}.xls`;
    link.click();
    URL.revokeObjectURL(url);
});
</script>
</body>
</html>
