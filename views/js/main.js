const csrf = document.querySelector('meta[name="csrf-token"]').content;
const controllerUrl = document.querySelector('meta[name="controller-url"]').content;
const form = document.getElementById('upload-form');
const loader = document.getElementById('loader');
const message = document.getElementById('message');

const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
}[character]));

const notify = (text, type = 'success') => {
    message.textContent = text;
    message.className = `message ${type}`;
    message.hidden = false;
    window.setTimeout(() => {
        message.hidden = true;
    }, 4000);
};

document.querySelectorAll('.drop-zone').forEach((zone) => {
    const input = document.getElementById(zone.dataset.input);
    const update = () => {
        zone.classList.toggle('has-file', input.files.length > 0);
        document.querySelector(`[data-file-name="${input.id}"]`).textContent = input.files[0]?.name || 'Seleccionar Excel';
    };
    input.addEventListener('change', update);
    ['dragenter', 'dragover'].forEach((eventName) => {
        zone.addEventListener(eventName, (event) => {
            event.preventDefault();
            zone.classList.add('dragging');
        });
    });
    ['dragleave', 'drop'].forEach((eventName) => {
        zone.addEventListener(eventName, (event) => {
            event.preventDefault();
            zone.classList.remove('dragging');
        });
    });
    zone.addEventListener('drop', (event) => {
        const transfer = new DataTransfer();
        Array.from(event.dataTransfer.files).forEach((file) => transfer.items.add(file));
        input.files = transfer.files;
        update();
    });
});

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const button = form.querySelector('.btn-seg');
    button.disabled = true;
    loader.hidden = false;
    try {
        const response = await fetch(controllerUrl, {
            method: 'POST',
            body: new FormData(form),
            headers: { 'X-CSRF-Token': csrf }
        });
        const data = await response.json();
        if (!response.ok || !data.ok) {
            throw new Error(data.error || 'No fue posible procesar los archivos.');
        }
        renderMatches(data);
        notify('Análisis de vinculación completado.');
    } catch (error) {
        notify(error.message, 'error');
    } finally {
        button.disabled = false;
        loader.hidden = true;
    }
});

function renderMatches(data) {
    const tbody = document.getElementById('matches');
    tbody.innerHTML = data.coincidencias.map((pair, index) => `
        <tr>
            <td><strong>${escapeHtml(pair.rpu)}</strong><small>${escapeHtml(pair.nombre_recibo_cfe)}</small></td>
            <td><strong>${escapeHtml(pair.cct)}</strong><small>${escapeHtml(pair.nombre_escuela)}</small></td>
            <td><strong>${escapeHtml(pair.localidad)}</strong><small>${escapeHtml(pair.municipio)}</small></td>
            <td><span class="score">${Number(pair.similitud).toFixed(1)}%</span></td>
            <td><button class="confirm-button" type="button" data-index="${index}">Confirmar Vínculo</button></td>
        </tr>
    `).join('');
    tbody.querySelectorAll('.confirm-button').forEach((button) => {
        button.addEventListener('click', () => confirmLink(data.coincidencias[Number(button.dataset.index)], button));
    });
    const summary = data.resumen;
    document.getElementById('summary').innerHTML = `<strong>${summary.coincidencias}</strong> coincidencias · ${summary.registros_seg} SEG · ${summary.registros_cfe} CFE`;
    const results = document.getElementById('results');
    results.hidden = false;
    results.scrollIntoView({ behavior: 'smooth' });
}

async function confirmLink(pair, button) {
    button.disabled = true;
    const body = new URLSearchParams({
        accion: 'confirmar',
        csrf,
        cct: pair.cct,
        rpu: pair.rpu,
        nombre_recibo_cfe: pair.nombre_recibo_cfe || ''
    });
    try {
        const response = await fetch(controllerUrl, {
            method: 'POST',
            body,
            headers: { 'X-CSRF-Token': csrf }
        });
        const data = await response.json();
        if (!response.ok || !data.ok) {
            throw new Error(data.error || 'No fue posible confirmar el vínculo.');
        }
        button.textContent = 'Vínculo Confirmado';
        button.classList.add('confirmed');
        notify(data.mensaje);
    } catch (error) {
        button.disabled = false;
        notify(error.message, 'error');
    }
}
