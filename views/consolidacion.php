<?php

declare(strict_types=1);

session_start();

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
    <title>Consolidación Bilateral | SEG Guerrero</title>
    <style>
        :root{--guinda:#6c1d24;--dorado:#bfa276;--oscuro:#212529;--fondo:#f5f2ed}*{box-sizing:border-box}body{background:var(--fondo);color:var(--oscuro);font-family:Arial,sans-serif;margin:0}.seg-navbar{align-items:center;background:var(--guinda);box-shadow:0 7px 22px #00000024;color:#fff;display:flex;height:72px;justify-content:space-between;left:0;padding:0 28px;position:fixed;right:0;top:0;z-index:10}.seg-brand{color:#fff;display:grid;text-decoration:none}.seg-brand strong{font-size:18px}.seg-brand small{font-size:11px;opacity:.72}.seg-badge{background:var(--dorado);border-radius:20px;color:#302719;font-size:12px;font-weight:700;padding:9px 14px}.seg-sidebar{background:var(--guinda);border-right:2px solid var(--dorado);bottom:0;left:0;padding:30px 14px;position:fixed;top:72px;width:230px;z-index:9}.seg-sidebar-title{color:var(--dorado);display:block;font-size:11px;font-weight:800;letter-spacing:2px;margin:0 14px 22px}.seg-sidebar a{border-radius:8px;color:#fff;display:block;font-size:14px;margin-bottom:8px;padding:14px;text-decoration:none;transition:.2s}.seg-sidebar a:hover,.seg-sidebar a.active{background:var(--dorado);color:#332719;transform:translateX(2px)}.workspace{margin-left:230px;padding:106px 28px 38px}.heading{align-items:end;display:flex;justify-content:space-between;margin-bottom:22px}.eyebrow{color:var(--guinda);font-size:10px;font-weight:800;letter-spacing:2px}.heading h1{font-size:27px;margin:6px 0}.heading p{color:#73777b;font-size:13px;margin:0}.period-badge{background:#fff;border:1px solid #ded8d0;border-radius:20px;color:#676b6f;font-size:11px;padding:9px 13px}.upload-card,.results-card{background:#fff;border:1px solid #e5dfd7;border-radius:14px;box-shadow:0 10px 30px #0000000d;padding:24px}.drop-grid{align-items:center;display:grid;grid-template-columns:1fr 38px 1fr}.drop-zone{align-items:center;background:#fcfbf9;border:2px dashed var(--dorado);border-radius:13px;cursor:pointer;display:flex;flex-direction:column;justify-content:center;min-height:205px;padding:26px;text-align:center;transition:.2s}.drop-zone:hover,.drop-zone.dragging,.drop-zone.ready{background:#bfa27618;box-shadow:inset 0 0 0 1px var(--dorado);transform:translateY(-2px)}.drop-zone input{display:none}.month-icon{align-items:center;background:var(--guinda);border-radius:11px;color:#fff;display:flex;font-size:13px;font-weight:800;height:50px;justify-content:center;margin-bottom:14px;width:50px}.drop-zone strong{font-size:15px}.drop-zone small{color:#85898d;font-size:11px;margin-top:7px}.file-name{background:#eeeae4;border-radius:20px;color:#65696d;font-size:10px;font-style:normal;font-weight:700;margin-top:13px;max-width:90%;overflow:hidden;padding:6px 10px;text-overflow:ellipsis;white-space:nowrap}.plus{align-items:center;background:var(--oscuro);border:5px solid #fff;border-radius:50%;color:var(--dorado);display:flex;font-size:20px;height:38px;justify-content:center;position:relative;width:38px;z-index:2}.btn-seg{background:var(--guinda);border:0;border-radius:8px;box-shadow:0 7px 15px #6c1d2433;color:#fff;cursor:pointer;display:block;font-size:13px;font-weight:700;margin:20px auto 0;padding:13px 22px}.btn-seg:disabled{cursor:wait;opacity:.6}.progress-box{margin:20px auto 0;max-width:550px}.progress-track{background:#e9e5df;border-radius:20px;height:9px;overflow:hidden}.progress-bar{background:linear-gradient(90deg,var(--guinda),var(--dorado));height:100%;transition:width .25s;width:0}.progress-text{color:#73777b;display:block;font-size:11px;margin-top:8px;text-align:center}.results-card{margin-top:22px}.results-head{align-items:center;display:flex;justify-content:space-between;margin-bottom:16px}.results-head h2{font-size:20px;margin:5px 0 0}.summary{color:#6f7377;font-size:11px}.summary strong{color:var(--guinda);font-size:18px}.table-wrap{overflow:auto}table{border-collapse:collapse;font-size:12px;width:100%}th{background:#f1ede7;color:#686c70;font-size:10px;letter-spacing:.5px;padding:11px;text-align:left;text-transform:uppercase}td{border-bottom:1px solid #ece7e0;padding:12px 11px;vertical-align:top}td strong,td small{display:block}td small{color:#7c8084;margin-top:3px}.options{display:grid;gap:7px;min-width:340px}.option{align-items:center;background:#faf8f5;border:1px solid #e5dfd7;border-radius:8px;display:grid;gap:8px;grid-template-columns:1fr auto auto;padding:8px}.option.selected{background:#bfa27620;border-color:var(--dorado)}.option label{cursor:pointer}.option input{accent-color:var(--guinda)}.score{background:#bfa2762c;border-radius:15px;color:#6f5838;font-size:10px;font-weight:800;padding:5px 7px}.confirm{background:var(--guinda);border:0;border-radius:7px;color:#fff;cursor:pointer;font-size:10px;font-weight:700;padding:8px 10px}.confirm.done{background:#347a51}.empty{color:#8a5c60;font-style:italic}.status-months{background:#212529;color:#fff;border-radius:14px;display:inline-block;font-size:9px;margin-top:6px;padding:4px 7px}@media(max-width:850px){.seg-sidebar{display:none}.workspace{margin-left:0;padding:96px 14px 30px}.seg-badge{display:none}.drop-grid{gap:10px;grid-template-columns:1fr}.plus{margin:-19px auto}.heading{align-items:start;flex-direction:column;gap:12px}.options{min-width:280px}}
    </style>
</head>
<body>
<?php include_once __DIR__ . '/fragments/navbar.php'; ?>
<?php include_once __DIR__ . '/fragments/sidebar.php'; ?>
<main class="workspace">
    <section class="heading">
        <div>
            <span class="eyebrow">VINCULACIÓN BILATERAL</span>
            <h1>Consolidación de Datos</h1>
            <p>Detección de RPUs únicos en dos meses continuos y sugerencia de CCT para Guerrero.</p>
        </div>
        <span class="period-badge">Proceso CFE · 2 periodos</span>
    </section>
    <form id="period-form" class="upload-card" enctype="multipart/form-data">
        <input type="hidden" name="accion" value="procesar_periodos">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['seg_csrf'], ENT_QUOTES, 'UTF-8') ?>">
        <div class="drop-grid">
            <label class="drop-zone" data-input="archivo_mes_uno">
                <input id="archivo_mes_uno" name="archivo_mes_uno" type="file" accept=".xlsx,.xls" required>
                <span class="month-icon">M1</span>
                <strong>Excel CFE - Mes 1</strong>
                <small>Primer periodo continuo</small>
                <em class="file-name">Seleccionar archivo Excel</em>
            </label>
            <span class="plus">+</span>
            <label class="drop-zone" data-input="archivo_mes_dos">
                <input id="archivo_mes_dos" name="archivo_mes_dos" type="file" accept=".xlsx,.xls" required>
                <span class="month-icon">M2</span>
                <strong>Excel CFE - Mes 2</strong>
                <small>Segundo periodo continuo</small>
                <em class="file-name">Seleccionar archivo Excel</em>
            </label>
        </div>
        <button class="btn-seg" type="submit">Procesar dos periodos</button>
        <div id="progress" class="progress-box" hidden>
            <div class="progress-track"><div id="progress-bar" class="progress-bar"></div></div>
            <span id="progress-text" class="progress-text">Preparando archivos...</span>
        </div>
    </form>
    <section id="results" class="results-card" hidden>
        <div class="results-head">
            <div><span class="eyebrow">RPUs CONSOLIDADOS</span><h2>Opciones de vinculación</h2></div>
            <div id="summary" class="summary"></div>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>RPU único</th><th>Referencia CFE</th><th>3 opciones CCT sugeridas</th></tr></thead>
                <tbody id="matches"></tbody>
            </table>
        </div>
    </section>
</main>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const form = document.getElementById('period-form');
    const token = document.querySelector('meta[name="csrf-token"]').content;
    const controller = '../controllers/escuelaController.php';
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
        const button = form.querySelector('button');
        const progress = document.getElementById('progress');
        const bar = document.getElementById('progress-bar');
        const text = document.getElementById('progress-text');
        button.disabled = true;
        progress.hidden = false;
        bar.style.width = '8%';
        text.textContent = 'Enviando ambos periodos CFE...';
        const request = new XMLHttpRequest();
        request.open('POST', controller);
        request.setRequestHeader('X-CSRF-Token', token);
        request.upload.onprogress = upload => {
            if (upload.lengthComputable) {
                const percentage = Math.round((upload.loaded / upload.total) * 55);
                bar.style.width = `${Math.max(8, percentage)}%`;
                text.textContent = `Cargando archivos: ${Math.round((upload.loaded / upload.total) * 100)}%`;
            }
        };
        request.upload.onload = () => {
            bar.style.width = '72%';
            text.textContent = 'Python agrupa RPUs y calcula coincidencias...';
        };
        request.onload = () => {
            button.disabled = false;
            try {
                const data = JSON.parse(request.responseText);
                if (request.status < 200 || request.status >= 300 || !data.ok) {
                    throw new Error(data.error || 'No fue posible procesar los periodos.');
                }
                bar.style.width = '100%';
                text.textContent = 'Consolidación terminada';
                renderResults(data);
                Swal.fire({icon:'success',title:'Periodos procesados',text:`Se detectaron ${data.resumen.rpu_unicos} RPUs únicos.`,confirmButtonColor:'#6c1d24'});
            } catch (error) {
                Swal.fire({icon:'error',title:'Error de procesamiento',text:error.message,confirmButtonColor:'#6c1d24'});
            }
        };
        request.onerror = () => {
            button.disabled = false;
            Swal.fire({icon:'error',title:'Error de conexión',text:'No fue posible comunicarse con el controlador.',confirmButtonColor:'#6c1d24'});
        };
        request.send(new FormData(form));
    });
    function renderResults(data) {
        const tbody = document.getElementById('matches');
        tbody.innerHTML = data.resultados.map((registro, row) => {
            const options = registro.opciones.length
                ? registro.opciones.map((option, index) => `
                    <div class="option">
                        <input id="option-${row}-${index}" type="radio" name="rpu-${row}" value="${index}">
                        <label for="option-${row}-${index}"><strong>${escapeHtml(option.cct)}</strong><small>${escapeHtml(option.nombre_escuela)} · ${escapeHtml(option.municipio)}</small></label>
                        <span class="score">${Number(option.similitud).toFixed(1)}%</span>
                        <button class="confirm" type="button" data-row="${row}" data-option="${index}">Confirmar Vínculo</button>
                    </div>
                `).join('')
                : '<span class="empty">Sin CCT coincidente en la localidad</span>';
            return `<tr><td><strong>${escapeHtml(registro.rpu)}</strong><span class="status-months">${registro.periodos_detectados} periodo(s)</span></td><td><strong>${escapeHtml(registro.nombre_cfe)}</strong><small>${escapeHtml(registro.poblacion_cfe)}</small></td><td><div class="options">${options}</div></td></tr>`;
        }).join('');
        tbody.querySelectorAll('.confirm').forEach(button => {
            button.addEventListener('click', () => confirmLink(data.resultados[Number(button.dataset.row)], Number(button.dataset.option), button));
        });
        const summary = data.resumen;
        document.getElementById('summary').innerHTML = `<strong>${summary.rpu_unicos}</strong> RPUs únicos · ${summary.registros_precarga} registros`;
        const results = document.getElementById('results');
        results.hidden = false;
        results.scrollIntoView({behavior:'smooth'});
    }
    async function confirmLink(registro, optionIndex, button) {
        const option = registro.opciones[optionIndex];
        const decision = await Swal.fire({icon:'question',title:'Confirmar vínculo',html:`<b>${escapeHtml(registro.rpu)}</b><br>${escapeHtml(option.cct)} · ${escapeHtml(option.nombre_escuela)}`,showCancelButton:true,confirmButtonText:'Confirmar Vínculo',cancelButtonText:'Cancelar',confirmButtonColor:'#6c1d24',cancelButtonColor:'#212529'});
        if (!decision.isConfirmed) return;
        button.disabled = true;
        const body = new URLSearchParams({accion:'confirmar_vinculo',csrf:token,rpu:registro.rpu,cct:option.cct,nombre_recibo_cfe:registro.nombre_cfe || ''});
        try {
            const response = await fetch(controller,{method:'POST',headers:{'X-CSRF-Token':token},body});
            const data = await response.json();
            if (!response.ok || !data.ok) throw new Error(data.error || 'No fue posible guardar el vínculo.');
            button.textContent = 'Vínculo confirmado';
            button.classList.add('done');
            button.closest('.option').classList.add('selected');
            Swal.fire({icon:'success',title:'Vínculo guardado',text:data.mensaje,confirmButtonColor:'#6c1d24'});
        } catch (error) {
            button.disabled = false;
            Swal.fire({icon:'error',title:'No se guardó',text:error.message,confirmButtonColor:'#6c1d24'});
        }
    }
</script>
</body>
</html>
