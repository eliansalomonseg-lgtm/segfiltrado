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
                <span class="eyebrow">BUSCADOR CFE</span>
                <h2>Descartar RPUs no vinculados</h2>
                <p class="section-note">Busca en los reportes CFE cargados por RPU, nombre del servicio, direccion, poblacion, division o tarifa.</p>
            </div>
        </div>
        <form id="cfe-catalog-form" class="import-filters">
            <label class="search-field">
                <i class="bi bi-search"></i>
                <input type="search" name="q" placeholder="RPU">
            </label>
            <label class="search-field">
                <i class="bi bi-building"></i>
                <input type="search" name="nombre" list="cfe-nombre-options" data-cfe-option="nombre" placeholder="Nombre del servicio">
            </label>
            <label class="search-field">
                <i class="bi bi-signpost"></i>
                <input type="search" name="direccion" list="cfe-direccion-options" data-cfe-option="direccion" data-optional-cfe="direccion" placeholder="Direccion del reporte">
            </label>
            <label class="search-field">
                <i class="bi bi-geo-alt"></i>
                <input type="search" name="poblacion" list="cfe-poblacion-options" data-cfe-option="poblacion" placeholder="Municipio o poblacion CFE">
            </label>
            <label class="search-field">
                <i class="bi bi-lightning"></i>
                <input type="search" name="tarifa" list="cfe-tarifa-options" data-cfe-option="tarifa" placeholder="Tarifa">
            </label>
            <label class="search-field">
                <i class="bi bi-diagram-3"></i>
                <input type="search" name="division" list="cfe-division-options" data-cfe-option="division" data-optional-cfe="division" placeholder="Division">
            </label>
            <label class="search-field">
                <input type="checkbox" name="sin_vinculo" value="1" checked>
                <span>Solo sin vinculo</span>
            </label>
            <button class="btn-seg compact-action" type="submit"><i class="bi bi-search me-2"></i>Buscar</button>
        </form>
        <datalist id="cfe-nombre-options"></datalist>
        <datalist id="cfe-direccion-options"></datalist>
        <datalist id="cfe-poblacion-options"></datalist>
        <datalist id="cfe-tarifa-options"></datalist>
        <datalist id="cfe-division-options"></datalist>
        <div id="cfe-catalog-status" class="adjustment-status">Usa los filtros para localizar medidores de escuelas que todavia no tienen RPU confirmado.</div>
        <div class="table-wrap">
            <table class="control-table">
                <thead>
                    <tr>
                        <th>Division</th>
                        <th>RPU</th>
                        <th>Nombre del servicio</th>
                        <th>Direccion</th>
                        <th>Poblacion</th>
                        <th>Tarifa</th>
                        <th>Vinculo</th>
                    </tr>
                </thead>
                <tbody id="cfe-catalog-body"></tbody>
            </table>
        </div>
        <div id="cfe-catalog-pager" class="risk-pager" hidden></div>
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
const catalogForm = document.getElementById('cfe-catalog-form');
const catalogStatus = document.getElementById('cfe-catalog-status');
const catalogBody = document.getElementById('cfe-catalog-body');
const catalogPager = document.getElementById('cfe-catalog-pager');
let currentRpu = '';
let catalogPage = 1;
const optionTimers = {};
let catalogAvailability = {};

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
        <small><b>Division CFE:</b> ${escapeHtml(cfe.division || 'Sin division')}</small>
        <small><b>Direccion CFE:</b> ${escapeHtml(cfe.direccion || 'Sin direccion')}</small>
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

function renderCatalog(data) {
    const rows = data.rpus || [];
    catalogAvailability = data.disponibles || catalogAvailability || {};
    updateCatalogAvailability();
    const missing = [];
    if (!Number(catalogAvailability.direccion || 0)) {
        missing.push('direccion_cfe no tiene datos cargados');
    }
    if (!Number(catalogAvailability.division || 0)) {
        missing.push('division_cfe no tiene datos cargados');
    }
    catalogStatus.textContent = `${number.format(data.total || 0)} RPUs encontrados en reportes CFE cargados.${missing.length ? ' Nota: ' + missing.join(' y ') + '.' : ''}`;
    catalogBody.innerHTML = rows.length
        ? rows.map((row) => {
            const linked = row.ccts && String(row.ccts).trim() !== '';
            return `<tr data-rpu="${escapeHtml(row.RPU)}">
                <td><strong>${escapeHtml(row.division_cfe || 'Sin division')}</strong><small>${escapeHtml(row.anio)}-${String(row.mes).padStart(2, '0')}</small></td>
                <td><button class="link-button" type="button" data-rpu="${escapeHtml(row.RPU)}">${escapeHtml(row.RPU)}</button></td>
                <td><strong>${escapeHtml(row.nombre_cfe || 'Sin nombre')}</strong><small>Total ${money.format(row.total || 0)} - ${number.format(row.consumo || 0)} kWh</small></td>
                <td>${escapeHtml(row.direccion_cfe || 'No cargada en cfe_consumos')}</td>
                <td>${escapeHtml(row.poblacion_cfe || 'Sin poblacion')}</td>
                <td><span class="status-pill">${escapeHtml(row.tarifa_cfe || 'N/D')}</span></td>
                <td><span class="status-pill ${linked ? 'status-ok' : 'status-warn'}">${linked ? escapeHtml(row.ccts) : 'Sin vinculo'}</span></td>
            </tr>`;
        }).join('')
        : '<tr><td colspan="7" class="empty-state"><i class="bi bi-search"></i><strong>Sin resultados</strong><span>Cambia los filtros o desactiva "solo sin vinculo".</span></td></tr>';
    catalogPager.hidden = Number(data.paginas || 1) <= 1;
    catalogPager.innerHTML = Number(data.paginas || 1) > 1
        ? `<button type="button" data-catalog-page="prev" ${Number(data.pagina || 1) <= 1 ? 'disabled' : ''}>Anterior</button><span>Pagina ${number.format(data.pagina || 1)} de ${number.format(data.paginas || 1)}</span><button type="button" data-catalog-page="next" ${Number(data.pagina || 1) >= Number(data.paginas || 1) ? 'disabled' : ''}>Siguiente</button>`
        : '';
}

function updateCatalogAvailability() {
    catalogForm.querySelectorAll('[data-optional-cfe]').forEach((input) => {
        const key = input.dataset.optionalCfe;
        const hasData = Number(catalogAvailability[key] || 0) > 0;
        input.disabled = !hasData;
        input.placeholder = hasData
            ? (key === 'direccion' ? 'Direccion del reporte' : 'Division')
            : (key === 'direccion' ? 'Direccion no cargada' : 'Division no cargada');
        if (!hasData) {
            input.value = '';
        }
    });
}

async function searchCatalog(page = 1) {
    catalogPage = page;
    catalogStatus.textContent = 'Buscando en reportes CFE cargados...';
    const data = new FormData(catalogForm);
    const body = new URLSearchParams();
    body.set('accion', 'buscar_catalogo_cfe');
    body.set('csrf', token);
    body.set('pagina', String(catalogPage));
    for (const [key, value] of data.entries()) {
        body.set(key, value);
    }
    if (!catalogForm.sin_vinculo.checked) {
        body.delete('sin_vinculo');
    }
    const response = await fetch('../controllers/rpuController.php', {method: 'POST', body});
    const result = await response.json();
    if (!result.ok) {
        throw new Error(result.error || 'No fue posible buscar en CFE.');
    }
    renderCatalog(result);
}

async function loadCfeOptions(input) {
    const campo = input.dataset.cfeOption;
    const list = document.getElementById(input.getAttribute('list'));
    if (!campo || !list) {
        return;
    }
    const body = new URLSearchParams({
        accion: 'opciones_catalogo_cfe',
        csrf: token,
        campo,
        termino: input.value.trim()
    });
    const formData = new FormData(catalogForm);
    for (const [key, value] of formData.entries()) {
        if (key !== campo) {
            body.set(key, value);
        }
    }
    if (!catalogForm.sin_vinculo.checked) {
        body.delete('sin_vinculo');
    }
    const response = await fetch('../controllers/rpuController.php', {method: 'POST', body});
    const data = await response.json();
    if (!data.ok) {
        return;
    }
    catalogAvailability = data.disponibles || catalogAvailability || {};
    updateCatalogAvailability();
    list.innerHTML = (data.opciones || []).map((value) => `<option value="${escapeHtml(value)}"></option>`).join('');
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

catalogBody.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-rpu]');
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

catalogForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    try {
        await searchCatalog(1);
    } catch (error) {
        catalogStatus.textContent = error.message;
    }
});

catalogForm.querySelectorAll('[data-cfe-option]').forEach((input) => {
    input.addEventListener('focus', () => loadCfeOptions(input));
    input.addEventListener('input', () => {
        clearTimeout(optionTimers[input.name]);
        optionTimers[input.name] = setTimeout(() => loadCfeOptions(input), 250);
    });
});

catalogPager.addEventListener('click', async (event) => {
    const button = event.target.closest('button[data-catalog-page]');
    if (!button) {
        return;
    }
    try {
        await searchCatalog(catalogPage + (button.dataset.catalogPage === 'next' ? 1 : -1));
    } catch (error) {
        catalogStatus.textContent = error.message;
    }
});

searchCatalog(1).catch((error) => {
    catalogStatus.textContent = error.message;
});
</script>
</body>
</html>
