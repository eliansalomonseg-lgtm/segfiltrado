<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/services/auth.php';
segRequireAdmin('dashboard.php');

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
    <title>Exportación anual de RPUs | SIEE Guerrero</title>
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
<main class="content annual-export-view">
    <section class="heading">
        <div>
            <span class="eyebrow">EXPORTACIÓN EJECUTIVA</span>
            <h1>Concentrado anual de RPUs</h1>
            <p>Genera un Excel por RPU con importes anuales, datos de servicio CFE y totales generales.</p>
        </div>
        <span class="alert-gold"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Solo administración</span>
    </section>

    <section class="results-card annual-export-card">
        <div class="annual-export-intro">
            <span class="quick-icon"><i class="bi bi-lightning-charge"></i></span>
            <div><span class="eyebrow">SELECCIÓN</span><h2>Ingresa los RPUs</h2><p>Pega uno por línea, separados por coma o por espacio. Se mostrará el nombre registrado en el reporte CFE.</p></div>
        </div>
        <form id="annual-export-form" class="annual-export-form">
            <label for="annual-rpus">RPUs a incluir</label>
            <textarea id="annual-rpus" name="rpus" rows="8" placeholder="Ejemplo:&#10;275920302458&#10;289960200152&#10;293190600871" required></textarea>
            <div class="annual-period-controls">
                <label><span>Desde mes</span><select id="annual-start-month"><?php foreach ([1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'] as $numero => $nombre): ?><option value="<?= $numero ?>" <?= $numero === 1 ? 'selected' : '' ?>><?= $nombre ?></option><?php endforeach; ?></select></label>
                <label><span>Desde año</span><input id="annual-start-year" type="number" min="2000" max="2100" value="2020"></label>
                <label><span>Hasta mes</span><select id="annual-end-month"><?php foreach ([1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'] as $numero => $nombre): ?><option value="<?= $numero ?>" <?= $numero === (int) date('n') ? 'selected' : '' ?>><?= $nombre ?></option><?php endforeach; ?></select></label>
                <label><span>Hasta año</span><input id="annual-end-year" type="number" min="2000" max="2100" value="<?= (int) date('Y') ?>"></label>
            </div>
            <div class="annual-export-actions">
                <button class="btn-seg" type="submit"><i class="bi bi-search me-2"></i>Comprobar servicios</button>
                <label class="annual-export-filter" for="annual-export-filter"><span>Exportar</span><select id="annual-export-filter" disabled><option value="all">Todos los RPUs</option><option value="found">Solo encontrados</option><option value="missing">Solo no encontrados</option></select></label>
                <button id="annual-export-download" class="btn-seg secondary" type="button" disabled><i class="bi bi-download me-2"></i>Descargar Excel</button>
            </div>
        </form>
        <p id="annual-export-status" class="annual-export-status" aria-live="polite">Ingresa los RPUs para identificar sus servicios CFE.</p>
    </section>

    <section id="annual-export-preview" class="results-card annual-export-preview" hidden>
        <div class="results-head">
            <div><span class="eyebrow">CONFIRMACIÓN</span><h2>Servicios localizados</h2><p class="section-note">El Excel incluirá nombre y dirección desde el último reporte CFE disponible de cada RPU.</p></div>
            <span id="annual-export-count" class="alert-gold">0 RPUs</span>
        </div>
        <div id="annual-export-list" class="annual-export-list"></div>
    </section>
</main>
<script>
const annualToken = document.querySelector('meta[name="csrf-token"]').content;
const annualForm = document.getElementById('annual-export-form');
const annualRpus = document.getElementById('annual-rpus');
const annualStatus = document.getElementById('annual-export-status');
const annualPreview = document.getElementById('annual-export-preview');
const annualList = document.getElementById('annual-export-list');
const annualCount = document.getElementById('annual-export-count');
const annualDownload = document.getElementById('annual-export-download');
const annualFilter = document.getElementById('annual-export-filter');
const annualStartMonth = document.getElementById('annual-start-month');
const annualStartYear = document.getElementById('annual-start-year');
const annualEndMonth = document.getElementById('annual-end-month');
const annualEndYear = document.getElementById('annual-end-year');
let annualTimer;
let annualReady = false;
let annualServices = [];

const annualEscape = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[character]));

async function annualJson(response) {
    const text = await response.text();
    try {
        const data = JSON.parse(text);
        if (!response.ok || !data.ok) throw new Error(data.error || 'No fue posible consultar los RPUs.');
        return data;
    } catch (error) {
        if (error instanceof SyntaxError) throw new Error('El servidor no devolvió una respuesta válida.');
        throw error;
    }
}

async function comprobarRpus() {
    const rpus = annualRpus.value.trim();
    if (!rpus) {
        annualReady = false;
        annualDownload.disabled = true;
        annualFilter.disabled = true;
        annualPreview.hidden = true;
        annualStatus.textContent = 'Ingresa al menos un RPU para continuar.';
        return;
    }
    annualReady = false;
    annualDownload.disabled = true;
    annualFilter.disabled = true;
    annualStatus.textContent = 'Buscando nombres en los reportes CFE...';
    const body = new URLSearchParams({
        accion: 'previsualizar_exportacion_anual', csrf: annualToken, rpus,
        mes_inicio: annualStartMonth.value, anio_inicio: annualStartYear.value,
        mes_fin: annualEndMonth.value, anio_fin: annualEndYear.value
    });
    try {
        const response = await fetch('../controllers/rpuController.php', {method: 'POST', headers: {'X-CSRF-Token': annualToken}, body});
        const data = await annualJson(response);
        annualServices = data.rpus || [];
        annualFilter.disabled = !annualServices.length;
        renderServiciosAnuales();
        annualPreview.hidden = false;
        const noLocalizados = annualServices.filter((servicio) => !servicio.encontrado).length;
        annualStatus.textContent = noLocalizados ? `${annualServices.length - noLocalizados} servicios localizados y ${noLocalizados} por revisar.` : `${annualServices.length} servicios listos para exportar.`;
    } catch (error) {
        annualPreview.hidden = true;
        annualFilter.disabled = true;
        annualStatus.textContent = error.message;
    }
}

function serviciosFiltradosAnuales() {
    if (annualFilter.value === 'found') return annualServices.filter((servicio) => servicio.encontrado);
    if (annualFilter.value === 'missing') return annualServices.filter((servicio) => !servicio.encontrado);
    return annualServices;
}

function renderServiciosAnuales() {
    const servicios = serviciosFiltradosAnuales();
    annualList.innerHTML = servicios.map((servicio, index) => `<article class="annual-export-service ${servicio.encontrado ? '' : 'not-found'}"><span>${index + 1}</span><strong>${annualEscape(servicio.rpu)}</strong><p>${annualEscape(servicio.nombre || 'No localizado en reportes CFE')}</p>${servicio.encontrado ? `<small><i class="bi bi-calendar3"></i> Ultimo reporte: ${annualEscape(servicio.ultimo_reporte)}</small>` : '<small><i class="bi bi-question-circle"></i> Sin reporte localizado</small>'}</article>`).join('') || '<div class="empty-state"><i class="bi bi-search"></i><strong>Sin RPUs para este filtro</strong><span>Cambia el filtro para ver los servicios restantes.</span></div>';
    annualCount.textContent = `${servicios.length} de ${annualServices.length} RPU${annualServices.length === 1 ? '' : 's'}`;
    annualReady = servicios.length > 0;
    annualDownload.disabled = !annualReady;
}

annualForm.addEventListener('submit', (event) => {
    event.preventDefault();
    comprobarRpus();
});

annualRpus.addEventListener('input', () => {
    annualReady = false;
    annualDownload.disabled = true;
    annualFilter.disabled = true;
    clearTimeout(annualTimer);
    if (annualRpus.value.trim().length < 4) return;
    annualTimer = window.setTimeout(comprobarRpus, 700);
});

[annualStartMonth, annualStartYear, annualEndMonth, annualEndYear].forEach((control) => control.addEventListener('change', () => {
    if (annualRpus.value.trim()) comprobarRpus();
}));

annualFilter.addEventListener('change', renderServiciosAnuales);

annualDownload.addEventListener('click', () => {
    if (!annualReady) return;
    const form = document.createElement('form');
    form.method = 'post';
    form.action = '../controllers/rpuController.php';
    form.innerHTML = `<input name="accion" value="exportar_rpus_anual"><input name="csrf" value="${annualEscape(annualToken)}"><input name="filtro_encontrados" value="${annualEscape(annualFilter.value)}"><input name="mes_inicio" value="${annualEscape(annualStartMonth.value)}"><input name="anio_inicio" value="${annualEscape(annualStartYear.value)}"><input name="mes_fin" value="${annualEscape(annualEndMonth.value)}"><input name="anio_fin" value="${annualEscape(annualEndYear.value)}"><textarea name="rpus">${annualEscape(annualRpus.value)}</textarea>`;
    document.body.appendChild(form);
    form.submit();
    form.remove();
});
</script>
</body>
</html>
