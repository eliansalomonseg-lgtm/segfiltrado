<?php

declare(strict_types=1);

session_start();

if (empty($_SESSION['seg_csrf'])) {
    $_SESSION['seg_csrf'] = bin2hex(random_bytes(24));
}

$segBasePath = '../';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['seg_csrf'], ENT_QUOTES, 'UTF-8') ?>">
    <title>Consolidación Predictiva | SEG Guerrero</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../../assets/css/seg-executive.css" rel="stylesheet">
    <style>
        /* Consolidacion-specific styles only */
        .drop-grid{align-items:center;display:grid;grid-template-columns:1fr 38px 1fr}
        .drop-zone.ready{background:#bfa27618;box-shadow:inset 0 0 0 1px var(--seg-gold);transform:translateY(-2px)}
        .cross{align-items:center;background:var(--seg-guinda);border:5px solid #fff;border-radius:50%;color:var(--seg-gold);display:flex;font-size:18px;height:38px;justify-content:center;position:relative;width:38px;z-index:2}
        .progress-box{margin:20px auto 0;max-width:560px}
        .progress-track{background:#e9e5df;border-radius:20px;height:9px;overflow:hidden}
        .progress-bar{background:linear-gradient(90deg,var(--seg-guinda),var(--seg-gold));height:100%;transition:width .25s;width:0}
        .progress-text{color:var(--seg-muted);display:block;font-size:11px;margin-top:8px;text-align:center}
        .summary strong{color:var(--seg-guinda);font-size:18px}
        .options{display:grid;gap:7px;min-width:430px}
        .option{align-items:center;background:#faf8f5;border:1px solid var(--seg-border);border-radius:12px;display:grid;gap:9px;grid-template-columns:1fr auto auto auto auto;padding:9px}
        .option.done{background:#e7f4eb;border-color:#4a8b60}
        .option-data small{line-height:1.35}
        .confirm.saved{background:#347a51}
        .empty{color:#8a5c60;font-style:italic}
        .tag{background:var(--seg-text);border-radius:12px;color:#fff;display:inline-block;font-size:9px;margin-top:6px;padding:4px 7px}
        .tag.address{background:#6f5838}
        .source-stack{display:grid;gap:14px}
        .source-stack .drop-zone{min-height:154px;padding:18px}
        .source-stack .file-icon{height:42px;margin-bottom:10px;width:42px}
        .sync-status{align-items:center;color:#6a5434;display:flex;font-size:11px;font-weight:700;gap:8px;justify-content:center;margin-top:12px}
        .mass-divider{align-items:center;color:var(--seg-gold);display:flex;font-size:20px;font-weight:800;justify-content:center}
        .manual-search{background:var(--seg-text);border:0;border-radius:7px;color:#fff;cursor:pointer;font-size:10px;font-weight:700;margin-top:10px;padding:8px 10px}
        .school-search-panel{display:grid;gap:8px;grid-template-columns:1fr 1fr;margin-top:8px}
        .school-search-panel .swal2-input,.school-search-panel .swal2-select{box-sizing:border-box;font-size:12px;height:42px;margin:0;width:100%}
        .search-school-list{display:grid;gap:8px;margin-top:12px;max-height:360px;overflow:auto;text-align:left}
        .search-school-item{align-items:center;background:#faf8f5;border:1px solid var(--seg-border);border-radius:8px;display:grid;gap:10px;grid-template-columns:1fr auto auto;padding:10px}
        .search-school-item strong{display:block;font-size:12px}
        .search-school-item small{color:var(--seg-muted);display:block;font-size:11px;margin-top:3px}
        .search-school-item button{background:var(--seg-guinda);border:0;border-radius:7px;color:#fff;cursor:pointer;font-size:10px;font-weight:700;padding:8px 10px}
        .file-icon{align-items:center;background:var(--seg-guinda);border-radius:11px;color:#fff;display:flex;font-size:12px;font-weight:800;height:52px;justify-content:center;margin-bottom:14px;width:52px}
        .file-icon.seg{background:var(--seg-text);color:var(--seg-gold)}
        .file-name{background:#eeeae4;border-radius:20px;color:#65696d;font-size:10px;font-style:normal;font-weight:700;margin-top:13px;max-width:90%;overflow:hidden;padding:6px 10px;text-overflow:ellipsis;white-space:nowrap}
        .source-title span{align-items:center;background:var(--seg-guinda);border-radius:8px;color:#fff;display:flex;height:30px;justify-content:center;width:30px}
        .btn-sync-catalogs{align-items:center;background:var(--seg-gold);border:0;border-radius:8px;color:#302719;cursor:pointer;display:flex;font-size:12px;font-weight:800;gap:8px;justify-content:center;margin-top:16px;padding:12px 14px;width:100%}
        .btn-sync-catalogs:disabled{cursor:wait;opacity:.7}
        .result-search{border:1px solid #dfe4ec;border-radius:11px;font-size:12px;min-width:280px;padding:10px 12px}
        @media(max-width:900px){.drop-grid{gap:10px;grid-template-columns:1fr}.cross{margin:-19px auto}.options{min-width:360px}}
        @media(max-width:600px){.workflow{align-items:stretch;flex-direction:column}.workflow span{text-align:center}}
    </style>
</head>
<body>
<?php include_once dirname(__DIR__) . '/fragments/navbar.php'; ?>
<?php include_once dirname(__DIR__) . '/fragments/sidebar.php'; ?>
<main class="workspace">
    <section class="heading">
        <div>
            <span class="eyebrow">EMPAREJAMIENTO BILATERAL</span>
            <h1>Consolidación Predictiva</h1>
            <p>Consolida estructura educativa, movimientos 911 y dos periodos de consumo CFE.</p>
        </div>
        <span class="alert-gold">STATUS activo y nivel educativo tienen prioridad</span>
    </section>
    <div class="workflow" aria-label="Flujo de consolidación">
        <span>1. Cargar archivos</span>
        <span>2. Revisar sugerencias</span>
        <span>3. Confirmar vínculos</span>
    </div>
    <section class="quick-actions" aria-label="Metricas rapidas">
        <article class="quick-card">
            <span class="quick-icon"><i class="bi bi-building-check"></i></span>
            <div><strong id="metric-schools">0</strong><span>Total Escuelas Publicas</span></div>
            <small>Catalogos</small>
        </article>
        <article class="quick-card">
            <span class="quick-icon"><i class="bi bi-graph-up-arrow"></i></span>
            <div><strong id="metric-progress">0%</strong><span>Porcentaje de Avance</span></div>
            <small>Vinculos</small>
        </article>
        <article class="quick-card">
            <span class="quick-icon"><i class="bi bi-exclamation-triangle"></i></span>
            <div><strong id="metric-alerts">0</strong><span>Casos con Alerta</span></div>
            <small>Revision</small>
        </article>
    </section>
    <form id="cross-form" class="upload-card" enctype="multipart/form-data">
        <input type="hidden" name="accion" value="procesar_archivos">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['seg_csrf'], ENT_QUOTES, 'UTF-8') ?>">
        <div class="row g-3 align-items-stretch">
            <div class="col-lg-6">
                <section class="source-column">
                    <div class="source-title"><span>SEG</span>Estructura educativa</div>
                    <div class="source-stack">
                        <label class="drop-zone" data-input="archivo_seg">
                            <input id="archivo_seg" name="archivo_seg" type="file" accept=".csv,.xlsx,.xls" required>
                            <span class="file-icon seg">1</span>
                            <strong>1. Catálogo Institucional SEG</strong>
                            <small>CCT, plantel, municipio, localidad, status y nivel</small>
                            <em class="file-name">Seleccionar CSV o Excel</em>
                        </label>
                        <label class="drop-zone" data-input="archivo_oficializacion">
                            <input id="archivo_oficializacion" name="archivo_oficializacion" type="file" accept=".xlsx,.xls" required>
                            <span class="file-icon seg">2</span>
                            <strong>2. Oficialización Básica (Datos 911)</strong>
                            <small>CV_CCT, plantel, municipio, localidad y tipo</small>
                            <em class="file-name">Seleccionar archivo Excel</em>
                        </label>
                    </div>
                    <button id="sync-catalogs" class="btn-sync-catalogs" type="button">📥 Sincronizar Catálogos en Base de Datos</button>
                    <div id="sync-status" class="sync-status" hidden>
                        <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                        <span>Guardando escuelas en base de datos local...</span>
                    </div>
                </section>
            </div>
            <div class="col-lg-6">
                <section class="source-column">
                    <div class="source-title"><span>CFE</span>Reportes de consumo</div>
                    <div class="source-stack">
                        <label class="drop-zone" data-input="archivo_cfe_a">
                            <input id="archivo_cfe_a" name="archivo_cfe_a" type="file" accept=".xlsx,.xls" required>
                            <span class="file-icon">3</span>
                            <strong>3. Reporte CFE - Periodo A</strong>
                            <small>RPU, nombre, dirección, población y tarifa</small>
                            <em class="file-name">Seleccionar archivo Excel</em>
                        </label>
                        <label class="drop-zone" data-input="archivo_cfe_b">
                            <input id="archivo_cfe_b" name="archivo_cfe_b" type="file" accept=".xlsx,.xls">
                            <span class="file-icon">4</span>
                            <strong>4. Reporte CFE - Periodo B (opcional)</strong>
                            <small>RPU, nombre, dirección, población y tarifa</small>
                            <em class="file-name">Seleccionar archivo Excel</em>
                        </label>
                    </div>
                </section>
            </div>
        </div>
        <button class="btn-seg" type="submit">Procesar Consolidación Masiva</button>
        <div id="progress" class="progress-box" hidden>
            <div class="progress-track"><div id="progress-bar" class="progress-bar"></div></div>
            <span id="progress-text" class="progress-text">Preparando archivos...</span>
        </div>
    </form>
    <section id="results" class="results-card" hidden>
        <div class="results-head">
            <div><span class="eyebrow">RESULTADOS</span><h2>RPUs únicos y escuelas sugeridas</h2></div>
            <div class="results-tools">
                <button id="auto-link-safe" class="btn btn-success btn-sm" type="button">⚡ Auto-Vincular Casos Seguros (≥50%)</button>
                <button id="export-links" class="btn btn-outline-dark btn-sm" type="button">Exportar vinculos</button>
                <input id="result-search" class="result-search" type="search" placeholder="Buscar RPU, CCT o escuela">
                <div id="summary" class="summary"></div>
            </div>
        </div>
        <div class="module-tabs" aria-label="Estados de coincidencias">
            <button class="active" type="button">Pendientes</button>
            <button type="button">Auto-Vinculados</button>
            <button type="button">Conflictos</button>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>RPU CFE</th><th>Referencia del recibo</th><th>3 mejores escuelas de Guerrero</th></tr></thead>
                <tbody id="matches"></tbody>
            </table>
        </div>
    </section>
</main>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const form = document.getElementById('cross-form');
    const token = document.querySelector('meta[name="csrf-token"]').content;
    const controller = '../../controllers/escuelaController.php';
    const syncButton = document.getElementById('sync-catalogs');
    const syncStatus = document.getElementById('sync-status');
    const resultSearch = document.getElementById('result-search');
    const autoLinkSafe = document.getElementById('auto-link-safe');
    const exportLinks = document.getElementById('export-links');
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[character]));
    const parseServerJson = text => {
        try {
            return JSON.parse(text || '{"ok":false,"error":"El servidor no devolvio una respuesta."}');
        } catch (error) {
            const clean = String(text || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
            return {ok:false,error:clean || 'El servidor devolvio una respuesta no valida.'};
        }
    };
    document.querySelectorAll('.drop-zone').forEach(zone => {
        const input = document.getElementById(zone.dataset.input);
        const update = () => {
            zone.classList.toggle('ready', input.files.length > 0);
            zone.querySelector('.file-name').textContent = input.files[0]?.name || (input.id === 'archivo_seg' ? 'Seleccionar CSV o Excel' : 'Seleccionar archivo Excel');
        };
        input.addEventListener('change', update);
        ['dragenter','dragover'].forEach(name => zone.addEventListener(name, event => {
            event.preventDefault();
            zone.classList.add('dragging');
        }));
        ['dragleave','drop'].forEach(name => zone.addEventListener(name, event => {
            event.preventDefault();
            zone.classList.remove('dragging');
        }));
        zone.addEventListener('drop', event => {
            const files = new DataTransfer();
            Array.from(event.dataTransfer.files).slice(0, 1).forEach(file => files.items.add(file));
            input.files = files.files;
            update();
        });
    });
    syncButton.addEventListener('click', async () => {
        const catalogo = document.getElementById('archivo_seg').files[0];
        const oficializacion = document.getElementById('archivo_oficializacion').files[0];
        if (!catalogo && !oficializacion) {
            Swal.fire({icon:'warning',title:'Sin catálogos',text:'Carga al menos un catálogo para sincronizar.',confirmButtonColor:'#6c1d24'});
            return;
        }
        const body = new FormData();
        body.append('accion', 'sincronizar_catalogos');
        body.append('csrf', token);
        if (catalogo) body.append('catalogo_seg', catalogo);
        if (oficializacion) body.append('oficializacion_911', oficializacion);
        syncButton.disabled = true;
        syncStatus.hidden = false;
        try {
            const response = await fetch(controller,{method:'POST',headers:{'X-CSRF-Token':token},body});
            const data = parseServerJson(await response.text());
            if (!response.ok || !data.ok) throw new Error(data.error || 'No fue posible sincronizar los catálogos.');
            Swal.fire({icon:'success',title:'Catálogos sincronizados',text:`Se guardaron ${data.total} escuelas en la base local.`,confirmButtonColor:'#6c1d24'});
        } catch (error) {
            Swal.fire({icon:'error',title:'Error de sincronización',text:error.message,confirmButtonColor:'#6c1d24'});
        } finally {
            syncButton.disabled = false;
            syncStatus.hidden = true;
        }
    });
    form.addEventListener('submit', event => {
        event.preventDefault();
        const button = form.querySelector('.btn-seg');
        const progress = document.getElementById('progress');
        const bar = document.getElementById('progress-bar');
        const text = document.getElementById('progress-text');
        const request = new XMLHttpRequest();
        button.disabled = true;
        progress.hidden = false;
        bar.style.width = '8%';
        text.textContent = 'Empaquetando los archivos de información...';
        request.open('POST', controller);
        request.setRequestHeader('X-CSRF-Token', token);
        request.upload.onprogress = upload => {
            if (upload.lengthComputable) {
                const percentage = Math.round(upload.loaded / upload.total * 58);
                bar.style.width = `${Math.max(8, percentage)}%`;
                text.textContent = `Cargando archivos: ${Math.round(upload.loaded / upload.total * 100)}%`;
            }
        };
        request.upload.onload = () => {
            bar.style.width = '75%';
            text.textContent = 'Unificando escuelas, reporte CFE y calculando coincidencias...';
        };
        request.onload = () => {
            button.disabled = false;
            try {
                const data = parseServerJson(request.responseText);
                if (request.status < 200 || request.status >= 300 || !data.ok) throw new Error(data.error || 'No fue posible ejecutar el cruce.');
                bar.style.width = '100%';
                text.textContent = 'Cruce predictivo completado';
                renderResults(data);
                Swal.fire({icon:'success',title:'Cruce terminado',text:`Se analizaron ${data.resumen.rpu_unicos} RPUs únicos.`,confirmButtonColor:'#6c1d24'});
            } catch (error) {
                Swal.fire({icon:'error',title:'Error de procesamiento',text:error.message,confirmButtonColor:'#6c1d24'});
            }
        };
        request.onerror = () => {
            button.disabled = false;
            Swal.fire({icon:'error',title:'Error de conexión',text:'No fue posible contactar al controlador.',confirmButtonColor:'#6c1d24'});
        };
        request.send(new FormData(form));
    });
    resultSearch.addEventListener('input', () => {
        if (window.currentResults && window.currentSummary) {
            renderResults({resultados:window.currentResults,resumen:window.currentSummary}, false);
        }
    });
    autoLinkSafe.addEventListener('click', autoLinkSafeCases);
    exportLinks.addEventListener('click', () => {
        const exportForm = document.createElement('form');
        exportForm.method = 'POST';
        exportForm.action = controller;
        exportForm.style.display = 'none';
        [['accion','exportar_vinculos'],['csrf',token]].forEach(([name, value]) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            exportForm.appendChild(input);
        });
        document.body.appendChild(exportForm);
        exportForm.submit();
        exportForm.remove();
    });
    const normalizeSearch = value => String(value ?? '').toUpperCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    function resultMatchesSearch(registro, term) {
        if (!term) return true;
        const fields = [registro.rpu, registro.nombre_cfe, registro.direccion_cfe, registro.poblacion_cfe, registro.tarifa_cfe];
        (registro.vinculos_confirmados || []).forEach(vinculo => fields.push(vinculo.cct, vinculo.nombre_escuela, vinculo.direccion_escuela));
        (registro.opciones || []).forEach(option => fields.push(option.cct, option.nombre_escuela, option.direccion_escuela, option.municipio, option.localidad, option.subnivel, option.origen));
        return normalizeSearch(fields.join(' ')).includes(term);
    }
    function scoreClass(option) {
        const score = Number(option.similitud ?? option.score ?? 0);
        if (score >= 69) return 'score-high';
        if (score >= 50) return 'score-manual';
        return 'score-low';
    }
    function updateQuickMetrics(data) {
        const resultados = data.resultados || [];
        const total = Number(data.resumen?.registros_seg || data.resumen?.registros_catalogo_seg || 0);
        const vinculados = resultados.filter(registro => registro.vinculo_confirmado || (registro.vinculos_confirmados || []).length).length;
        const avance = resultados.length ? Math.round(vinculados / resultados.length * 100) : 0;
        const alertas = resultados.filter(registro => !(registro.opciones || []).some(option => Number(option.similitud ?? option.score ?? 0) >= 69)).length;
        document.getElementById('metric-schools').textContent = total.toLocaleString('es-MX');
        document.getElementById('metric-progress').textContent = `${avance}%`;
        document.getElementById('metric-alerts').textContent = alertas.toLocaleString('es-MX');
    }
    function renderResults(data, scroll = true) {
        window.currentResults = data.resultados;
        window.currentSummary = data.resumen;
        updateQuickMetrics(data);
        const tbody = document.getElementById('matches');
        const term = normalizeSearch(resultSearch.value);
        const registros = data.resultados.map((registro, row) => ({registro,row})).filter(item => resultMatchesSearch(item.registro, term));
        tbody.innerHTML = registros.length ? registros.map(({registro, row}) => {
            const opciones = registro.opciones || [];
            const options = opciones.length ? opciones.map((option, index) => `
                <div class="option ${option.vinculado ? 'done' : ''} ${scoreClass(option)}">
                    <div class="option-data"><strong>${escapeHtml(option.cct)} · ${escapeHtml(option.nombre_escuela)}</strong><small>${escapeHtml(option.municipio)} · ${escapeHtml(option.localidad)} · ${escapeHtml(option.subnivel)} · STATUS ${escapeHtml(option.status)}</small></div>
                    <span class="tag address">${escapeHtml(option.direccion_escuela || 'Sin direccion oficial')}</span>
                    <span class="tag">${escapeHtml(option.origen || 'Sin origen')}</span>
                    <span class="score">${Number(option.similitud).toFixed(1)}%</span>
                    <button class="confirm" type="button" data-row="${row}" data-option="${index}">Confirmar Vínculo</button>
                </div>
            `).join('') : '<span class="empty">Sin escuelas coincidentes en esta localidad</span>';
            const linked = registro.vinculos_confirmados?.length ? registro.vinculos_confirmados.map(vinculo => `<span class="tag">Vinculado: ${escapeHtml(vinculo.cct)}${vinculo.nombre_escuela ? ' - ' + escapeHtml(vinculo.nombre_escuela) : ''}</span>`).join('') : '';
            const linkedDirections = registro.vinculos_confirmados?.length ? registro.vinculos_confirmados.filter(vinculo => vinculo.direccion_escuela).map(vinculo => `<span class="tag address">${escapeHtml(vinculo.direccion_escuela)}</span>`).join('') : '';
            return `<tr><td><strong>${escapeHtml(registro.rpu)}</strong><span class="tag">${escapeHtml(registro.tarifa_cfe || 'Sin tarifa')}</span>${linked}${linkedDirections}<button class="manual-search" type="button" data-row="${row}">Buscar CCT para este RPU</button></td><td><strong>${escapeHtml(registro.nombre_cfe)}</strong><small>${escapeHtml(registro.direccion_cfe)}<br>${escapeHtml(registro.poblacion_cfe)}</small></td><td><div class="options">${options}</div></td></tr>`;
        }).join('') : '<tr><td colspan="3"><span class="empty">Sin resultados para la busqueda actual</span></td></tr>';
        tbody.querySelectorAll('.option.done .confirm').forEach(button => {
            button.textContent = 'Quitar vinculo';
            button.classList.add('saved');
            button.classList.add('remove-link');
        });
        tbody.querySelectorAll('.remove-link').forEach(button => button.addEventListener('click', () => {
            removeLink(data.resultados[Number(button.dataset.row)], Number(button.dataset.option), button);
        }));
        tbody.querySelectorAll('.confirm:not(.remove-link)').forEach(button => button.addEventListener('click', () => {
            confirmLink(data.resultados[Number(button.dataset.row)], Number(button.dataset.option), button);
        }));
        tbody.querySelectorAll('.manual-search').forEach(button => button.addEventListener('click', () => {
            openSchoolSearch(data.resultados[Number(button.dataset.row)]);
        }));
        const summary = data.resumen;
        document.getElementById('summary').innerHTML = `<strong>${registros.length}</strong> de ${summary.rpu_unicos} RPUs · ${summary.rpu_con_sugerencias} con sugerencias`;
        const results = document.getElementById('results');
        results.hidden = false;
        if (scroll) results.scrollIntoView({behavior:'smooth'});
    }
    function getSafeLinkCases() {
        const selected = new Map();
        (window.currentResults || []).forEach(registro => {
            (registro.opciones || []).forEach(option => {
                const score = Number(option.similitud ?? option.score ?? 0);
                if (score >= 50 && !option.vinculado && registro.rpu && option.cct) {
                    selected.set(`${option.cct}|${registro.rpu}`, {
                        CCT: option.cct,
                        RPU: registro.rpu,
                        nombre_recibo_cfe: registro.nombre_cfe || '',
                        poblacion_cfe: registro.poblacion_cfe || '',
                        tarifa_cfe: registro.tarifa_cfe || ''
                    });
                }
            });
        });
        return Array.from(selected.values());
    }
    async function autoLinkSafeCases() {
        const vinculos = getSafeLinkCases();
        if (!vinculos.length) {
            Swal.fire({icon:'info',title:'Sin casos seguros',text:'No hay sugerencias pendientes con score mayor o igual al 50%.',confirmButtonColor:'#6c1d24'});
            return;
        }
        const decision = await Swal.fire({icon:'question',title:'Auto-vincular casos seguros',text:`Se insertarán ${vinculos.length} vínculos con alta certeza.`,showCancelButton:true,confirmButtonText:'Auto-vincular',cancelButtonText:'Cancelar',confirmButtonColor:'#198754',cancelButtonColor:'#212529'});
        if (!decision.isConfirmed) return;
        autoLinkSafe.disabled = true;
        const body = new URLSearchParams({accion:'auto_vincular_masivo',csrf:token,vinculos:JSON.stringify(vinculos)});
        try {
            const response = await fetch(controller,{method:'POST',headers:{'X-CSRF-Token':token},body});
            const data = parseServerJson(await response.text());
            if (!response.ok || !data.ok) throw new Error(data.error || 'No fue posible auto-vincular los casos seguros.');
            const linkedKeys = new Set(vinculos.map(vinculo => `${vinculo.CCT}|${vinculo.RPU}`));
            (window.currentResults || []).forEach(registro => {
                registro.vinculos_confirmados = registro.vinculos_confirmados || [];
                (registro.opciones || []).forEach(option => {
                    if (linkedKeys.has(`${option.cct}|${registro.rpu}`)) {
                        option.vinculado = true;
                        if (!registro.vinculos_confirmados.some(vinculo => vinculo.cct === option.cct)) {
                            registro.vinculos_confirmados.push({cct:option.cct,nombre_escuela:option.nombre_escuela,direccion_escuela:option.direccion_escuela});
                        }
                    }
                });
                registro.vinculo_confirmado = registro.vinculos_confirmados.length > 0;
            });
            renderResults({resultados:window.currentResults,resumen:window.currentSummary}, false);
            Swal.fire({icon:'success',title:'Auto-vinculación completada',text:`Se han procesado e insertado automáticamente ${data.total} escuelas con alta certeza`,confirmButtonColor:'#198754'});
        } catch (error) {
            Swal.fire({icon:'error',title:'No se pudo auto-vincular',text:error.message,confirmButtonColor:'#6c1d24'});
        } finally {
            autoLinkSafe.disabled = false;
        }
    }
    function optionFromSchool(escuela) {
        return {
            cct: escuela.CCT,
            nombre_escuela: escuela.NOMBRECT,
            direccion_escuela: escuela.DOMICILIO || '',
            municipio: escuela.NOMBREMUN || '',
            localidad: escuela.NOMBRELOC || '',
            subnivel: escuela.SUBNIVEL || escuela.NIVEL || '',
            status: escuela.STATUS || '',
            origen: escuela.ORIGEN || 'Base local',
            similitud: 100,
            vinculado: false
        };
    }
    async function searchSchools(filters) {
        const body = new URLSearchParams({accion:'buscar_escuelas',csrf:token,q:filters.q || '',nivel:filters.nivel || '',poblacion:filters.poblacion || '',origen:filters.origen || ''});
        const response = await fetch(controller,{method:'POST',headers:{'X-CSRF-Token':token},body});
        const data = parseServerJson(await response.text());
        if (!response.ok || !data.ok) throw new Error(data.error || 'No fue posible buscar escuelas.');
        return data.escuelas || [];
    }
    async function openSchoolSearch(registro) {
        let lastResults = [];
        let selectedSchool = null;
        const modal = await Swal.fire({
            title:'Buscar escuela para vincular',
            html:`<div class="school-search-panel"><input id="school-search-input" class="swal2-input" placeholder="CCT, nombre, HOMO, zona o sector"><select id="school-level-input" class="swal2-select"><option value="">Todos los niveles</option><option value="PREESCOLAR">Preescolar</option><option value="PRIMARIA">Primaria</option><option value="SECUNDARIA">Secundaria</option><option value="TELESECUNDARIA">Telesecundaria</option></select><input id="school-location-input" class="swal2-input" placeholder="Poblacion, localidad, municipio o comunidad"><select id="school-source-input" class="swal2-select"><option value="">SEG y Oficializacion</option><option value="Catalogo SEG">Catalogo SEG</option><option value="Oficializacion">Oficializacion</option></select></div><div id="school-search-list" class="search-school-list"><span class="empty">Busca por nivel, poblacion/comunidad o texto de la escuela.</span></div>`,
            showConfirmButton:false,
            showCancelButton:true,
            cancelButtonText:'Cerrar',
            cancelButtonColor:'#212529',
            didOpen: () => {
                const input = document.getElementById('school-search-input');
                const level = document.getElementById('school-level-input');
                const location = document.getElementById('school-location-input');
                const source = document.getElementById('school-source-input');
                const list = document.getElementById('school-search-list');
                let timer = null;
                const renderList = escuelas => {
                    lastResults = escuelas;
                    list.innerHTML = escuelas.length ? escuelas.map((escuela, index) => `
                        <div class="search-school-item">
                            <div><strong>${escapeHtml(escuela.CCT)} - ${escapeHtml(escuela.NOMBRECT)}</strong><small>${escapeHtml(escuela.NOMBREMUN)} - ${escapeHtml(escuela.NOMBRELOC)} - ${escapeHtml(escuela.NIVEL || escuela.SUBNIVEL)} - ${escapeHtml(escuela.HOMO || 'Sin HOMO')} - STATUS ${escapeHtml(escuela.STATUS)}</small><small>${escapeHtml(escuela.TURNO || 'Sin turno')} - Zona ${escapeHtml(escuela.ZONA || escuela.CCT_ZONA || 'N/D')} - ${escapeHtml(escuela.ORIGEN || 'Base local')}</small></div>
                            <span class="tag address">${escapeHtml(escuela.DOMICILIO || 'Sin direccion oficial')}</span>
                            <button type="button" data-school="${index}">Vincular</button>
                        </div>
                    `).join('') : '<span class="empty">Sin escuelas encontradas.</span>';
                };
                const runSearch = () => {
                    clearTimeout(timer);
                    const filters = {q:input.value.trim(),nivel:level.value,poblacion:location.value.trim(),origen:source.value};
                    if (filters.q.length < 2 && filters.poblacion.length < 2 && !filters.nivel && !filters.origen) {
                        list.innerHTML = '<span class="empty">Busca por nivel, poblacion/comunidad o texto de la escuela.</span>';
                        return;
                    }
                    list.innerHTML = '<span class="empty">Buscando...</span>';
                    timer = setTimeout(async () => {
                        try {
                            renderList(await searchSchools(filters));
                        } catch (error) {
                            list.innerHTML = `<span class="empty">${escapeHtml(error.message)}</span>`;
                        }
                    }, 280);
                };
                input.addEventListener('input', runSearch);
                location.addEventListener('input', runSearch);
                level.addEventListener('change', runSearch);
                source.addEventListener('change', runSearch);
                if (registro.poblacion_cfe) {
                    location.value = registro.poblacion_cfe;
                    runSearch();
                }
                list.addEventListener('click', event => {
                    const button = event.target.closest('button[data-school]');
                    if (!button) return;
                    const escuela = lastResults[Number(button.dataset.school)];
                    if (escuela) {
                        selectedSchool = escuela;
                        Swal.close();
                    }
                });
                input.focus();
            }
        });
        if (selectedSchool) {
            await confirmSelectedOption(registro, optionFromSchool(selectedSchool), null);
        }
    }
    async function confirmLink(registro, optionIndex, button) {
        const option = registro.opciones[optionIndex];
        await confirmSelectedOption(registro, option, button);
    }
    async function confirmSelectedOption(registro, option, button) {
        const decision = await Swal.fire({icon:'question',title:'Confirmar vínculo',html:`<b>${escapeHtml(registro.rpu)}</b><br>${escapeHtml(option.cct)} · ${escapeHtml(option.nombre_escuela)}`,showCancelButton:true,confirmButtonText:'Confirmar Vínculo',cancelButtonText:'Cancelar',confirmButtonColor:'#6c1d24',cancelButtonColor:'#212529'});
        if (!decision.isConfirmed) return;
        if (button) button.disabled = true;
        const body = new URLSearchParams({accion:'confirmar_vinculo',csrf:token,cct:option.cct,rpu:registro.rpu,nombre_recibo_cfe:registro.nombre_cfe || '',poblacion_cfe:registro.poblacion_cfe || '',tarifa_cfe:registro.tarifa_cfe || ''});
        try {
            const response = await fetch(controller,{method:'POST',headers:{'X-CSRF-Token':token},body});
            const data = parseServerJson(await response.text());
            if (!response.ok || !data.ok) throw new Error(data.error || 'No fue posible guardar el vínculo.');
            option.vinculado = true;
            registro.opciones = registro.opciones || [];
            if (!registro.opciones.some(item => item.cct === option.cct)) {
                registro.opciones.unshift(option);
            }
            registro.vinculos_confirmados = registro.vinculos_confirmados || [];
            if (!registro.vinculos_confirmados.some(vinculo => vinculo.cct === option.cct)) {
                registro.vinculos_confirmados.push({cct:option.cct,nombre_escuela:option.nombre_escuela,direccion_escuela:option.direccion_escuela});
            }
            registro.vinculo_confirmado = true;
            renderResults({resultados:window.currentResults,resumen:window.currentSummary}, false);
            Swal.fire({icon:'success',title:'Vínculo guardado',text:data.mensaje,confirmButtonColor:'#6c1d24'});
        } catch (error) {
            if (button) button.disabled = false;
            Swal.fire({icon:'error',title:'No se guardó',text:error.message,confirmButtonColor:'#6c1d24'});
        }
    }
    async function removeLink(registro, optionIndex, button) {
        const option = registro.opciones[optionIndex];
        const decision = await Swal.fire({icon:'warning',title:'Quitar vinculo',html:`<b>${escapeHtml(registro.rpu)}</b><br>${escapeHtml(option.cct)} - ${escapeHtml(option.nombre_escuela)}`,showCancelButton:true,confirmButtonText:'Quitar',cancelButtonText:'Cancelar',confirmButtonColor:'#6c1d24',cancelButtonColor:'#212529'});
        if (!decision.isConfirmed) return;
        button.disabled = true;
        const body = new URLSearchParams({accion:'eliminar_vinculo',csrf:token,cct:option.cct,rpu:registro.rpu});
        try {
            const response = await fetch(controller,{method:'POST',headers:{'X-CSRF-Token':token},body});
            const data = parseServerJson(await response.text());
            if (!response.ok || !data.ok) throw new Error(data.error || 'No fue posible quitar el vinculo.');
            option.vinculado = false;
            registro.vinculos_confirmados = (registro.vinculos_confirmados || []).filter(vinculo => vinculo.cct !== option.cct);
            registro.vinculo_confirmado = registro.vinculos_confirmados.length > 0;
            renderResults({resultados:window.currentResults,resumen:window.currentSummary});
            Swal.fire({icon:'success',title:'Vinculo eliminado',text:data.mensaje,confirmButtonColor:'#6c1d24'});
        } catch (error) {
            button.disabled = false;
            Swal.fire({icon:'error',title:'No se elimino',text:error.message,confirmButtonColor:'#6c1d24'});
        }
    }
</script>
</body>
</html>
