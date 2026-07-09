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
    <style>
        :root{--guinda:#6c1d24;--dorado:#bfa276;--oscuro:#212529;--fondo:#f5f2ed}*{box-sizing:border-box}body{background:var(--fondo);color:var(--oscuro);font-family:Arial,sans-serif;margin:0}.seg-navbar{align-items:center;background:var(--guinda);box-shadow:0 7px 22px #0002;color:#fff;display:flex;height:72px;justify-content:space-between;left:0;padding:0 28px;position:fixed;right:0;top:0;z-index:10}.seg-brand{color:#fff;display:grid;text-decoration:none}.seg-brand strong{font-size:18px}.seg-brand small{font-size:11px;opacity:.75}.seg-badge{background:var(--dorado);border-radius:20px;color:#302719;font-size:12px;font-weight:700;padding:9px 14px}.seg-sidebar{background:var(--guinda);border-right:2px solid var(--dorado);bottom:0;left:0;padding:30px 14px;position:fixed;top:72px;width:230px;z-index:9}.seg-sidebar-title{color:var(--dorado);display:block;font-size:11px;font-weight:800;letter-spacing:2px;margin:0 14px 22px}.seg-sidebar a{border-radius:8px;color:#fff;display:block;font-size:14px;margin-bottom:8px;padding:14px;text-decoration:none;transition:.2s}.seg-sidebar a:hover,.seg-sidebar a.active{background:var(--dorado);color:#332719;transform:translateX(2px)}.workspace{margin-left:230px;padding:106px 28px 38px}.heading{align-items:end;display:flex;justify-content:space-between;margin-bottom:22px}.eyebrow{color:var(--guinda);font-size:10px;font-weight:800;letter-spacing:2px}.heading h1{font-size:27px;margin:6px 0}.heading p{color:#73777b;font-size:13px;margin:0}.alert-gold{background:#bfa2762b;border:1px solid var(--dorado);border-radius:9px;color:#6a5434;font-size:11px;padding:10px 13px}.upload-card,.results-card{background:#fff;border:1px solid #e5dfd7;border-radius:14px;box-shadow:0 10px 30px #0000000d;padding:24px}.drop-grid{align-items:center;display:grid;grid-template-columns:1fr 38px 1fr}.drop-zone{align-items:center;background:#fcfbf9;border:2px dashed var(--dorado);border-radius:13px;cursor:pointer;display:flex;flex-direction:column;justify-content:center;min-height:210px;padding:26px;text-align:center;transition:.2s}.drop-zone:hover,.drop-zone.dragging,.drop-zone.ready{background:#bfa27618;box-shadow:inset 0 0 0 1px var(--dorado);transform:translateY(-2px)}.drop-zone input{display:none}.file-icon{align-items:center;background:var(--guinda);border-radius:11px;color:#fff;display:flex;font-size:12px;font-weight:800;height:52px;justify-content:center;margin-bottom:14px;width:52px}.file-icon.seg{background:var(--oscuro);color:var(--dorado)}.drop-zone strong{font-size:15px}.drop-zone small{color:#85898d;font-size:11px;margin-top:7px}.file-name{background:#eeeae4;border-radius:20px;color:#65696d;font-size:10px;font-style:normal;font-weight:700;margin-top:13px;max-width:90%;overflow:hidden;padding:6px 10px;text-overflow:ellipsis;white-space:nowrap}.cross{align-items:center;background:var(--guinda);border:5px solid #fff;border-radius:50%;color:var(--dorado);display:flex;font-size:18px;height:38px;justify-content:center;position:relative;width:38px;z-index:2}.btn-seg{background:var(--guinda);border:0;border-radius:8px;box-shadow:0 7px 15px #6c1d2433;color:#fff;cursor:pointer;display:block;font-size:13px;font-weight:700;margin:20px auto 0;padding:13px 22px}.btn-seg:disabled{cursor:wait;opacity:.6}.progress-box{margin:20px auto 0;max-width:560px}.progress-track{background:#e9e5df;border-radius:20px;height:9px;overflow:hidden}.progress-bar{background:linear-gradient(90deg,var(--guinda),var(--dorado));height:100%;transition:width .25s;width:0}.progress-text{color:#73777b;display:block;font-size:11px;margin-top:8px;text-align:center}.results-card{margin-top:22px}.results-head{align-items:center;display:flex;justify-content:space-between;margin-bottom:16px}.results-head h2{font-size:20px;margin:5px 0 0}.summary{color:#6f7377;font-size:11px}.summary strong{color:var(--guinda);font-size:18px}.table-wrap{overflow:auto}table{border-collapse:collapse;font-size:12px;width:100%}th{background:#f1ede7;color:#686c70;font-size:10px;letter-spacing:.5px;padding:11px;text-align:left;text-transform:uppercase}td{border-bottom:1px solid #ece7e0;padding:12px 11px;vertical-align:top}td strong,td small{display:block}td small{color:#7c8084;margin-top:3px}.options{display:grid;gap:7px;min-width:430px}.option{align-items:center;background:#faf8f5;border:1px solid #e5dfd7;border-radius:8px;display:grid;gap:9px;grid-template-columns:1fr auto auto;padding:9px}.option.done{background:#e7f4eb;border-color:#4a8b60}.option-data small{line-height:1.35}.score{background:#bfa2762c;border-radius:15px;color:#6f5838;font-size:10px;font-weight:800;padding:5px 7px}.confirm{background:var(--guinda);border:0;border-radius:7px;color:#fff;cursor:pointer;font-size:10px;font-weight:700;padding:8px 10px}.confirm.saved{background:#347a51}.empty{color:#8a5c60;font-style:italic}.tag{background:var(--oscuro);border-radius:12px;color:#fff;display:inline-block;font-size:9px;margin-top:6px;padding:4px 7px}@media(max-width:850px){.seg-sidebar{display:none}.workspace{margin-left:0;padding:96px 14px 30px}.seg-badge{display:none}.drop-grid{gap:10px;grid-template-columns:1fr}.cross{margin:-19px auto}.heading{align-items:start;flex-direction:column;gap:12px}.options{min-width:360px}}
        body{background:radial-gradient(circle at 85% 10%,#bfa27622,transparent 28%),var(--fondo)}
        .workspace{max-width:1500px}
        .heading h1{font-size:29px}
        .upload-card,.results-card{border-radius:16px;box-shadow:0 14px 38px #00000010;padding:26px}
        .workflow{display:flex;gap:8px;margin-bottom:16px}
        .workflow span{background:#fff;border:1px solid #e5dfd7;border-radius:999px;color:#73777b;font-size:10px;font-weight:700;padding:7px 11px}
        .workflow span:first-child{background:var(--guinda);border-color:var(--guinda);color:#fff}
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
            <p>Cruce de RPUs CFE con las escuelas oficiales de Guerrero.</p>
        </div>
        <span class="alert-gold">STATUS activo y nivel educativo tienen prioridad</span>
    </section>
    <div class="workflow" aria-label="Flujo de consolidación">
        <span>1. Cargar archivos</span>
        <span>2. Revisar sugerencias</span>
        <span>3. Confirmar vínculos</span>
    </div>
    <form id="cross-form" class="upload-card" enctype="multipart/form-data">
        <input type="hidden" name="accion" value="procesar_archivos">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['seg_csrf'], ENT_QUOTES, 'UTF-8') ?>">
        <div class="drop-grid">
            <label class="drop-zone" data-input="archivo_seg">
                <input id="archivo_seg" name="archivo_seg" type="file" accept=".xlsx,.xls" required>
                <span class="file-icon seg">SEG</span>
                <strong>Excel Catálogo SEG</strong>
                <small>CCT, plantel, localidad, status y subnivel</small>
                <em class="file-name">Seleccionar archivo Excel</em>
            </label>
            <span class="cross">×</span>
            <label class="drop-zone" data-input="archivo_cfe">
                <input id="archivo_cfe" name="archivo_cfe" type="file" accept=".xlsx,.xls" required>
                <span class="file-icon">CFE</span>
                <strong>Excel Reporte CFE</strong>
                <small>RPU, nombre, dirección, población y tarifa</small>
                <em class="file-name">Seleccionar archivo Excel</em>
            </label>
        </div>
        <button class="btn-seg" type="submit">Ejecutar Cruce Predictivo</button>
        <div id="progress" class="progress-box" hidden>
            <div class="progress-track"><div id="progress-bar" class="progress-bar"></div></div>
            <span id="progress-text" class="progress-text">Preparando archivos...</span>
        </div>
    </form>
    <section id="results" class="results-card" hidden>
        <div class="results-head">
            <div><span class="eyebrow">RESULTADOS</span><h2>RPUs únicos y escuelas sugeridas</h2></div>
            <div id="summary" class="summary"></div>
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
    const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[character]));
    document.querySelectorAll('.drop-zone').forEach(zone => {
        const input = document.getElementById(zone.dataset.input);
        const update = () => {
            zone.classList.toggle('ready', input.files.length > 0);
            zone.querySelector('.file-name').textContent = input.files[0]?.name || 'Seleccionar archivo Excel';
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
        text.textContent = 'Enviando catálogo SEG y reporte CFE...';
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
            text.textContent = 'Python limpia, agrupa y calcula las coincidencias...';
        };
        request.onload = () => {
            button.disabled = false;
            try {
                const data = JSON.parse(request.responseText);
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
    function renderResults(data) {
        const tbody = document.getElementById('matches');
        tbody.innerHTML = data.resultados.map((registro, row) => {
            const options = registro.opciones.length ? registro.opciones.map((option, index) => `
                <div class="option">
                    <div class="option-data"><strong>${escapeHtml(option.cct)} · ${escapeHtml(option.nombre_escuela)}</strong><small>${escapeHtml(option.municipio)} · ${escapeHtml(option.localidad)} · ${escapeHtml(option.subnivel)} · STATUS ${escapeHtml(option.status)}</small></div>
                    <span class="score">${Number(option.similitud).toFixed(1)}%</span>
                    <button class="confirm" type="button" data-row="${row}" data-option="${index}">Confirmar Vínculo</button>
                </div>
            `).join('') : '<span class="empty">Sin escuelas coincidentes en esta localidad</span>';
            return `<tr><td><strong>${escapeHtml(registro.rpu)}</strong><span class="tag">${escapeHtml(registro.tarifa_cfe || 'Sin tarifa')}</span></td><td><strong>${escapeHtml(registro.nombre_cfe)}</strong><small>${escapeHtml(registro.direccion_cfe)}<br>${escapeHtml(registro.poblacion_cfe)}</small></td><td><div class="options">${options}</div></td></tr>`;
        }).join('');
        tbody.querySelectorAll('.confirm').forEach(button => button.addEventListener('click', () => {
            confirmLink(data.resultados[Number(button.dataset.row)], Number(button.dataset.option), button);
        }));
        const summary = data.resumen;
        document.getElementById('summary').innerHTML = `<strong>${summary.rpu_unicos}</strong> RPUs únicos · ${summary.rpu_con_sugerencias} con sugerencias`;
        const results = document.getElementById('results');
        results.hidden = false;
        results.scrollIntoView({behavior:'smooth'});
    }
    async function confirmLink(registro, optionIndex, button) {
        const option = registro.opciones[optionIndex];
        const decision = await Swal.fire({icon:'question',title:'Confirmar vínculo',html:`<b>${escapeHtml(registro.rpu)}</b><br>${escapeHtml(option.cct)} · ${escapeHtml(option.nombre_escuela)}`,showCancelButton:true,confirmButtonText:'Confirmar Vínculo',cancelButtonText:'Cancelar',confirmButtonColor:'#6c1d24',cancelButtonColor:'#212529'});
        if (!decision.isConfirmed) return;
        button.disabled = true;
        const body = new URLSearchParams({accion:'confirmar_vinculo',csrf:token,cct:option.cct,rpu:registro.rpu,nombre_recibo_cfe:registro.nombre_cfe || '',poblacion_cfe:registro.poblacion_cfe || '',tarifa_cfe:registro.tarifa_cfe || ''});
        try {
            const response = await fetch(controller,{method:'POST',headers:{'X-CSRF-Token':token},body});
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.error || 'No fue posible guardar el vínculo.');
            button.textContent = 'Vínculo confirmado';
            button.classList.add('saved');
            button.closest('.option').classList.add('done');
            Swal.fire({icon:'success',title:'Vínculo guardado',text:data.mensaje,confirmButtonColor:'#6c1d24'});
        } catch (error) {
            button.disabled = false;
            Swal.fire({icon:'error',title:'No se guardó',text:error.message,confirmButtonColor:'#6c1d24'});
        }
    }
</script>
</body>
</html>
