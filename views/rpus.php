<?php

declare(strict_types=1);

session_start();

$segBasePath = '';

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
    <title>Expediente RPU | SEG Guerrero</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../assets/css/seg-executive.css" rel="stylesheet">
</head>
<body>
<?php include_once __DIR__ . '/fragments/navbar.php'; ?>
<?php include_once __DIR__ . '/fragments/sidebar.php'; ?>
<main class="content rpu-directory-view">
    <section class="heading">
        <div>
            <span class="eyebrow">EXPEDIENTE ENERGÉTICO</span>
            <h1>Consulta por RPU</h1>
            <p>Consulta el recibo, la escuela vinculada y el historial de pagos.</p>
        </div>
        <span class="alert-gold">RPU y escuela</span>
    </section>

    <section class="results-card rpu-search-card">
        <div class="rpu-search-heading"><span class="quick-icon"><i class="bi bi-lightning-charge"></i></span><div><span class="eyebrow">BÚSQUEDA</span><h2>Localizar medidor</h2></div></div>
        <form id="rpu-form" class="rpu-search-form">
            <label class="search-field">
                <i class="bi bi-search"></i>
                <input type="search" name="rpu" placeholder="Buscar RPU" required>
            </label>
            <button class="btn-seg compact-action" type="submit"><i class="bi bi-search me-2"></i>Consultar RPU</button>
        </form>
        <div id="rpu-status" class="adjustment-status">Captura un RPU para revisar su expediente.</div>
    </section>

    <section id="rpu-summary" class="quick-actions" hidden>
        <article class="quick-card rpu-metric-reports">
            <span class="quick-icon"><i class="bi bi-receipt"></i></span>
            <div><strong data-rpu-summary="registros">0</strong><span>Reportes guardados</span></div>
            <small>Historial</small>
        </article>
        <article class="quick-card rpu-metric-current">
            <span class="quick-icon"><i class="bi bi-cash-coin"></i></span>
            <div><strong data-rpu-summary="total_actual">$0.00</strong><span>Ultimo total</span></div>
            <small>Pago</small>
        </article>
        <article class="quick-card rpu-metric-total">
            <span class="quick-icon"><i class="bi bi-wallet2"></i></span>
            <div><strong data-rpu-summary="total_acumulado">$0.00</strong><span>Total acumulado</span></div>
            <small>Todo el historial</small>
        </article>
        <article class="quick-card rpu-metric-consumption">
            <span class="quick-icon"><i class="bi bi-speedometer2"></i></span>
            <div><strong data-rpu-summary="consumo_actual">0</strong><span>Ultimo consumo</span></div>
            <small>kWh</small>
        </article>
        <article class="quick-card rpu-metric-energy">
            <span class="quick-icon"><i class="bi bi-lightning-charge"></i></span>
            <div><strong data-rpu-summary="consumo_acumulado">0</strong><span>Consumo acumulado</span></div>
            <small>kWh historicos</small>
        </article>
        <article class="quick-card rpu-metric-trend">
            <span class="quick-icon"><i class="bi bi-activity"></i></span>
            <div><strong data-rpu-summary="estado">Sin historial</strong><span>Tendencia</span></div>
            <small data-rpu-summary="diferencia_total">Sin comparativo</small>
        </article>
    </section>

    <section class="rpu-grid" id="rpu-workspace" hidden>
        <article class="results-card">
            <div class="results-head">
                <div><span class="eyebrow">VINCULO</span><h2>Escuela localizada</h2></div>
                <span class="alert-gold" id="rpu-link-state">Sin vinculo</span>
            </div>
            <div id="rpu-school" class="rpu-school-card"></div>
        </article>
    </section>

    <section class="rpu-history-layout" id="rpu-history-zone" hidden>
        <article class="results-card rpu-chart-card">
            <div class="results-head">
                <div><span class="eyebrow">HISTORIAL COMPLETO</span><h2>Pagos y consumo por periodo</h2><p class="section-note">Cada barra representa un reporte cargado para este RPU.</p></div>
            </div>
            <div class="rpu-history-tools">
                <label class="rpu-history-filter"><span>Año</span><select id="rpu-history-year"><option value="all">Todo el historial</option></select></label>
                <label class="rpu-print-filter"><span>Formato de impresión</span><select id="rpu-print-mode"><option value="summary">Todo el periodo seleccionado</option><option value="periods">Un periodo por hoja</option></select></label>
                <button id="print-rpu-history" class="btn-seg compact-action" type="button"><i class="bi bi-printer me-2"></i>Imprimir RPU</button>
            </div>
            <div id="rpu-chart" class="rpu-chart"></div>
        </article>
        <article class="results-card rpu-history-card">
            <div class="results-head">
                <div><span class="eyebrow">DETALLE</span><h2>Reportes del RPU</h2><p class="section-note">Consulta fechas, total, consumo y alertas de cada periodo.</p></div>
            </div>
            <div class="table-wrap">
                <table class="control-table">
                    <thead>
                        <tr>
                            <th>Periodo</th>
                            <th>Total</th>
                            <th>Consumo</th>
                            <th>Alertas</th>
                        </tr>
                    </thead>
                    <tbody id="rpu-history-body"></tbody>
                </table>
            </div>
        </article>
    </section>
</main>
<script>
const token = document.querySelector('meta[name="csrf-token"]').content;
const form = document.getElementById('rpu-form');
const statusBox = document.getElementById('rpu-status');
const summary = document.getElementById('rpu-summary');
const workspace = document.getElementById('rpu-workspace');
const historyZone = document.getElementById('rpu-history-zone');
const schoolBox = document.getElementById('rpu-school');
const linkState = document.getElementById('rpu-link-state');
const chart = document.getElementById('rpu-chart');
const historyBody = document.getElementById('rpu-history-body');
const historyYearFilter = document.getElementById('rpu-history-year');
const printModeFilter = document.getElementById('rpu-print-mode');
const printRpuHistory = document.getElementById('print-rpu-history');
let currentRpu = '';
let currentHistory = [];

const money = new Intl.NumberFormat('es-MX', {style: 'currency', currency: 'MXN'});
const number = new Intl.NumberFormat('es-MX');

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
}

function setSummary(resumen) {
    summary.querySelector('[data-rpu-summary="registros"]').textContent = number.format(resumen.registros || 0);
    summary.querySelector('[data-rpu-summary="total_actual"]').textContent = money.format(resumen.total_actual || 0);
    summary.querySelector('[data-rpu-summary="total_acumulado"]').textContent = money.format(resumen.total_acumulado || 0);
    summary.querySelector('[data-rpu-summary="consumo_actual"]').textContent = number.format(resumen.consumo_actual || 0);
    summary.querySelector('[data-rpu-summary="consumo_acumulado"]').textContent = number.format(resumen.consumo_acumulado || 0);
    summary.querySelector('[data-rpu-summary="estado"]').textContent = resumen.estado || 'Sin historial';
    summary.querySelector('[data-rpu-summary="diferencia_total"]').textContent = resumen.diferencia_total === null || resumen.diferencia_total === undefined
        ? 'Sin comparativo'
        : `${resumen.diferencia_total <= 0 ? 'Bajo' : 'Subio'} ${money.format(Math.abs(resumen.diferencia_total))}`;
}

function cfePanel(cfe) {
    return `<div class="compare-panel cfe-panel">
        <span class="compare-label">RECIBO CFE</span>
        <strong>${escapeHtml(cfe.rpu || currentRpu)} - ${escapeHtml(cfe.nombre || 'Sin nombre CFE')}</strong>
        <small><b>Division CFE:</b> ${escapeHtml(cfe.division || 'Sin division')}</small>
        <small><b>Direccion CFE:</b> ${escapeHtml(cfe.direccion || 'Sin direccion')}</small>
        <small><b>Poblacion CFE:</b> ${escapeHtml(cfe.poblacion || 'Sin poblacion')}</small>
        <small><b>Tarifa:</b> ${escapeHtml(cfe.tarifa || 'Sin tarifa')} ${cfe.periodo ? `- <b>Periodo:</b> ${escapeHtml(cfe.periodo)}` : ''}</small>
    </div>`;
}

function schoolPanel(escuela, vinculado, rpu) {
    const turnoZona = [escuela.turno ? `<b>Turno:</b> ${escapeHtml(escuela.turno)}` : '', escuela.zona ? `<b>Zona:</b> ${escapeHtml(escuela.zona)}` : '', escuela.sector ? `<b>Sector:</b> ${escapeHtml(escuela.sector)}` : ''].filter(Boolean).join(' - ');
    const homoFuente = [escuela.homo ? `<b>HOMO:</b> ${escapeHtml(escuela.homo)}` : '', escuela.fuente ? `<b>Fuente:</b> ${escapeHtml(escuela.fuente)}` : ''].filter(Boolean).join(' - ');
    return `<div class="compare-panel school-panel">
        <span class="compare-label">ESCUELA OFICIAL</span>
        <strong>${escapeHtml(escuela.cct || 'Sin CCT')} - ${escapeHtml(escuela.nombre || 'Escuela sin nombre')}</strong>
        <small><b>Domicilio:</b> ${escapeHtml(escuela.domicilio || 'Sin domicilio')}</small>
        <small><b>Localidad:</b> ${escapeHtml(escuela.localidad || 'Sin localidad')} - <b>Municipio:</b> ${escapeHtml(escuela.municipio || 'Sin municipio')}</small>
        <small><b>Nivel educativo:</b> ${escapeHtml(escuela.nivel || 'Sin nivel')}</small>
        <small><b>Subnivel:</b> ${escapeHtml(escuela.subnivel || 'Sin subnivel')}</small>
        ${turnoZona ? `<small>${turnoZona}</small>` : ''}
        ${homoFuente ? `<small>${homoFuente}</small>` : ''}
        <span class="status-pill ${vinculado ? 'status-ok' : 'status-warn'}">${escapeHtml(escuela.origen || (vinculado ? 'Vinculo confirmado' : 'Sugerencia'))} - ${escapeHtml(escuela.score || 0)}%</span>
        ${vinculado && escuela.cct ? `<button class="unlink-rpu-button" type="button" data-unlink-rpu="${escapeHtml(rpu)}" data-unlink-cct="${escapeHtml(escuela.cct)}"><i class="bi bi-link-45deg me-1"></i>Desvincular este CCT</button>` : ''}
    </div>`;
}

function comparisonCard(cfe, escuela, vinculado) {
    return `<div class="rpu-comparison">
        ${cfePanel(cfe || {})}
        ${schoolPanel(escuela || {}, vinculado, currentRpu)}
    </div>`;
}

function renderSchool(data) {
    const vinculados = data.vinculos || [];
    const cfe = data.cfe || {};
    linkState.textContent = vinculados.length ? (vinculados.length === 1 ? 'Vinculado' : `${vinculados.length} vinculos`) : 'Sin vinculo';
    schoolBox.innerHTML = vinculados.length
        ? vinculados.map((escuela) => comparisonCard(cfe, escuela, true)).join('')
        : `${cfePanel(cfe)}<div class="empty-state"><i class="bi bi-link-45deg"></i><strong>RPU sin vinculo confirmado</strong><span>Este medidor todavia no tiene escuela asignada.</span></div>`;
}

function renderChart(historial) {
    if (!historial.length) {
        chart.classList.remove('is-long-history');
        chart.innerHTML = '<div class="empty-state"><i class="bi bi-bar-chart"></i><strong>Sin historial</strong><span>Guarda reportes CFE para construir la grafica.</span></div>';
        return;
    }
    chart.classList.toggle('is-long-history', historial.length > 12);
    const maxTotal = Math.max(...historial.map((row) => Number(row.total) || 0), 1);
    chart.innerHTML = historial.map((row) => {
        const total = Number(row.total) || 0;
        const consumo = Number(row.consumo) || 0;
        const height = Math.max(8, Math.round(total / maxTotal * 120));
        return `<div class="rpu-bar">
            <span style="height:${height}px"></span>
            <strong>${money.format(total)}</strong>
            <small>${escapeHtml(row.anio)}-${String(row.mes).padStart(2, '0')}<br>${number.format(consumo)} kWh</small>
        </div>`;
    }).join('');
}

function renderHistory(historial) {
    historyBody.innerHTML = historial.length
        ? historial.slice().reverse().map((row) => `<tr>
            <td><strong>${escapeHtml(row.anio)}-${String(row.mes).padStart(2, '0')}</strong><small>${escapeHtml(row.desde || '')} / ${escapeHtml(row.hasta || '')}</small></td>
            <td><strong>${money.format(row.total || 0)}</strong><small>${escapeHtml(row.tarifa_cfe || 'Sin tarifa')}</small></td>
            <td><strong>${number.format(row.consumo || 0)}</strong><small>kWh</small></td>
            <td><span class="status-pill ${Number(row.severidad) >= 4 ? 'status-warn' : 'status-ok'}">Sev. ${escapeHtml(row.severidad || 0)}</span><small>${escapeHtml(row.alertas || 'Sin alertas')}</small></td>
        </tr>`).join('')
        : '<tr><td colspan="4" class="empty-state"><i class="bi bi-clock-history"></i><strong>Sin historial</strong><span>Analiza reportes en Ajustes CFE para alimentar esta vista.</span></td></tr>';
}

function resumenHistorial(historial) {
    if (!historial.length) {
        return {registros: 0, total_actual: 0, consumo_actual: 0, total_acumulado: 0, consumo_acumulado: 0, diferencia_total: null, estado: 'Sin historial'};
    }
    const actual = historial[historial.length - 1];
    const anterior = historial[historial.length - 2];
    const diferencia = anterior ? Number(actual.total || 0) - Number(anterior.total || 0) : null;
    return {
        registros: historial.length,
        total_actual: Number(actual.total || 0),
        consumo_actual: Number(actual.consumo || 0),
        total_acumulado: historial.reduce((suma, fila) => suma + Number(fila.total || 0), 0),
        consumo_acumulado: historial.reduce((suma, fila) => suma + Number(fila.consumo || 0), 0),
        diferencia_total: diferencia,
        estado: diferencia === null ? 'Primer registro' : (diferencia <= 0 ? 'Mejorando' : 'Subiendo')
    };
}

function aplicarFiltroHistorial() {
    const historial = historialFiltrado();
    setSummary(resumenHistorial(historial));
    renderChart(historial);
    renderHistory(historial);
}

function historialFiltrado() {
    const year = historyYearFilter.value;
    return year === 'all' ? currentHistory : currentHistory.filter((fila) => String(fila.anio) === year);
}

function abrirImpresionRpu() {
    const historial = historialFiltrado();
    if (!currentRpu || !historial.length) {
        statusBox.textContent = 'Consulta un RPU con historial antes de imprimir.';
        return;
    }
    const mode = printModeFilter.value;
    const yearLabel = historyYearFilter.value === 'all' ? 'Todo el historial' : `Año ${historyYearFilter.value}`;
    const resumen = resumenHistorial(historial);
    const maxTotal = Math.max(...historial.map((fila) => Number(fila.total) || 0), 1);
    const resumenEnHoja = (filas, etiqueta) => {
        const suma = resumenHistorial(filas);
        const maximo = Math.max(...filas.map((fila) => Number(fila.total) || 0), 1);
        const barras = filas.map((fila) => {
            const height = Math.max(10, Math.round((Number(fila.total) || 0) / maximo * 180));
            return `<div class="bar"><i style="height:${height}px"></i><strong>${money.format(fila.total || 0)}</strong><small>${escapeHtml(fila.anio)}-${String(fila.mes).padStart(2, '0')}<br>${number.format(fila.consumo || 0)} kWh</small></div>`;
        }).join('');
        return `<section class="summary-sheet"><header><span>SECRETARÍA DE EDUCACIÓN GUERRERO</span><h1>RPU ${escapeHtml(currentRpu)}</h1><p>${escapeHtml(etiqueta)} · ${number.format(suma.registros)} reportes · Total acumulado ${money.format(suma.total_acumulado)} · Consumo ${number.format(suma.consumo_acumulado)} kWh</p></header><div class="chart">${barras}</div><table><thead><tr><th>Periodo</th><th>Total</th><th>Consumo</th><th>Tarifa</th><th>Alertas</th></tr></thead><tbody>${filas.slice().reverse().map((fila) => `<tr><td>${escapeHtml(fila.anio)}-${String(fila.mes).padStart(2, '0')}</td><td>${money.format(fila.total || 0)}</td><td>${number.format(fila.consumo || 0)} kWh</td><td>${escapeHtml(fila.tarifa_cfe || 'Sin tarifa')}</td><td>${escapeHtml(fila.alertas || 'Sin alertas')}</td></tr>`).join('')}</tbody></table></section>`;
    };
    const detallePorPeriodo = historial.map((fila, index) => `<section class="period-sheet"><header><span>SECRETARÍA DE EDUCACIÓN GUERRERO</span><h1>RPU ${escapeHtml(currentRpu)}</h1><p>Periodo ${escapeHtml(fila.anio)}-${String(fila.mes).padStart(2, '0')} · Hoja ${index + 1} de ${historial.length}</p></header><div class="period-grid"><div><span>Total facturado</span><strong>${money.format(fila.total || 0)}</strong></div><div><span>Consumo</span><strong>${number.format(fila.consumo || 0)} kWh</strong></div><div><span>Tarifa</span><strong>${escapeHtml(fila.tarifa_cfe || 'Sin tarifa')}</strong></div><div><span>Fechas del recibo</span><strong>${escapeHtml(fila.desde || 'Sin fecha')} / ${escapeHtml(fila.hasta || 'Sin fecha')}</strong></div></div><div class="single-bar"><i style="height:${Math.max(30, Math.round((Number(fila.total) || 0) / maxTotal * 260))}px"></i><strong>${money.format(fila.total || 0)}</strong></div><p class="alerts"><b>Alertas:</b> ${escapeHtml(fila.alertas || 'Sin alertas')}</p></section>`).join('');
    const resumenesPorAnio = historyYearFilter.value === 'all'
        ? Object.values(historial.reduce((grupos, fila) => {
            (grupos[fila.anio] ||= []).push(fila);
            return grupos;
        }, {})).sort((a, b) => Number(a[0].anio) - Number(b[0].anio)).map((filas) => resumenEnHoja(filas, `Año ${filas[0].anio}`)).join('')
        : resumenEnHoja(historial, yearLabel);
    const content = mode === 'periods' ? detallePorPeriodo : resumenesPorAnio;
    const printWindow = window.open('', '_blank', 'width=1200,height=900');
    if (!printWindow) {
        statusBox.textContent = 'El navegador bloqueo la ventana de impresión. Permite ventanas emergentes e inténtalo otra vez.';
        return;
    }
    printWindow.document.write(`<!doctype html><html lang="es"><head><meta charset="utf-8"><title>RPU ${escapeHtml(currentRpu)}</title><style>@page{size:landscape;margin:12mm}*{box-sizing:border-box;-webkit-print-color-adjust:exact;print-color-adjust:exact}body{color:#222;font-family:Arial,sans-serif;margin:0}header{border-bottom:3px solid #6a1b29;margin-bottom:18px;padding-bottom:10px}header span{color:#6a1b29;font-size:10px;font-weight:800;letter-spacing:1.2px}h1{font-size:25px;margin:5px 0}header p{color:#555;font-size:12px;margin:0}.summary-sheet,.period-sheet{break-after:page;min-height:180mm}.chart{align-items:end;display:flex;gap:10px;justify-content:space-around;min-height:265px;padding:18px 4px}.bar{align-items:center;display:grid;gap:6px;justify-items:center;min-width:58px}.bar i,.single-bar i{background:#bfa276;border:2px solid #6a1b29;border-radius:8px 8px 2px 2px;display:block;width:25px}.bar strong{font-size:10px;text-align:center}.bar small{color:#555;font-size:9px;line-height:1.3;text-align:center}table{border-collapse:collapse;font-size:10px;width:100%}th{background:#6a1b29;color:#fff;text-align:left}th,td{border:1px solid #d9d4d0;padding:6px;vertical-align:top}.period-grid{display:grid;gap:10px;grid-template-columns:repeat(4,1fr)}.period-grid div{border:1px solid #d9d4d0;padding:12px}.period-grid span,.period-grid strong{display:block}.period-grid span{color:#666;font-size:11px}.period-grid strong{font-size:17px;margin-top:5px}.single-bar{align-items:center;display:grid;gap:10px;justify-content:center;justify-items:center;min-height:310px}.single-bar strong{font-size:20px}.alerts{border-top:1px solid #d9d4d0;font-size:12px;padding-top:10px}@media print{.summary-sheet:last-child,.period-sheet:last-child{break-after:auto}}</style></head><body>${content}</body></html>`);
    printWindow.document.close();
    printWindow.focus();
    window.setTimeout(() => printWindow.print(), 800);
}

function cargarAniosHistorial(historial) {
    const year = historyYearFilter.value;
    const years = [...new Set(historial.map((fila) => String(fila.anio)))].sort((a, b) => Number(b) - Number(a));
    historyYearFilter.innerHTML = `<option value="all">Todo el historial</option>${years.map((item) => `<option value="${escapeHtml(item)}">${escapeHtml(item)}</option>`).join('')}`;
    historyYearFilter.value = years.includes(year) ? year : 'all';
}

function render(data) {
    currentHistory = data.historial || [];
    cargarAniosHistorial(currentHistory);
    renderSchool(data);
    aplicarFiltroHistorial();
    summary.hidden = false;
    workspace.hidden = false;
    historyZone.hidden = false;
}

async function searchRpu(rpu) {
    currentRpu = rpu;
    statusBox.textContent = 'Consultando vinculos, historial y sugerencias...';
    const body = new URLSearchParams({accion: 'buscar_rpu', csrf: token, rpu, incluir_sugerencias: '0'});
    const response = await fetch('../controllers/rpuController.php', {method: 'POST', body});
    const data = await response.json();
    if (!data.ok) {
        throw new Error(data.error || 'No fue posible consultar el RPU.');
    }
    statusBox.textContent = data.encontrado ? `Expediente cargado para RPU ${data.rpu}.` : `No hay historial guardado para RPU ${data.rpu}.`;
    render(data);
}

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    try {
        await searchRpu(form.rpu.value.trim());
    } catch (error) {
        statusBox.textContent = error.message;
    }
});

historyYearFilter.addEventListener('change', aplicarFiltroHistorial);
printRpuHistory.addEventListener('click', abrirImpresionRpu);

schoolBox.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-unlink-cct]');
    if (!button) {
        return;
    }
    const rpu = button.dataset.unlinkRpu;
    const cct = button.dataset.unlinkCct;
    if (!window.confirm(`¿Confirmas desvincular el RPU ${rpu} del CCT ${cct}?`)) {
        return;
    }
    button.disabled = true;
    try {
        const body = new URLSearchParams({accion: 'desvincular_rpu', csrf: token, rpu, cct});
        const response = await fetch('../controllers/rpuController.php', {method: 'POST', body});
        const data = await response.json();
        if (!response.ok || !data.ok) {
            throw new Error(data.error || 'No fue posible desvincular el CCT.');
        }
        statusBox.textContent = data.mensaje;
        await searchRpu(rpu);
    } catch (error) {
        statusBox.textContent = error.message;
        button.disabled = false;
    }
});

</script>
</body>
</html>
