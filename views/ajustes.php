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
            <p>Sube un reporte mensual CFE para detectar periodos no bimestrales, cargos, diferencias y cobros sin consumo.</p>
        </div>
        <span class="alert-gold">Regla bimestral: 50 a 75 dias</span>
    </section>

    <section class="results-card adjustment-uploader">
        <form id="adjustment-form" class="adjustment-form">
            <input type="hidden" name="accion" value="analizar_ajustes_cfe">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['seg_csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <label class="adjustment-drop">
                <input type="file" name="reporte_cfe" accept=".xlsx,.xls" required>
                <span><i class="bi bi-file-earmark-spreadsheet"></i></span>
                <strong id="adjustment-file-name">Selecciona reporte CFE mensual</strong>
                <small>Ejemplo: 2025-04_M061_Reporte.xlsx</small>
            </label>
            <button class="btn-seg compact-action" type="submit"><i class="bi bi-search me-2"></i>Analizar ajustes</button>
            <button class="btn-seg compact-action btn-sync-catalogs" type="button" id="download-adjustments" disabled><i class="bi bi-download me-2"></i>Exportar alertas</button>
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
            <div><strong data-summary="periodo_bimestral">0</strong><span>Periodo bimestral</span></div>
            <small>Correctos</small>
        </article>
        <article class="quick-card">
            <span class="quick-icon"><i class="bi bi-cash-coin"></i></span>
            <div><strong data-summary="importe_total">0</strong><span>Importe total</span></div>
            <small>Facturado</small>
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

const money = new Intl.NumberFormat('es-MX', {style: 'currency', currency: 'MXN'});
const number = new Intl.NumberFormat('es-MX');

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char]));
}

function render(data) {
    currentRows = data.registros || [];
    Object.entries(data.resumen || {}).forEach(([key, value]) => {
        const target = summary.querySelector(`[data-summary="${key}"]`);
        if (target) {
            target.textContent = key === 'importe_total' ? money.format(value) : number.format(value);
        }
    });
    fileLabel.textContent = `${data.archivo || 'Reporte'} ${data.mes_reporte ? ' - ' + data.mes_reporte : ''}`;
    body.innerHTML = currentRows.filter((row) => row.alertas.length > 0).slice(0, 200).map((row) => {
        const level = row.severidad >= 7 - 'status-warn' : row.severidad >= 4 - '' : 'status-ok';
        return `<tr>
            <td><strong>${escapeHtml(row.rpu)}</strong><small>${escapeHtml(row.tarifa || 'Sin tarifa')}</small></td>
            <td><strong>${escapeHtml(row.nombre)}</strong><small>${escapeHtml(row.poblacion)}</small></td>
            <td><strong>${escapeHtml(row.desde)} / ${escapeHtml(row.hasta)}</strong><small>${escapeHtml(row.dias)} dias</small></td>
            <td><strong>${number.format(row.consumo || 0)}</strong><small>kWh</small></td>
            <td><strong>${money.format(row.total || 0)}</strong><small>Diferencia ${money.format(row.diferencia || 0)}</small></td>
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
    statusBox.textContent = 'Analizando reporte CFE y validando periodos bimestrales...';
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
        statusBox.textContent = `Analisis completado: ${number.format(json.resumen.con_alerta)} registros con alerta.`;
        render(json);
    } catch (error) {
        statusBox.textContent = error.message;
    }
});

form.reporte_cfe.addEventListener('change', () => {
    fileName.textContent = form.reporte_cfe.files[0]?.name || 'Selecciona reporte CFE mensual';
});

download.addEventListener('click', () => {
    const headers = ['RPU','NOMBRE','POBLACION','TARIFA','DESDE','HASTA','DIAS','CONSUMO','ENERGIA','DAP','CARGOS_DEPOSITOS','CREDITOS_REDONDEOS','TOTAL','DIFERENCIA','SEVERIDAD','ALERTAS'];
    const rows = currentRows.filter((row) => row.alertas.length > 0).map((row) => [
        row.rpu, row.nombre, row.poblacion, row.tarifa, row.desde, row.hasta, row.dias, row.consumo, row.energia, row.dap, row.cargos_depositos, row.creditos_redondeos, row.total, row.diferencia, row.severidad, row.alertas.join(' | ')
    ]);
    const csv = [headers, ...rows].map((row) => row.map((value) => `"${String(value ?? '').replace(/"/g, '""')}"`).join(',')).join('\n');
    const blob = new Blob(['\ufeff' + csv], {type: 'text/csv;charset=utf-8;'});
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'ajustes_cfe.csv';
    link.click();
    URL.revokeObjectURL(url);
});
</script>
</body>
</html>
