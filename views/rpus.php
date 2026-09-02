<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/services/auth.php';
segRequireLogin('../login.php');

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
    <link href="css/seg-executive.css" rel="stylesheet">
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
                <label class="rpu-print-filter"><span>Formato de impresión</span><select id="rpu-print-mode">
                    <option value="panoramic" selected>Gráfica panorámica completa (1 sola hoja)</option>
                    <option value="summary">Resumen anual detallado</option>
                    <option value="periods">Un periodo por hoja</option>
                </select></label>
                <button id="print-rpu-history" class="btn-seg compact-action" type="button"><i class="bi bi-printer me-2"></i>Imprimir RPU</button>
                <button id="print-rpu-table" class="btn btn-outline-dark compact-action" type="button"><i class="bi bi-table me-2"></i>Imprimir tabla</button>
                <button id="export-rpu-excel" class="btn btn-outline-dark compact-action" type="button"><i class="bi bi-file-earmark-excel me-2"></i>Exportar Excel</button>
            </div>
            <div id="rpu-chart" class="rpu-chart"></div>
        </article>
        <article class="results-card rpu-history-card">
            <div class="results-head">
                <div><span class="eyebrow">DETALLE</span><h2>Reportes del RPU</h2><p class="section-note">Consulta fechas, total, consumo y alertas de cada periodo.</p></div>
                <label class="rpu-history-filter rpu-meter-selector"><span>Medidor para lecturas e impresión</span><select id="rpu-history-meter" disabled><option value="all">Todos los medidores</option></select></label>
            </div>
            <div class="table-wrap">
                <table class="control-table">
                    <thead>
                        <tr>
                            <th>Periodo</th>
                            <th>Total</th>
                            <th>Consumo</th>
                            <th>Lecturas</th>
                            <th>Movimiento</th>
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
const historyMeterFilter = document.getElementById('rpu-history-meter');
const printModeFilter = document.getElementById('rpu-print-mode');
const printRpuHistory = document.getElementById('print-rpu-history');
const printRpuTable = document.getElementById('print-rpu-table');
const exportRpuExcel = document.getElementById('export-rpu-excel');
let currentRpu = '';
let currentHistory = [];
let currentSchool = {};
let currentCfe = {};
let currentSystemPeriod = '';

const money = new Intl.NumberFormat('es-MX', {style: 'currency', currency: 'MXN'});
const number = new Intl.NumberFormat('es-MX');
const lecturaNumber = new Intl.NumberFormat('es-MX', {maximumFractionDigits: 4});

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
}

function normalizar(value) {
    return String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toUpperCase();
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
    const plano = cfe.plano || {};
    return `<div class="compare-panel cfe-panel">
        <span class="compare-label">RECIBO CFE</span>
        <strong>${escapeHtml(cfe.rpu || currentRpu)} - ${escapeHtml(cfe.nombre || 'Sin nombre CFE')}</strong>
        <small><b>Division CFE:</b> ${escapeHtml(cfe.division || 'Sin division')}</small>
        <small><b>Direccion CFE:</b> ${escapeHtml(cfe.direccion || 'Sin direccion')}</small>
        <small><b>Poblacion CFE:</b> ${escapeHtml(cfe.poblacion || 'Sin poblacion')}</small>
        <small><b>Estado (archivo plano):</b> ${escapeHtml(plano.estado || 'Sin dato en archivo plano')}</small>
        <small><b>Tarifa:</b> ${escapeHtml(cfe.tarifa || 'Sin tarifa')} ${cfe.periodo ? `- <b>Periodo:</b> ${escapeHtml(cfe.periodo)}` : ''}</small>
    </div>`;
}

function enlaceMapaEscuela(escuela) {
    const latitud = Number(String(escuela.latitud ?? '').replace(',', '.'));
    const longitud = Number(String(escuela.longitud ?? '').replace(',', '.'));
    if (!Number.isFinite(latitud) || !Number.isFinite(longitud) || latitud < 14 || latitud > 20 || longitud < -106 || longitud > -94) {
        return '';
    }
    return `https://www.google.com/maps?q=${encodeURIComponent(`${latitud},${longitud}`)}`;
}

function schoolPanel(escuela, vinculado, rpu) {
    const turnoZona = [escuela.turno ? `<b>Turno:</b> ${escapeHtml(escuela.turno)}` : '', escuela.zona ? `<b>Zona:</b> ${escapeHtml(escuela.zona)}` : '', escuela.sector ? `<b>Sector:</b> ${escapeHtml(escuela.sector)}` : ''].filter(Boolean).join(' - ');
    const homoFuente = [escuela.homo ? `<b>HOMO:</b> ${escapeHtml(escuela.homo)}` : '', escuela.fuente ? `<b>Fuente:</b> ${escapeHtml(escuela.fuente)}` : ''].filter(Boolean).join(' - ');
    const mapaUrl = vinculado ? enlaceMapaEscuela(escuela) : '';
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
        ${mapaUrl ? `<a class="school-map-link" href="${mapaUrl}" target="_blank" rel="noopener"><i class="bi bi-geo-alt-fill"></i>Abrir ubicacion en Maps</a>` : ''}
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
        const proporcion = total / maxTotal;
        const nivel = proporcion >= .999 ? 'is-peak' : (proporcion >= .75 ? 'is-high' : (proporcion >= .4 ? 'is-medium' : 'is-low'));
        return `<div class="rpu-bar ${nivel}" title="${Math.round(proporcion * 100)}% del importe más alto de este RPU">
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
            <td>${lecturasFacturadas(row)}</td>
            <td>${etiquetaMovimiento(row)}</td>
            <td><span class="status-pill ${Number(row.severidad) >= 4 ? 'status-warn' : 'status-ok'}">Sev. ${escapeHtml(row.severidad || 0)}</span><small>${escapeHtml(row.alertas || 'Sin alertas')}</small></td>
        </tr>`).join('')
        : '<tr><td colspan="6" class="empty-state"><i class="bi bi-clock-history"></i><strong>Sin historial</strong><span>Analiza reportes en Ajustes CFE para alimentar esta vista.</span></td></tr>';
}

function diasFacturados(row) {
    const dias = Number(row.dias);
    return Number.isFinite(dias) && dias > 0 ? `${number.format(dias)} días facturados` : 'Días no disponibles';
}

function lecturasSeleccionadas(row) {
    const lecturas = Array.isArray(row.medidores) ? row.medidores : [];
    const instalados = lecturas.filter((lectura) => String(lectura.tipo_medidor || '') === 'INSTALADO');
    const consumo = instalados.filter((lectura) => esRegistroConsumo(lectura));
    const base = consumo.length ? consumo : instalados;
    return historyMeterFilter.value === 'all'
        ? base
        : base.filter((lectura) => String(lectura.numero_medidor || '') === historyMeterFilter.value);
}

function esRegistroConsumo(lectura) {
    const etiqueta = normalizar(lectura.ley_medidor || '');
    return etiqueta === '' || (etiqueta.startsWith('CONS') && !etiqueta.includes('RET'));
}

function detalleLectura(lectura) {
    if (lectura.lectura_anterior === null || lectura.lectura_anterior === '' || lectura.lectura_actual === null || lectura.lectura_actual === '') return '';
    const anterior = Number(lectura.lectura_anterior);
    const actual = Number(lectura.lectura_actual);
    if (!Number.isFinite(anterior) || !Number.isFinite(actual)) return '';
    const diferencia = Number(lectura.diferencia_lectura);
    const resultado = Number.isFinite(diferencia) ? diferencia : actual - anterior;
    return `${lecturaNumber.format(actual)} - ${lecturaNumber.format(anterior)} = ${lecturaNumber.format(resultado)}`;
}

function textoLecturas(row) {
    const lecturas = lecturasSeleccionadas(row);
    return lecturas.map((lectura) => {
        const detalle = detalleLectura(lectura);
        if (!detalle) return '';
        const medidor = String(lectura.numero_medidor || 'Sin serie');
        const registro = lecturas.length > 1 ? ` (${lectura.tipo_medidor || 'Registro'} ${lectura.posicion || 1})` : '';
        return `${medidor}${registro}: ${detalle}`;
    }).filter(Boolean).join(' | ');
}

function lecturasFacturadas(row) {
    const lecturas = lecturasSeleccionadas(row).map((lectura) => ({lectura, detalle: detalleLectura(lectura)})).filter((item) => item.detalle);
    const medidorSeleccionado = historyMeterFilter.value;
    return lecturas.length
        ? `<div class="rpu-reading-list">${lecturas.map(({lectura, detalle}) => { const multiplicador = Number(lectura.multiplicador); const diferencia = Number.isFinite(Number(lectura.diferencia_lectura)) ? Number(lectura.diferencia_lectura) : Number(lectura.lectura_actual) - Number(lectura.lectura_anterior); const consumoCalculado = Number.isFinite(multiplicador) && multiplicador > 0 ? diferencia * multiplicador : null; return `<div><small>${escapeHtml(`Medidor ${lectura.numero_medidor || 'Sin serie'} · Lectura de consumo`)}</small><strong>${escapeHtml(detalle)}</strong>${consumoCalculado !== null ? `<small>Multiplicador × ${escapeHtml(multiplicador)} · Consumo calculado ${escapeHtml(number.format(consumoCalculado))} kWh</small>` : ''}</div>`; }).join('')}</div>`
        : `<small>${escapeHtml(medidorSeleccionado === 'all' ? (Number(row.enriquecido_plano) === 1 ? 'Archivo plano sin lecturas disponibles' : 'Sin archivo plano') : 'Sin lectura para el medidor seleccionado')}</small>`;
}

function etiquetaMovimiento(row) {
    const codigo = String(row.tipo_movimiento || '').trim().padStart(2, '0');
    const etiquetas = {
        '01': ['Normal', 'movement-normal'],
        '04': ['Finiquito', 'movement-settlement'],
        '06': ['Ajuste (06)', 'movement-adjustment'],
        '09': ['Ajuste (09)', 'movement-adjustment']
    };
    const movimiento = etiquetas[codigo];
    const fuente = String(row.fuente || '') === 'PLANO_HISTORICO'
        ? 'Archivo plano histórico sin factura'
        : 'Archivo plano';
    if (!movimiento) {
        return `<span class="movement-pill movement-undetermined">Movimiento no determinado</span><small>${escapeHtml(fuente)}</small>`;
    }
    return `<span class="movement-pill ${movimiento[1]}">${movimiento[0]}</span><small>Código ${escapeHtml(codigo)} · ${escapeHtml(fuente)}</small>`;
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

function datosEncabezadoRpu() {
    return {
        nombre: currentCfe.nombre || currentSchool.nombre || 'Sin nombre registrado',
        cct: currentSchool.cct || '',
        domicilio: currentCfe.direccion || currentSchool.domicilio || 'Sin domicilio registrado',
        localidad: currentCfe.poblacion || currentSchool.localidad || '',
        municipio: currentSchool.municipio || ''
    };
}

function gruposAnuales(historial) {
    return Object.entries(historial.reduce((grupos, fila) => {
        (grupos[fila.anio] ||= []).push(fila);
        return grupos;
    }, {})).sort((a, b) => Number(a[0]) - Number(b[0]));
}

function tablaRpuAnual(historial, paraExcel = false, mostrarFechas = false) {
    return gruposAnuales(historial).map(([anio, filas]) => {
        const total = filas.reduce((suma, fila) => suma + Number(fila.total || 0), 0);
        const consumo = filas.reduce((suma, fila) => suma + Number(fila.consumo || 0), 0);
        const detalle = filas.slice().reverse().map((fila) => `<tr><td>${escapeHtml(fila.anio)}-${String(fila.mes).padStart(2, '0')}</td>${mostrarFechas ? `<td>${escapeHtml(fila.desde || 'Sin fecha')}</td><td>${escapeHtml(fila.hasta || 'Sin fecha')}</td>` : `<td>${escapeHtml(diasFacturados(fila))}</td>`}<td>${escapeHtml(fila.tarifa_cfe || 'Sin tarifa')}</td><td class="currency">${paraExcel ? Number(fila.total || 0).toFixed(2) : money.format(fila.total || 0)}</td><td>${paraExcel ? Number(fila.consumo || 0).toFixed(0) : `${number.format(fila.consumo || 0)} kWh`}</td><td>${escapeHtml(textoLecturas(fila) || 'Sin archivo plano')}</td><td>${escapeHtml(fila.tipo_movimiento || 'No determinado')}</td><td>${escapeHtml(fila.alertas || 'Sin alertas')}</td></tr>`).join('');
        const subtotal = mostrarFechas
            ? `<tr class="rpu-year-total"><td colspan="4">TOTAL DEL AÑO ${escapeHtml(anio)} - ${number.format(filas.length)} reportes</td><td class="currency">${paraExcel ? total.toFixed(2) : money.format(total)}</td><td>${paraExcel ? consumo.toFixed(0) : `${number.format(consumo)} kWh`}</td><td colspan="3"></td></tr>`
            : `<tr class="rpu-year-total"><td colspan="3">TOTAL DEL AÑO ${escapeHtml(anio)} - ${number.format(filas.length)} reportes</td><td class="currency">${paraExcel ? total.toFixed(2) : money.format(total)}</td><td>${paraExcel ? consumo.toFixed(0) : `${number.format(consumo)} kWh`}</td><td colspan="3"></td></tr>`;
        return `<tr class="rpu-year-label"><td colspan="${mostrarFechas ? 9 : 8}">AÑO ${escapeHtml(anio)}</td></tr>${detalle}${subtotal}`;
    }).join('');
}

function resumenGeneralRpu(historial) {
    return {
        reportes: historial.length,
        total: historial.reduce((suma, fila) => suma + Number(fila.total || 0), 0),
        consumo: historial.reduce((suma, fila) => suma + Number(fila.consumo || 0), 0)
    };
}

function nombrePeriodo(anio, mes) {
    const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    return `${meses[Math.max(1, Math.min(12, Number(mes) || 1)) - 1]} ${anio}`;
}

function nombrePeriodoClave(periodo) {
    const [anio, mes] = String(periodo || '').split('-');
    return anio && mes ? nombrePeriodo(anio, mes) : 'Sin registros';
}

function diferenciaMeses(periodoInicial, periodoFinal) {
    const [anioInicial, mesInicial] = String(periodoInicial || '').split('-').map(Number);
    const [anioFinal, mesFinal] = String(periodoFinal || '').split('-').map(Number);
    if (!anioInicial || !mesInicial || !anioFinal || !mesFinal) return Number.POSITIVE_INFINITY;
    return (anioFinal - anioInicial) * 12 + (mesFinal - mesInicial);
}

function esCobroBimestral(fila) {
    const tipo = normalizar(fila.tipo_periodo || '');
    const tarifa = normalizar(fila.tarifa_cfe || '').replace(/\s/g, '');
    if (tipo.includes('MENSUAL')) return false;
    if (tipo.includes('BIMESTRAL')) return true;
    return !['03', '68', '78'].includes(tarifa);
}

function estadoVigenciaRpu() {
    if (!currentHistory.length) {
        return {desde: 'Sin registros', hasta: 'Sin registros', estado: 'Sin historial'};
    }
    const primero = currentHistory[0];
    const ultimo = currentHistory[currentHistory.length - 1];
    const desdeClave = `${primero.anio}-${String(primero.mes).padStart(2, '0')}`;
    const hastaClave = `${ultimo.anio}-${String(ultimo.mes).padStart(2, '0')}`;
    const bimestral = esCobroBimestral(ultimo);
    const diferencia = diferenciaMeses(hastaClave, currentSystemPeriod);
    const activo = currentSystemPeriod !== '' && (diferencia === 0 || (bimestral && diferencia === 1));
    return {
        desde: nombrePeriodoClave(desdeClave),
        hasta: nombrePeriodoClave(hastaClave),
        estado: activo && bimestral && diferencia === 1 ? 'ACTIVO (BIMESTRAL)' : (activo ? 'ACTIVO' : 'INACTIVO'),
        activo
    };
}

function encabezadoFormalRpu(etiqueta) {
    const encabezado = datosEncabezadoRpu();
    const ubicacion = [encabezado.domicilio, encabezado.localidad, encabezado.municipio].filter(Boolean).map(escapeHtml).join(' - ');
    const cct = encabezado.cct ? `<p><b>CCT vinculado:</b> ${escapeHtml(encabezado.cct)}</p>` : '';
    return `<header class="rpu-export-header"><span>SECRETARIA DE EDUCACION GUERRERO</span><h1>RPU ${escapeHtml(currentRpu)}</h1><p class="rpu-export-name">${escapeHtml(encabezado.nombre)}</p>${cct}<p>${ubicacion}</p><small>${escapeHtml(etiqueta)} · ${escapeHtml(etiquetaMedidorSeleccionado())}</small></header>`;
}

function etiquetaMedidorSeleccionado() {
    return historyMeterFilter.value === 'all' ? 'Lecturas: todos los medidores' : `Lecturas: medidor ${historyMeterFilter.value}`;
}

function contenidoImpresionFormal(historial) {
    const hojas = gruposAnuales(historial).map(([anio, filas]) => `<section class="rpu-print-sheet" style="break-after:page;min-height:180mm">${encabezadoFormalRpu(`Relacion anual de pagos - ${anio}`)}<table class="rpu-export-table"><thead><tr><th>Periodo</th><th>Desde</th><th>Hasta</th><th>Tarifa</th><th>Importe pagado</th><th>Consumo</th><th>Lectura actual - anterior</th><th>Movimiento</th><th>Alertas</th></tr></thead><tbody>${tablaRpuAnual(filas, false, true)}</tbody></table></section>`).join('');
    const resumen = resumenGeneralRpu(historial);
    const vigencia = estadoVigenciaRpu();
    const filasResumen = gruposAnuales(historial).map(([anio, filas]) => {
        const totalAnual = resumenGeneralRpu(filas);
        return `<tr><td>AÑO ${escapeHtml(anio)}</td><td>${number.format(totalAnual.reportes)}</td><td>${money.format(totalAnual.total)}</td><td>${number.format(totalAnual.consumo)} kWh</td></tr>`;
    }).join('');
    const tablaResumen = `<table class="rpu-export-table"><thead><tr><th>Año</th><th>Reportes</th><th>Importe pagado</th><th>Consumo</th></tr></thead><tbody>${filasResumen}<tr class="rpu-year-total"><td>TOTAL DE TODOS LOS AÑOS</td><td>${number.format(resumen.reportes)}</td><td>${money.format(resumen.total)}</td><td>${number.format(resumen.consumo)} kWh</td></tr></tbody></table>`;
    const colorEstatus = vigencia.activo ? '#087957' : '#a34726';
    const tablaVigencia = `<table class="rpu-export-table" style="margin-bottom:16px"><thead><tr><th>Primer periodo con reporte</th><th>Ultimo periodo con reporte</th><th>Estatus del RPU</th></tr></thead><tbody><tr><td>${escapeHtml(vigencia.desde)}</td><td>${escapeHtml(vigencia.hasta)}</td><td style="color:${colorEstatus};font-weight:800">${escapeHtml(vigencia.estado)}</td></tr></tbody></table>`;
    return `${hojas}<section class="rpu-print-total" style="min-height:180mm">${encabezadoFormalRpu('Resumen general del historial')}${tablaVigencia}${tablaResumen}</section>`;
}

function escapeXml(value) {
    return String(value ?? '').replace(/[<>&'\"]/g, (char) => ({'<': '&lt;', '>': '&gt;', '&': '&amp;', "'": '&apos;', '"': '&quot;'}[char]));
}

function celdaExcel(valor, tipo = 'String', estilo = '') {
    return `<Cell${estilo ? ` ss:StyleID="${estilo}"` : ''}><Data ss:Type="${tipo}">${escapeXml(valor)}</Data></Cell>`;
}

function hojaExcelAnual(anio, filas) {
    const encabezado = datosEncabezadoRpu();
    const total = resumenGeneralRpu(filas);
    const datos = filas.slice().reverse().map((fila) => `<Row>${celdaExcel(`${fila.anio}-${String(fila.mes).padStart(2, '0')}`)}${celdaExcel(diasFacturados(fila))}${celdaExcel(fila.tarifa_cfe || 'Sin tarifa')}${celdaExcel(Number(fila.total || 0), 'Number', 'Currency')}${celdaExcel(Number(fila.consumo || 0), 'Number', 'Integer')}${celdaExcel(textoLecturas(fila) || 'Sin archivo plano')}${celdaExcel(fila.tipo_movimiento || 'No determinado')}${celdaExcel(fila.alertas || 'Sin alertas')}</Row>`).join('');
    const filaCct = encabezado.cct ? `<Row><Cell ss:MergeAcross="7"><Data ss:Type="String">CCT vinculado: ${escapeXml(encabezado.cct)}</Data></Cell></Row>` : '';
    return `<Worksheet ss:Name="${escapeXml(String(anio))}"><Table><Column ss:Width="90"/><Column ss:Width="105"/><Column ss:Width="65"/><Column ss:Width="105"/><Column ss:Width="90"/><Column ss:Width="155"/><Column ss:Width="90"/><Column ss:Width="220"/><Row><Cell ss:MergeAcross="7" ss:StyleID="Institution"><Data ss:Type="String">SECRETARIA DE EDUCACION GUERRERO</Data></Cell></Row><Row><Cell ss:MergeAcross="7" ss:StyleID="Title"><Data ss:Type="String">RPU ${escapeXml(currentRpu)}</Data></Cell></Row><Row><Cell ss:MergeAcross="7" ss:StyleID="Name"><Data ss:Type="String">${escapeXml(encabezado.nombre)}</Data></Cell></Row>${filaCct}<Row><Cell ss:MergeAcross="7"><Data ss:Type="String">${escapeXml([encabezado.domicilio, encabezado.localidad, encabezado.municipio].filter(Boolean).join(' - '))}</Data></Cell></Row><Row/><Row>${['Periodo', 'Días facturados', 'Tarifa', 'Importe pagado', 'Consumo', 'Lectura actual - anterior', 'Movimiento', 'Alertas'].map((titulo) => celdaExcel(titulo, 'String', 'TableHeader')).join('')}</Row>${datos}<Row>${celdaExcel(`TOTAL ${anio} - ${filas.length} reportes`, 'String', 'TotalLabel')}<Cell ss:MergeAcross="1" ss:StyleID="TotalLabel"/><Cell ss:StyleID="TotalLabel"/>${celdaExcel(total.total, 'Number', 'TotalCurrency')}${celdaExcel(total.consumo, 'Number', 'TotalInteger')}<Cell ss:MergeAcross="2" ss:StyleID="TotalLabel"/></Row></Table></Worksheet>`;
}

function libroExcelRpu(historial) {
    const grupos = gruposAnuales(historial);
    const resumen = resumenGeneralRpu(historial);
    const vigencia = estadoVigenciaRpu();
    const filasResumen = grupos.map(([anio, filas]) => {
        const total = resumenGeneralRpu(filas);
        return `<Row>${celdaExcel(anio)}${celdaExcel(filas.length, 'Number', 'Integer')}${celdaExcel(total.total, 'Number', 'Currency')}${celdaExcel(total.consumo, 'Number', 'Integer')}</Row>`;
    }).join('');
    const resumenHoja = `<Worksheet ss:Name="Resumen general"><Table><Column ss:Width="120"/><Column ss:Width="100"/><Column ss:Width="130"/><Column ss:Width="180"/><Row><Cell ss:MergeAcross="3" ss:StyleID="Institution"><Data ss:Type="String">SECRETARIA DE EDUCACION GUERRERO</Data></Cell></Row><Row><Cell ss:MergeAcross="3" ss:StyleID="Title"><Data ss:Type="String">RPU ${escapeXml(currentRpu)} - RESUMEN GENERAL</Data></Cell></Row><Row/><Row>${['Primer periodo con reporte', 'Ultimo periodo con reporte', 'Estatus del RPU'].map((titulo) => celdaExcel(titulo, 'String', 'TableHeader')).join('')}<Cell ss:StyleID="TableHeader"/></Row><Row>${celdaExcel(vigencia.desde)}${celdaExcel(vigencia.hasta)}${celdaExcel(vigencia.estado)}<Cell/></Row><Row/><Row>${['Año', 'Reportes', 'Importe pagado', 'Consumo'].map((titulo) => celdaExcel(titulo, 'String', 'TableHeader')).join('')}</Row>${filasResumen}<Row>${celdaExcel('TOTAL TODOS LOS AÑOS', 'String', 'TotalLabel')}${celdaExcel(resumen.reportes, 'Number', 'TotalInteger')}${celdaExcel(resumen.total, 'Number', 'TotalCurrency')}${celdaExcel(resumen.consumo, 'Number', 'TotalInteger')}</Row></Table></Worksheet>`;
    return '<' + '?xml version="1.0" encoding="UTF-8"?' + '>' + `<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Styles><Style ss:ID="Institution"><Font ss:Bold="1" ss:Color="#6A1B29" ss:Size="11"/></Style><Style ss:ID="Title"><Font ss:Bold="1" ss:Size="16"/></Style><Style ss:ID="Name"><Font ss:Bold="1" ss:Size="12"/></Style><Style ss:ID="TableHeader"><Font ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#6A1B29" ss:Pattern="Solid"/></Style><Style ss:ID="Currency"><NumberFormat ss:Format="\$#,##0.00"/></Style><Style ss:ID="Integer"><NumberFormat ss:Format="#\,##0"/></Style><Style ss:ID="TotalLabel"><Font ss:Bold="1"/><Interior ss:Color="#F8F0F1" ss:Pattern="Solid"/></Style><Style ss:ID="TotalCurrency"><Font ss:Bold="1"/><Interior ss:Color="#F8F0F1" ss:Pattern="Solid"/><NumberFormat ss:Format="\$#,##0.00"/></Style><Style ss:ID="TotalInteger"><Font ss:Bold="1"/><Interior ss:Color="#F8F0F1" ss:Pattern="Solid"/><NumberFormat ss:Format="#\,##0"/></Style></Styles>${grupos.map(([anio, filas]) => hojaExcelAnual(anio, filas)).join('')}${resumenHoja}</Workbook>`;
}

function contenidoTablaRpu(historial, paraExcel = false) {
    const encabezado = datosEncabezadoRpu();
    const etiqueta = historyYearFilter.value === 'all' ? 'Todos los años disponibles' : `Año ${historyYearFilter.value}`;
    return `<header class="rpu-export-header"><span>SECRETARIA DE EDUCACION GUERRERO</span><h1>RPU ${escapeHtml(currentRpu)}</h1><p><b>${escapeHtml(encabezado.nombre)}</b></p><p>${escapeHtml(encabezado.domicilio)}${encabezado.localidad ? ` - ${escapeHtml(encabezado.localidad)}` : ''}${encabezado.municipio ? ` - ${escapeHtml(encabezado.municipio)}` : ''}</p><small>${escapeHtml(etiqueta)} - Reportes CFE cargados</small></header><table class="rpu-export-table"><thead><tr><th>Periodo</th><th>Días facturados</th><th>Tarifa</th><th>Importe pagado</th><th>Consumo</th><th>Lectura actual - anterior</th><th>Movimiento</th><th>Alertas</th></tr></thead><tbody>${tablaRpuAnual(historial, paraExcel)}</tbody></table>`;
}

function validarTablaRpu() {
    const historial = historialFiltrado();
    if (!currentRpu || !historial.length) {
        statusBox.textContent = 'Consulta un RPU con historial antes de imprimir o exportar.';
        return null;
    }
    return historial;
}

function imprimirTablaRpu() {
    const historial = validarTablaRpu();
    if (!historial) return;
    const contenido = contenidoImpresionFormal(historial);
    const printWindow = window.open('', '_blank', 'width=1150,height=850');
    if (!printWindow) {
        statusBox.textContent = 'El navegador bloqueo la ventana de impresion. Permite ventanas emergentes e intentalo otra vez.';
        return;
    }
    printWindow.document.write(`<!doctype html><html lang="es"><head><meta charset="utf-8"><title>RPU ${escapeHtml(currentRpu)}</title><style>@page{size:landscape;margin:12mm}*{box-sizing:border-box;-webkit-print-color-adjust:exact;print-color-adjust:exact}body{color:#222;font-family:Arial,sans-serif;margin:0}.rpu-export-header{border-bottom:3px solid #6a1b29;margin-bottom:16px;padding-bottom:10px}.rpu-export-header span{color:#6a1b29;font-size:10px;font-weight:800;letter-spacing:1.1px}.rpu-export-header h1{font-size:26px;margin:5px 0}.rpu-export-header p{font-size:12px;margin:3px 0}.rpu-export-header small{color:#666;font-size:10px}.rpu-export-table{border-collapse:collapse;font-size:10px;width:100%}.rpu-export-table th{background:#6a1b29;color:#fff;text-align:left}.rpu-export-table th,.rpu-export-table td{border:1px solid #d9d4d0;padding:7px;vertical-align:top}.rpu-year-label td{background:#f6efe2;color:#6a1b29;font-size:11px;font-weight:800}.rpu-year-total td{background:#f8f0f1;font-weight:800}.rpu-export-table td:nth-child(5),.rpu-export-table td:nth-child(6){text-align:right;white-space:nowrap}</style></head><body>${contenido}</body></html>`);
    printWindow.document.close();
    printWindow.focus();
    window.setTimeout(() => printWindow.print(), 700);
}

function exportarTablaRpuExcel() {
    const historial = validarTablaRpu();
    if (!historial) return;
    const libro = libroExcelRpu(historial);
    const archivo = new Blob(['\ufeff', libro], {type: 'application/vnd.ms-excel;charset=utf-8'});
    const enlace = document.createElement('a');
    const etiqueta = historyYearFilter.value === 'all' ? 'historial' : historyYearFilter.value;
    enlace.href = URL.createObjectURL(archivo);
    enlace.download = `RPU_${currentRpu}_${etiqueta}.xls`;
    document.body.appendChild(enlace);
    enlace.click();
    enlace.remove();
    URL.revokeObjectURL(enlace.href);
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
    const encabezado = datosEncabezadoRpu();
    const maxTotal = Math.max(...historial.map((fila) => Number(fila.total) || 0), 1);

    // Generar vista panorámica de 1 sola hoja con todos los reportes (limpia y comprensible)
    const hojaPanoramicaCompleta = (filas) => {
        const cronologico = filas.slice().sort((a, b) => (Number(a.anio) - Number(b.anio)) || (Number(a.mes) - Number(b.mes)));
        const maximo = Math.max(...cronologico.map((f) => Number(f.total) || 0), 1);
        const suma = resumenHistorial(cronologico);
        const totalBarras = cronologico.length;
        const barWidth = Math.max(8, Math.min(24, Math.floor(940 / Math.max(totalBarras, 1))));

        // Generar barras limpias con montos arriba y periodos perfectamente legibles abajo
        const barras = cronologico.map((fila, idx) => {
            const tot = Number(fila.total) || 0;
            const con = Number(fila.consumo) || 0;
            const esCero = con === 0;
            const esAjuste = String(fila.tipo_movimiento || '') === '06' || String(fila.tipo_movimiento || '') === '09' || (fila.alertas && fila.alertas.toLowerCase().includes('ajuste')) || (tot > 15000);
            
            // Escala visual ponderada para que los cobros normales de $240-$1,500 sean perfectamente visibles junto a picos grandes
            const height = esAjuste
                ? 110
                : Math.max(10, Math.round(Math.pow(tot / maximo, 0.45) * 95));
            
            const barClass = esAjuste ? 'bar-ajuste' : (esCero ? 'bar-cero' : 'bar-normal');
            const valClass = esAjuste ? 'bar-val-top is-ajuste' : (esCero ? 'bar-val-top is-cero' : 'bar-val-top');
            
            const periodoTexto = `${String(fila.mes).padStart(2,'0')}/${String(fila.anio).slice(-2)}`;
            const infoTooltip = `Periodo: ${fila.mes}/${fila.anio} | Total: ${money.format(tot)} | Consumo: ${number.format(con)} kWh ${esAjuste ? '(AJUSTE CFE)' : ''}`;

            return `<div class="panoramic-bar" style="width:${barWidth}px" title="${escapeHtml(infoTooltip)}">
                <span class="${valClass}" title="${money.format(tot)}">${money.format(tot)}</span>
                <i class="${barClass}" style="height:${height}px"></i>
                <span class="bar-date">${periodoTexto}</span>
            </div>`;
        }).join('');

        // Resumen agrupado por año para tabla inferior compacta (sin NaN)
        const porAnio = cronologico.reduce((acc, f) => {
            (acc[f.anio] ||= []).push(f);
            return acc;
        }, {});
        
        const filasAnuales = Object.entries(porAnio).map(([anio, items]) => {
            const totAnio = items.reduce((s, i) => s + (Number(i.total) || 0), 0);
            const conAnio = items.reduce((s, i) => s + (Number(i.consumo) || 0), 0);
            const ceros = items.filter(i => (Number(i.consumo) || 0) === 0).length;
            const ajustes = items.filter(i => String(i.tipo_movimiento || '') === '06' || String(i.tipo_movimiento || '') === '09' || (Number(i.total) || 0) > 15000).length;
            
            let estadoTexto = '<span class="badge-ok">Consumo normal</span>';
            if (ceros === items.length) {
                estadoTexto = `<span class="badge-cero">100% en 0 kWh (${ceros} recibos)</span>`;
            } else if (ceros > 0) {
                estadoTexto = `<span class="badge-cero">${ceros} recibos en 0 kWh</span>`;
            }
            if (ajustes > 0) {
                estadoTexto += ` <span class="badge-ajuste">⚠️ ${ajustes} ajuste CFE</span>`;
            }

            return `<tr>
                <td><strong>${anio}</strong></td>
                <td>${items.length} recibos</td>
                <td>${number.format(conAnio)} kWh</td>
                <td><strong>${money.format(totAnio)}</strong></td>
                <td>${estadoTexto}</td>
            </tr>`;
        }).join('');

        return `<section class="panoramic-sheet">
            <header class="panoramic-header">
                <div class="header-titles">
                    <span>GOBIERNO DEL ESTADO DE GUERRERO · SECRETARÍA DE EDUCACIÓN GUERRERO</span>
                    <h1>EXPEDIENTE DE CONSUMO Y FACTURACIÓN CFE · RPU ${escapeHtml(currentRpu)}</h1>
                    <p><strong>${escapeHtml(encabezado.nombre)}</strong> ${encabezado.cct ? `(CCT: ${escapeHtml(encabezado.cct)})` : ''} · ${escapeHtml(encabezado.domicilio || '')} ${encabezado.municipio ? `· ${escapeHtml(encabezado.municipio)}` : ''}</p>
                </div>
                <div class="header-badge">
                    <span>TARIFA CFE</span>
                    <strong>${escapeHtml(currentCfe.tarifa || '02 / PDBT')}</strong>
                </div>
            </header>

            <div class="kpi-strip">
                <div class="kpi-item"><span class="kpi-lbl">TOTAL FACTURADO ACUMULADO</span><strong class="kpi-val">${money.format(suma.total_acumulado)}</strong></div>
                <div class="kpi-item"><span class="kpi-lbl">CONSUMO TOTAL ACUMULADO</span><strong class="kpi-val">${number.format(suma.consumo_acumulado)} kWh</strong></div>
                <div class="kpi-item"><span class="kpi-lbl">TOTAL DE REPORTES REGISTRADOS</span><strong class="kpi-val">${number.format(suma.registros)} reportes CFE</strong></div>
                <div class="kpi-item"><span class="kpi-lbl">MEDIDOR / ESTATUS</span><strong class="kpi-val">${escapeHtml(etiquetaMedidorSeleccionado() || 'Registrado en sistema')}</strong></div>
            </div>

            <div class="chart-section">
                <div class="chart-title-bar">
                    <span>HISTORIAL CRONOLÓGICO DE FACTURACIÓN (TODOS LOS PERIODOS CARGADOS)</span>
                    <div class="chart-legend">
                        <span><i class="dot dot-normal"></i> Facturación con consumo</span>
                        <span><i class="dot dot-cero"></i> Factura en 0 kWh (cargo base / DAP)</span>
                        <span><i class="dot dot-ajuste"></i> Ajuste / Refacturación CFE</span>
                    </div>
                </div>
                <div class="panoramic-chart-wrap">
                    <div class="panoramic-chart">${barras}</div>
                </div>
            </div>

            <div class="summary-bottom">
                <table class="compact-year-table">
                    <thead>
                        <tr>
                            <th>Año fiscal</th>
                            <th>Reportes</th>
                            <th>Consumo anual</th>
                            <th>Total facturado</th>
                            <th>Comportamiento operativo anual</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${filasAnuales}
                        <tr class="row-total">
                            <td><strong>TOTAL HISTÓRICO</strong></td>
                            <td><strong>${number.format(suma.registros)} reportes</strong></td>
                            <td><strong>${number.format(suma.consumo_acumulado)} kWh</strong></td>
                            <td><strong>${money.format(suma.total_acumulado)}</strong></td>
                            <td><strong>Expediente completo</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <footer class="panoramic-footer">
                <span>Expediente oficial generado desde el Sistema de Gestión Energética SEG · Fecha de emisión: ${new Date().toLocaleDateString('es-MX', {year:'numeric', month:'long', day:'numeric'})}</span>
                <span>Documento oficial de consulta</span>
            </footer>
        </section>`;
    };

    const resumenEnHoja = (filas, etiqueta) => {
        const suma = resumenHistorial(filas);
        const maximo = Math.max(...filas.map((fila) => Number(fila.total) || 0), 1);
        const barras = filas.map((fila) => {
            const height = Math.max(10, Math.round((Number(fila.total) || 0) / maximo * 180));
            return `<div class="bar"><i style="height:${height}px"></i><strong>${money.format(fila.total || 0)}</strong><small>${escapeHtml(fila.anio)}-${String(fila.mes).padStart(2, '0')}<br>${number.format(fila.consumo || 0)} kWh</small></div>`;
        }).join('');
        return `<section class="summary-sheet"><header><span>SECRETARÍA DE EDUCACIÓN GUERRERO</span><h1>RPU ${escapeHtml(currentRpu)}</h1><p>${escapeHtml(etiqueta)} · ${number.format(suma.registros)} reportes · Total acumulado ${money.format(suma.total_acumulado)} · Consumo ${number.format(suma.consumo_acumulado)} kWh</p><small>${escapeHtml(etiquetaMedidorSeleccionado())}</small></header><div class="chart">${barras}</div><table><thead><tr><th>Periodo</th><th>Total</th><th>Consumo</th><th>Tarifa</th><th>Alertas</th></tr></thead><tbody>${filas.slice().reverse().map((fila) => `<tr><td>${escapeHtml(fila.anio)}-${String(fila.mes).padStart(2, '0')}</td><td>${money.format(fila.total || 0)}</td><td>${number.format(fila.consumo || 0)} kWh</td><td>${escapeHtml(fila.tarifa_cfe || 'Sin tarifa')}</td><td>${escapeHtml(fila.alertas || 'Sin alertas')}</td></tr>`).join('')}</tbody></table></section>`;
    };
    const detallePorPeriodo = historial.map((fila, index) => `<section class="period-sheet"><header><span>SECRETARÍA DE EDUCACIÓN GUERRERO</span><h1>RPU ${escapeHtml(currentRpu)}</h1><p>Periodo ${escapeHtml(fila.anio)}-${String(fila.mes).padStart(2, '0')} · Hoja ${index + 1} de ${historial.length}</p><small>${escapeHtml(etiquetaMedidorSeleccionado())}</small></header><div class="period-grid"><div><span>Total facturado</span><strong>${money.format(fila.total || 0)}</strong></div><div><span>Consumo</span><strong>${number.format(fila.consumo || 0)} kWh</strong></div><div><span>Tarifa</span><strong>${escapeHtml(fila.tarifa_cfe || 'Sin tarifa')}</strong></div><div><span>Fechas del recibo</span><strong>${escapeHtml(fila.desde || 'Sin fecha')} / ${escapeHtml(fila.hasta || 'Sin fecha')}</strong></div></div><div class="single-bar"><i style="height:${Math.max(30, Math.round((Number(fila.total) || 0) / maxTotal * 260))}px"></i><strong>${money.format(fila.total || 0)}</strong></div><p class="alerts"><b>Lectura:</b> ${escapeHtml(textoLecturas(fila) || 'Sin lectura para el medidor seleccionado')}</p><p class="alerts"><b>Alertas:</b> ${escapeHtml(fila.alertas || 'Sin alertas')}</p></section>`).join('');
    const resumenesPorAnio = historyYearFilter.value === 'all'
        ? Object.values(historial.reduce((grupos, fila) => {
            (grupos[fila.anio] ||= []).push(fila);
            return grupos;
        }, {})).sort((a, b) => Number(a[0].anio) - Number(b[0].anio)).map((filas) => resumenEnHoja(filas, `Año ${filas[0].anio}`)).join('')
        : resumenEnHoja(historial, yearLabel);

    let content = '';
    if (mode === 'panoramic') {
        content = hojaPanoramicaCompleta(historial);
    } else if (mode === 'periods') {
        content = detallePorPeriodo;
    } else {
        content = resumenesPorAnio;
    }

    const printWindow = window.open('', '_blank', 'width=1200,height=900');
    if (!printWindow) {
        statusBox.textContent = 'El navegador bloqueo la ventana de impresión. Permite ventanas emergentes e inténtalo otra vez.';
        return;
    }
    printWindow.document.write(`<!doctype html><html lang="es"><head><meta charset="utf-8"><title>RPU ${escapeHtml(currentRpu)} - Expediente</title><style>
@page { size: landscape; margin: 8mm 10mm; }
* { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
body { color: #222; font-family: 'Segoe UI', Arial, sans-serif; margin: 0; background: #fff; }

/* HOJA PANORÁMICA 1 SOLA PÁGINA */
.panoramic-sheet { height: 100vh; max-height: 190mm; display: flex; flex-direction: column; justify-content: space-between; page-break-inside: avoid; page-break-after: avoid; }
.panoramic-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2.5px solid #6a1b29; padding-bottom: 6px; }
.header-titles span { color: #6a1b29; font-size: 9px; font-weight: 800; letter-spacing: 1px; display: block; }
.header-titles h1 { font-size: 20px; margin: 2px 0; color: #111; font-weight: 800; }
.header-titles p { margin: 0; font-size: 11px; color: #444; }
.header-badge { background: #fbf6ee; border: 1.5px solid #bfa276; border-radius: 6px; padding: 4px 10px; text-align: center; }
.header-badge span { display: block; font-size: 8px; color: #6a1b29; font-weight: 700; }
.header-badge strong { font-size: 14px; color: #6a1b29; }

/* STRIP DE KPIS (4 COLUMNAS LIMPIAS) */
.kpi-strip { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin: 10px 0; }
.kpi-item { background: #fdfbf9; border: 1px solid #e7ded4; border-radius: 6px; padding: 7px 12px; border-left: 3.5px solid #6a1b29; }
.kpi-lbl { font-size: 8.5px; color: #777; font-weight: 700; display: block; letter-spacing: 0.5px; }
.kpi-val { font-size: 14px; color: #111; font-weight: 800; margin-top: 2px; display: block; }

/* SECCIÓN DE GRÁFICA PANORÁMICA */
.chart-section { background: #fff; border: 1px solid #e7ded4; border-radius: 8px; padding: 10px 14px 12px 14px; flex: 1; display: flex; flex-direction: column; justify-content: space-between; min-height: 250px; max-height: 275px; margin-bottom: 8px; }
.chart-title-bar { display: flex; justify-content: space-between; align-items: center; font-size: 9.5px; font-weight: 800; color: #6a1b29; margin-bottom: 6px; border-bottom: 1px dashed #eee; padding-bottom: 4px; }
.chart-legend { display: flex; gap: 14px; font-size: 9px; color: #444; }
.chart-legend span { display: flex; align-items: center; gap: 5px; }
.dot { display: inline-block; width: 9px; height: 9px; border-radius: 2px; }
.dot-normal { background: linear-gradient(180deg, #bfa276 0%, #6a1b29 100%); }
.dot-cero { background: #d0c8be; border: 1px solid #999; }
.dot-ajuste { background: #d9534f; }

.panoramic-chart-wrap { position: relative; width: 100%; height: 215px; display: flex; flex-direction: column; justify-content: flex-end; background: repeating-linear-gradient(to top, #fcfcfc 0px, #fcfcfc 35px, #f3f0eb 36px); border-radius: 4px; padding: 6px 6px 0 6px; border-bottom: 2px solid #6a1b29; }
.panoramic-chart { display: flex; align-items: flex-end; justify-content: space-between; gap: 4px; height: 100%; width: 100%; }
.panoramic-bar { display: flex; flex-direction: column; align-items: center; justify-content: flex-end; position: relative; height: 100%; }
.panoramic-bar i { display: block; width: 100%; border-radius: 3px 3px 0 0; min-height: 5px; transition: height 0.2s ease; }
.bar-normal { background: linear-gradient(180deg, #bfa276 0%, #6a1b29 100%); border: 1px solid #52141f; }
.bar-cero { background: #e2ded9; border-top: 2px solid #8e8780; border-left: 1px solid #ccc; border-right: 1px solid #ccc; }
.bar-ajuste { background: #d9534f; border: 1px solid #b52b27; box-shadow: 0 0 6px rgba(217,83,79,0.5); }

/* Montos arriba de cada periodo */
.bar-val-top { font-size: 7.5px; font-weight: 800; color: #333; writing-mode: vertical-rl; transform: rotate(180deg); margin-bottom: 4px; white-space: nowrap; font-family: 'Segoe UI', Arial, sans-serif; letter-spacing: -0.2px; line-height: 1; text-shadow: 0 0 2px #fff; }
.bar-val-top.is-ajuste { color: #c02622; font-weight: 900; background: #fee2e2; border-radius: 2px; padding: 2px 1px; }
.bar-val-top.is-cero { color: #888; font-weight: 600; }

/* Fechas de periodo abajo */
.bar-date { font-size: 8px; color: #6a1b29; font-weight: 800; writing-mode: vertical-rl; transform: rotate(180deg); margin-top: 5px; margin-bottom: 2px; white-space: nowrap; font-family: monospace; letter-spacing: 0.5px; background: #faf6f0; border-radius: 2px; padding: 3px 1px; border: 0.5px solid #e7ded4; }

/* TABLA INFERIOR COMPACTA */
.summary-bottom { margin-bottom: 6px; }
.compact-year-table { width: 100%; border-collapse: collapse; font-size: 9px; }
.compact-year-table th { background: #6a1b29; color: #fff; padding: 4.5px 8px; text-align: left; font-size: 8.5px; letter-spacing: 0.5px; }
.compact-year-table td { padding: 3.5px 8px; border: 1px solid #e7ded4; font-size: 9px; }
.compact-year-table tr:nth-child(even) { background: #fdfbf9; }
.compact-year-table tr.row-total { background: #f6efe2; font-weight: 800; border-top: 2px solid #6a1b29; }
.badge-cero { background: #f0e6e6; color: #a94442; padding: 1.5px 5px; border-radius: 3px; font-weight: 700; font-size: 8px; }
.badge-ok { color: #2e6b30; font-weight: 700; font-size: 8px; }
.badge-ajuste { background: #f2dede; color: #a94442; padding: 1.5px 5px; border-radius: 3px; font-weight: 800; font-size: 8px; }

/* FOOTER */
.panoramic-footer { display: flex; justify-content: space-between; font-size: 8px; color: #888; border-top: 1px solid #e7ded4; padding-top: 4px; }

/* ESTILOS DE FORMATOS ANTERIORES */
.summary-sheet, .period-sheet { break-after: page; min-height: 180mm; }
.chart { align-items: end; display: flex; gap: 10px; justify-content: space-around; min-height: 265px; padding: 18px 4px; }
.bar { align-items: center; display: grid; gap: 6px; justify-items: center; min-width: 58px; }
.bar i, .single-bar i { background: #bfa276; border: 2px solid #6a1b29; border-radius: 8px 8px 2px 2px; display: block; width: 25px; }
.bar strong { font-size: 10px; text-align: center; }
.bar small { color: #555; font-size: 9px; line-height: 1.3; text-align: center; }
table { border-collapse: collapse; font-size: 10px; width: 100%; }
th { background: #6a1b29; color: #fff; text-align: left; }
th, td { border: 1px solid #d9d4d0; padding: 6px; vertical-align: top; }
.period-grid { display: grid; gap: 10px; grid-template-columns: repeat(4, 1fr); }
.period-grid div { border: 1px solid #d9d4d0; padding: 12px; }
.period-grid span, .period-grid strong { display: block; }
.period-grid span { color: #666; font-size: 11px; }
.period-grid strong { font-size: 17px; margin-top: 5px; }
.single-bar { align-items: center; display: grid; gap: 10px; justify-content: center; justify-items: center; min-height: 310px; }
.single-bar strong { font-size: 20px; }
.alerts { border-top: 1px solid #d9d4d0; font-size: 12px; padding-top: 10px; }

@media print {
    .summary-sheet:last-child, .period-sheet:last-child, .panoramic-sheet:last-child { break-after: auto; }
}
</style></head><body>${content}</body></html>`);
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

function cargarMedidoresHistorial(historial) {
    const seleccionado = historyMeterFilter.value;
    const medidores = [...new Set(historial.flatMap((fila) => Array.isArray(fila.medidores) ? fila.medidores : []).filter((lectura) => String(lectura.tipo_medidor || '') === 'INSTALADO' && esRegistroConsumo(lectura)).map((lectura) => String(lectura.numero_medidor || '').trim()).filter(Boolean))].sort();
    historyMeterFilter.innerHTML = `<option value="all">Todos los medidores</option>${medidores.map((medidor) => `<option value="${escapeHtml(medidor)}">Medidor ${escapeHtml(medidor)}</option>`).join('')}`;
    historyMeterFilter.disabled = medidores.length === 0;
    historyMeterFilter.value = medidores.includes(seleccionado) ? seleccionado : 'all';
}

function render(data) {
    currentHistory = data.historial || [];
    currentSchool = (data.vinculos || [])[0] || {};
    currentCfe = data.cfe || {};
    currentSystemPeriod = data.ultimo_periodo_sistema || '';
    cargarAniosHistorial(currentHistory);
    cargarMedidoresHistorial(currentHistory);
    renderSchool(data);
    aplicarFiltroHistorial();
    summary.hidden = false;
    workspace.hidden = false;
    historyZone.hidden = false;
}

async function searchRpu(rpu) {
    currentRpu = rpu;
    historyMeterFilter.value = 'all';
    const button = form.querySelector('button[type="submit"]');
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Cargando expediente';
    form.closest('.rpu-search-card').classList.add('is-loading');
    statusBox.textContent = `Abriendo expediente del RPU ${rpu}...`;
    const body = new URLSearchParams({accion: 'buscar_rpu', csrf: token, rpu, incluir_sugerencias: '0'});
    try {
        const response = await fetch('../controllers/rpuController.php', {method: 'POST', body});
        const data = await response.json();
        if (!data.ok) {
            throw new Error(data.error || 'No fue posible consultar el RPU.');
        }
        statusBox.textContent = data.encontrado ? `Expediente cargado para RPU ${data.rpu}.` : `No hay historial guardado para RPU ${data.rpu}.`;
        render(data);
    } finally {
        button.disabled = false;
        button.innerHTML = '<i class="bi bi-search me-2"></i>Consultar RPU';
        form.closest('.rpu-search-card').classList.remove('is-loading');
    }
}

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    try {
        await searchRpu(form.rpu.value.trim());
    } catch (error) {
        statusBox.textContent = error.message;
    }
});

const rpuInicial = new URLSearchParams(window.location.search).get('rpu')?.trim() || '';
if (/^[A-Za-z0-9]{4,20}$/.test(rpuInicial)) {
    form.rpu.value = rpuInicial;
    searchRpu(rpuInicial).catch((error) => {
        statusBox.textContent = error.message;
    });
}

historyYearFilter.addEventListener('change', aplicarFiltroHistorial);
historyMeterFilter.addEventListener('change', aplicarFiltroHistorial);
printRpuHistory.addEventListener('click', abrirImpresionRpu);
printRpuTable.addEventListener('click', imprimirTablaRpu);
exportRpuExcel.addEventListener('click', exportarTablaRpuExcel);

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
