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
            <span class="eyebrow">EXPEDIENTE ENERGETICO</span>
            <h1>Consulta por RPU</h1>
            <p>Verifica si un medidor esta vinculado, revisa su escuela, mapa, sugerencias, historial y tendencia de pagos.</p>
        </div>
        <span class="alert-gold">RPU -> escuela -> historial</span>
    </section>

    <section class="results-card rpu-search-card">
        <form id="rpu-form" class="rpu-search-form">
            <label class="search-field">
                <i class="bi bi-search"></i>
                <input type="search" name="rpu" placeholder="Buscar RPU" required>
            </label>
            <button class="btn-seg compact-action" type="submit"><i class="bi bi-lightning-charge me-2"></i>Consultar</button>
        </form>
        <div id="rpu-status" class="adjustment-status">Captura un RPU para revisar su expediente.</div>
    </section>

    <section class="results-card rpu-risk-card">
        <div class="results-head">
            <div>
                <span class="eyebrow">REVISION PRIORITARIA</span>
                <h2>RPUs sugeridos por los ultimos 6 periodos</h2>
                <p class="section-note">Incluye subidas fuertes, pago minimo, consumo cero y consumo bajo de 50 kWh o menos en los ultimos seis periodos.</p>
            </div>
            <button id="reload-risk-rpus" class="btn-seg compact-action" type="button"><i class="bi bi-arrow-clockwise me-2"></i>Actualizar</button>
        </div>
        <div id="risk-rpu-periods" class="adjustment-status">Buscando periodos cargados...</div>
        <div class="risk-filter-bar" id="risk-filter-bar" hidden>
            <button type="button" data-risk-filter="todos" class="active">Todos</button>
            <button type="button" data-risk-filter="pago_minimo">Pago minimo</button>
            <button type="button" data-risk-filter="sin_consumo">Sin consumo</button>
            <button type="button" data-risk-filter="consumo_bajo">Consume <=50 kWh</button>
            <button type="button" data-risk-filter="incremento">Subio mucho</button>
            <button type="button" data-risk-filter="sin_vinculo">Sin vinculo</button>
        </div>
        <div id="risk-rpu-summary" class="risk-summary" hidden></div>
        <div id="risk-rpu-list" class="risk-rpu-list"></div>
        <div id="risk-rpu-pager" class="risk-pager" hidden></div>
    </section>

    <section id="rpu-summary" class="quick-actions" hidden>
        <article class="quick-card">
            <span class="quick-icon"><i class="bi bi-receipt"></i></span>
            <div><strong data-rpu-summary="registros">0</strong><span>Reportes guardados</span></div>
            <small>Historial</small>
        </article>
        <article class="quick-card">
            <span class="quick-icon"><i class="bi bi-cash-coin"></i></span>
            <div><strong data-rpu-summary="total_actual">$0.00</strong><span>Ultimo total</span></div>
            <small>Pago</small>
        </article>
        <article class="quick-card">
            <span class="quick-icon"><i class="bi bi-speedometer2"></i></span>
            <div><strong data-rpu-summary="consumo_actual">0</strong><span>Ultimo consumo</span></div>
            <small>kWh</small>
        </article>
        <article class="quick-card">
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

    <section class="dashboard-grid" id="rpu-history-zone" hidden>
        <article class="results-card">
            <div class="results-head">
                <div><span class="eyebrow">GRAFICA</span><h2>Pagos y consumo</h2></div>
            </div>
            <div id="rpu-chart" class="rpu-chart"></div>
        </article>
        <article class="results-card">
            <div class="results-head">
                <div><span class="eyebrow">HISTORIAL</span><h2>Reportes del RPU</h2></div>
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
const riskList = document.getElementById('risk-rpu-list');
const riskPeriods = document.getElementById('risk-rpu-periods');
const riskFilterBar = document.getElementById('risk-filter-bar');
const riskSummary = document.getElementById('risk-rpu-summary');
const riskPager = document.getElementById('risk-rpu-pager');
const reloadRisk = document.getElementById('reload-risk-rpus');
let currentRpu = '';
let riskRowsAll = [];
let riskRows = [];
let riskPage = 1;
let riskFilter = 'todos';
const riskPageSize = 10;

const money = new Intl.NumberFormat('es-MX', {style: 'currency', currency: 'MXN'});
const number = new Intl.NumberFormat('es-MX');

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
}

function setSummary(resumen) {
    summary.querySelector('[data-rpu-summary="registros"]').textContent = number.format(resumen.registros || 0);
    summary.querySelector('[data-rpu-summary="total_actual"]').textContent = money.format(resumen.total_actual || 0);
    summary.querySelector('[data-rpu-summary="consumo_actual"]').textContent = number.format(resumen.consumo_actual || 0);
    summary.querySelector('[data-rpu-summary="estado"]').textContent = resumen.estado || 'Sin historial';
    summary.querySelector('[data-rpu-summary="diferencia_total"]').textContent = resumen.diferencia_total === null || resumen.diferencia_total === undefined
        ? 'Sin comparativo'
        : `${resumen.diferencia_total <= 0 ? 'Bajo' : 'Subio'} ${money.format(Math.abs(resumen.diferencia_total))}`;
}

function cfePanel(cfe) {
    return `<div class="compare-panel cfe-panel">
        <span class="compare-label">RECIBO CFE</span>
        <strong>${escapeHtml(cfe.rpu || currentRpu)} - ${escapeHtml(cfe.nombre || 'Sin nombre CFE')}</strong>
        <small><b>Poblacion CFE:</b> ${escapeHtml(cfe.poblacion || 'Sin poblacion')}</small>
        <small><b>Tarifa:</b> ${escapeHtml(cfe.tarifa || 'Sin tarifa')} ${cfe.periodo ? `- <b>Periodo:</b> ${escapeHtml(cfe.periodo)}` : ''}</small>
    </div>`;
}

function schoolPanel(escuela, vinculado) {
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
    </div>`;
}

function comparisonCard(cfe, escuela, vinculado) {
    return `<div class="rpu-comparison">
        ${cfePanel(cfe || {})}
        ${schoolPanel(escuela || {}, vinculado)}
    </div>`;
}

function renderSchool(data) {
    const linked = data.vinculos?.[0];
    const cfe = data.cfe || {};
    linkState.textContent = linked ? 'Vinculado' : 'Sin vinculo';
    schoolBox.innerHTML = linked
        ? comparisonCard(cfe, linked, true)
        : `${cfePanel(cfe)}<div class="empty-state"><i class="bi bi-link-45deg"></i><strong>RPU sin vinculo confirmado</strong><span>Este medidor todavia no tiene escuela asignada.</span></div>`;
}

function renderChart(historial) {
    if (!historial.length) {
        chart.innerHTML = '<div class="empty-state"><i class="bi bi-bar-chart"></i><strong>Sin historial</strong><span>Guarda reportes CFE para construir la grafica.</span></div>';
        return;
    }
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

function render(data) {
    setSummary(data.resumen || {});
    renderSchool(data);
    renderChart(data.historial || []);
    renderHistory(data.historial || []);
    summary.hidden = false;
    workspace.hidden = false;
    historyZone.hidden = false;
}

function renderRiskRpus(data) {
    const periods = data.periodos || [];
    riskRowsAll = data.rpus || [];
    riskRows = applyRiskFilter(riskRowsAll);
    riskPage = 1;
    riskPeriods.textContent = periods.length
        ? `Ultimos 6 periodos: ${periods.join(', ')}. ${riskRowsAll.length} casos para revisar.`
        : 'Todavia no hay reportes CFE guardados para sugerir RPUs.';
    riskFilterBar.hidden = riskRowsAll.length === 0;
    riskSummary.hidden = riskRowsAll.length === 0;
    updateRiskFilterButtons();
    renderRiskPage();
}

function applyRiskFilter(rows) {
    if (riskFilter === 'todos') {
        return rows;
    }
    if (riskFilter === 'sin_vinculo') {
        return rows.filter((row) => !row.cct);
    }
    return rows.filter((row) => row.riesgo_tipo === riskFilter || (riskFilter === 'incremento' && row.riesgo_tipo === 'mixto'));
}

function updateRiskFilterButtons() {
    riskFilterBar.querySelectorAll('button[data-risk-filter]').forEach((button) => {
        button.classList.toggle('active', button.dataset.riskFilter === riskFilter);
    });
}

function renderRiskSummary(rows) {
    const totalActual = rows.reduce((sum, row) => sum + Number(row.total || 0), 0);
    const consumoActual = rows.reduce((sum, row) => sum + Number(row.consumo || 0), 0);
    const sinVinculo = rows.filter((row) => !row.cct).length;
    const pagosMinimos = rows.filter((row) => row.riesgo_tipo === 'pago_minimo' || row.riesgo_tipo === 'sin_consumo').length;
    riskSummary.innerHTML = `<span><b>${number.format(rows.length)}</b> casos</span><span><b>${money.format(totalActual)}</b> pago actual</span><span><b>${number.format(consumoActual)}</b> kWh</span><span><b>${number.format(pagosMinimos)}</b> minimo/sin consumo</span><span><b>${number.format(sinVinculo)}</b> sin vinculo</span>`;
}

function riskLabel(type) {
    return {
        incremento: 'Subio mucho',
        consumo_bajo: 'Consume poco',
        pago_minimo: 'Pago minimo',
        sin_consumo: 'Sin consumo',
        mixto: 'Doble alerta'
    }[type] || 'Revision';
}

function renderRiskPage() {
    renderRiskSummary(riskRows);
    const totalPages = Math.max(1, Math.ceil(riskRows.length / riskPageSize));
    riskPage = Math.min(Math.max(1, riskPage), totalPages);
    const start = (riskPage - 1) * riskPageSize;
    const rows = riskRows.slice(start, start + riskPageSize);
    riskList.innerHTML = rows.length
        ? rows.map((row) => {
            const linked = Boolean(row.cct);
            const type = row.riesgo_tipo || 'incremento';
            const label = riskLabel(type);
            const schoolName = linked ? `${row.cct} - ${row.escuela || 'Escuela sin nombre'}` : 'Sin escuela vinculada';
            const history = (row.historial_periodos || []).map((item) => `<span>${escapeHtml(item.periodo)}<b>${money.format(item.total || 0)}</b><small>${number.format(item.consumo || 0)} kWh</small></span>`).join('');
            const mainMetric = type === 'incremento' || type === 'mixto'
                ? `${escapeHtml(row.incremento_porcentaje || 0)}%`
                : `${number.format(row.consumo || 0)} kWh`;
            const simpleReason = type === 'pago_minimo'
                ? `Pago minimo en ${escapeHtml(row.periodos_pago_minimo || 0)} periodos`
                : type === 'sin_consumo'
                    ? 'No registra consumo actual'
                    : type === 'consumo_bajo'
                        ? `Consumo de 50 kWh o menos en ${escapeHtml(row.periodos_bajo_consumo || 0)} periodos`
                        : `Subio de ${money.format(row.total_anterior || 0)} a ${money.format(row.total || 0)}`;
            return `<button class="risk-rpu-item risk-type-${escapeHtml(type)} ${linked ? 'is-linked' : 'is-unlinked'}" type="button" data-rpu="${escapeHtml(row.rpu)}">
            <span class="risk-score">${escapeHtml(row.score)}</span>
            <span>
                <strong>${escapeHtml(row.rpu)} - ${escapeHtml(schoolName)}</strong>
                <small>${escapeHtml(row.nombre || 'Recibo CFE sin nombre')} - ${escapeHtml(row.poblacion || 'Sin poblacion')} - Tarifa ${escapeHtml(row.tarifa || 'N/D')}</small>
                <span class="risk-simple"><b>${escapeHtml(label)}</b><strong>${mainMetric}</strong><small>${escapeHtml(simpleReason)}</small></span>
                <span class="risk-history">${history || '<span>Sin historial suficiente</span>'}</span>
            </span>
            <em class="${linked ? 'risk-linked' : 'risk-unlinked'}">${linked ? 'Vinculado' : 'Sin vinculo'}</em>
            <i class="bi bi-chevron-right"></i>
        </button>`;
        }).join('')
        : '<div class="empty-state"><i class="bi bi-check2-circle"></i><strong>Sin RPUs criticos</strong><span>No se encontraron casos prioritarios en los ultimos periodos.</span></div>';
    riskPager.hidden = riskRows.length <= riskPageSize;
    riskPager.innerHTML = riskRows.length > riskPageSize
        ? `<button type="button" data-risk-page="prev" ${riskPage === 1 ? 'disabled' : ''}>Anterior</button><span>Pagina ${riskPage} de ${totalPages} - ${riskRows.length} casos</span><button type="button" data-risk-page="next" ${riskPage === totalPages ? 'disabled' : ''}>Siguiente</button>`
        : '';
}

async function loadRiskRpus() {
    riskPeriods.textContent = 'Calculando los ultimos 6 periodos: aumentos fuertes, consumos en cero y consumos muy bajos repetidos...';
    const body = new URLSearchParams({accion: 'sugerir_rpus_malos', csrf: token});
    const response = await fetch('../controllers/rpuController.php', {method: 'POST', body});
    const data = await response.json();
    if (!data.ok) {
        throw new Error(data.error || 'No fue posible calcular sugerencias.');
    }
    renderRiskRpus(data);
}

async function searchRpu(rpu) {
    currentRpu = rpu;
    statusBox.textContent = 'Consultando vinculos, historial y sugerencias...';
    const body = new URLSearchParams({accion: 'buscar_rpu', csrf: token, rpu});
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

riskList.addEventListener('click', async (event) => {
    const button = event.target.closest('button[data-rpu]');
    if (!button) {
        return;
    }
    form.rpu.value = button.dataset.rpu;
    try {
        await searchRpu(button.dataset.rpu);
    } catch (error) {
        statusBox.textContent = error.message;
    }
});

riskFilterBar.addEventListener('click', (event) => {
    const button = event.target.closest('button[data-risk-filter]');
    if (!button) {
        return;
    }
    riskFilter = button.dataset.riskFilter;
    riskRows = applyRiskFilter(riskRowsAll);
    riskPage = 1;
    updateRiskFilterButtons();
    renderRiskPage();
});

riskPager.addEventListener('click', (event) => {
    const button = event.target.closest('button[data-risk-page]');
    if (!button) {
        return;
    }
    riskPage += button.dataset.riskPage === 'next' ? 1 : -1;
    renderRiskPage();
});

reloadRisk.addEventListener('click', async () => {
    try {
        await loadRiskRpus();
    } catch (error) {
        riskPeriods.textContent = error.message;
    }
});

loadRiskRpus().catch((error) => {
    riskPeriods.textContent = error.message;
});
</script>
</body>
</html>
