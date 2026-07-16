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
                <h2>RPUs sugeridos por los ultimos meses cargados</h2>
                <p class="section-note">Incluye RPUs sin vinculo y tambien medidores ya vinculados que presentan importes, consumos o alertas fuera de patron.</p>
            </div>
            <button id="reload-risk-rpus" class="btn-seg compact-action" type="button"><i class="bi bi-arrow-clockwise me-2"></i>Actualizar</button>
        </div>
        <div id="risk-rpu-periods" class="adjustment-status">Buscando periodos cargados...</div>
        <div id="risk-rpu-list" class="risk-rpu-list"></div>
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
            <div id="rpu-suggestions" class="rpu-suggestions"></div>
        </article>

        <article class="results-card rpu-map-card">
            <div class="results-head">
                <div><span class="eyebrow">MAPA</span><h2>Ubicacion aproximada</h2></div>
                <a id="rpu-map-link" class="clear-link" href="#" target="_blank" rel="noopener">Abrir mapa</a>
            </div>
            <iframe id="rpu-map" title="Mapa del RPU" loading="lazy"></iframe>
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
const suggestionsBox = document.getElementById('rpu-suggestions');
const linkState = document.getElementById('rpu-link-state');
const map = document.getElementById('rpu-map');
const mapLink = document.getElementById('rpu-map-link');
const chart = document.getElementById('rpu-chart');
const historyBody = document.getElementById('rpu-history-body');
const riskList = document.getElementById('risk-rpu-list');
const riskPeriods = document.getElementById('risk-rpu-periods');
const reloadRisk = document.getElementById('reload-risk-rpus');
let currentRpu = '';

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
    const suggestions = data.sugerencias || [];
    const cfe = data.cfe || {};
    linkState.textContent = linked ? 'Vinculado' : suggestions.length ? 'Sugerencia disponible' : 'Sin vinculo';
    schoolBox.innerHTML = linked
        ? comparisonCard(cfe, linked, true)
        : `${cfePanel(cfe)}<div class="empty-state"><i class="bi bi-link-45deg"></i><strong>RPU sin vinculo confirmado</strong><span>Compara la poblacion CFE contra localidad y municipio de las sugerencias antes de vincular.</span></div>`;
    suggestionsBox.innerHTML = suggestions.length && !linked
        ? `<h3>Sugerencias para vincular</h3>${suggestions.map((item) => `<div class="rpu-suggestion">
            ${comparisonCard(cfe, item, false)}
            <button class="btn-seg compact-action" type="button" data-cct="${escapeHtml(item.cct)}">Vincular</button>
        </div>`).join('')}`
        : '';
}

function renderMap(data) {
    map.src = data.mapa?.url || 'https://www.google.com/maps?q=Guerrero%20Mexico&output=embed';
    mapLink.href = (data.mapa?.url || '').replace('&output=embed', '');
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
    renderMap(data);
    renderChart(data.historial || []);
    renderHistory(data.historial || []);
    summary.hidden = false;
    workspace.hidden = false;
    historyZone.hidden = false;
}

function renderRiskRpus(data) {
    const periods = data.periodos || [];
    const rows = data.rpus || [];
    riskPeriods.textContent = periods.length
        ? `Analisis sobre periodos: ${periods.join(', ')}. Se muestran aumentos fuertes y posibles escuelas sin operacion por consumo bajo repetido.`
        : 'Todavia no hay reportes CFE guardados para sugerir RPUs.';
    riskList.innerHTML = rows.length
        ? rows.map((row) => {
            const linked = Boolean(row.cct);
            const type = row.riesgo_tipo || 'incremento';
            const typeLabel = type === 'consumo_bajo' ? 'Consumo bajo' : (type === 'mixto' ? 'Mixto' : 'Incremento');
            const detail = type === 'consumo_bajo'
                ? `<small><b>Consumo bajo:</b> ${number.format(row.consumo || 0)} kWh actual - promedio ${number.format(row.consumo_promedio || 0)} kWh - ${escapeHtml(row.periodos_bajo_consumo || 0)} periodos bajos</small>`
                : `<small><b>Incremento:</b> ${escapeHtml(row.incremento_porcentaje || 0)}% - ${money.format(row.total_anterior || 0)} (${escapeHtml(row.periodo_anterior || 'previo')}) -> ${money.format(row.total || 0)} (${escapeHtml(row.periodo || 'actual')})</small>`;
            return `<button class="risk-rpu-item ${linked ? 'is-linked' : 'is-unlinked'}" type="button" data-rpu="${escapeHtml(row.rpu)}">
            <span class="risk-score">${escapeHtml(row.score)}</span>
            <span>
                <strong>${escapeHtml(row.rpu)} - ${escapeHtml(row.nombre || 'Sin nombre CFE')}</strong>
                <small><b>${escapeHtml(typeLabel)}:</b> ${escapeHtml(row.poblacion || 'Sin poblacion')} - Tarifa ${escapeHtml(row.tarifa || 'N/D')} - ${escapeHtml(row.periodo)}</small>
                ${detail}
                <small><b>Riesgo:</b> ${escapeHtml(row.motivo)} - ${number.format(row.consumo || 0)} kWh</small>
                <small>${linked ? `<b>Escuela:</b> ${escapeHtml(row.cct)} - ${escapeHtml(row.escuela || '')} - ${escapeHtml(row.nivel || row.subnivel || 'Sin nivel')} - ${escapeHtml(row.localidad || '')}` : '<b>Escuela:</b> sin vinculo confirmado'}</small>
            </span>
            <em class="${linked ? 'risk-linked' : 'risk-unlinked'}">${linked ? 'Vinculado' : 'Sin vinculo'}</em>
            <i class="bi bi-chevron-right"></i>
        </button>`;
        }).join('')
        : '<div class="empty-state"><i class="bi bi-check2-circle"></i><strong>Sin RPUs criticos</strong><span>No se encontraron casos prioritarios en los ultimos periodos.</span></div>';
}

async function loadRiskRpus() {
    riskPeriods.textContent = 'Calculando aumentos fuertes, consumos en cero y consumos muy bajos repetidos...';
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

suggestionsBox.addEventListener('click', async (event) => {
    const button = event.target.closest('button[data-cct]');
    if (!button) {
        return;
    }
    button.disabled = true;
    try {
        const body = new URLSearchParams({accion: 'vincular_rpu', csrf: token, rpu: currentRpu, cct: button.dataset.cct});
        const response = await fetch('../controllers/rpuController.php', {method: 'POST', body});
        const data = await response.json();
        if (!data.ok) {
            throw new Error(data.error || 'No fue posible vincular.');
        }
        statusBox.textContent = data.mensaje;
        await searchRpu(currentRpu);
    } catch (error) {
        statusBox.textContent = error.message;
        button.disabled = false;
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
