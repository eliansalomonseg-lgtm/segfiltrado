<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/services/auth.php';
segRequireLogin('../login.php');

require_once dirname(__DIR__) . '/services/conexion.php';

$conexion = Conexion::conectar();
$segBasePath = '';

function coordenadaGuerrero(mixed $valor): ?float
{
    $texto = trim(str_replace(',', '.', (string) $valor));
    return is_numeric($texto) ? (float) $texto : null;
}

$ultimoReporte = $conexion->query('SELECT id, anio, mes FROM cfe_reportes ORDER BY anio DESC, mes DESC, id DESC LIMIT 1')->fetch();
$puntos = [];
$consulta = $conexion->prepare(
    'SELECT er.RPU, er.CCT, e.NOMBRECT, e.DOMICILIO, e.NOMBREMUN, e.NOMBRELOC, e.NIVEL, e.SUBNIVEL, e.LATITUD, e.LONGITUD,
            consumo.severidad, consumo.alertas, consumo.total, consumo.consumo, consumo.tarifa_cfe
     FROM escuelas_rpu er
     INNER JOIN escuelas e ON e.CCT = er.CCT
     LEFT JOIN (
        SELECT RPU, MAX(severidad) AS severidad, GROUP_CONCAT(DISTINCT NULLIF(alertas, \'\') SEPARATOR \' | \') AS alertas,
               MAX(total) AS total, MAX(consumo) AS consumo, MAX(tarifa_cfe) AS tarifa_cfe
        FROM cfe_consumos
        WHERE reporte_id = ?
        GROUP BY RPU
     ) consumo ON consumo.RPU = er.RPU
     WHERE e.LATITUD IS NOT NULL AND e.LATITUD <> \'\' AND e.LONGITUD IS NOT NULL AND e.LONGITUD <> \'\'
     ORDER BY e.NOMBREMUN, e.NOMBRELOC, e.NOMBRECT'
);
$consulta->execute([(int) ($ultimoReporte['id'] ?? 0)]);

foreach ($consulta->fetchAll(PDO::FETCH_ASSOC) as $registro) {
    $latitud = coordenadaGuerrero($registro['LATITUD']);
    $longitud = coordenadaGuerrero($registro['LONGITUD']);
    if ($latitud === null || $longitud === null || $latitud < 16 || $latitud > 19.1 || $longitud < -102.8 || $longitud > -97.4) {
        continue;
    }
    $puntos[] = [
        'rpu' => (string) $registro['RPU'],
        'cct' => (string) $registro['CCT'],
        'nombre' => (string) $registro['NOMBRECT'],
        'domicilio' => (string) $registro['DOMICILIO'],
        'municipio' => (string) $registro['NOMBREMUN'],
        'localidad' => (string) $registro['NOMBRELOC'],
        'nivel' => (string) $registro['NIVEL'],
        'subnivel' => (string) $registro['SUBNIVEL'],
        'latitud' => $latitud,
        'longitud' => $longitud,
        'alerta' => (int) ($registro['severidad'] ?? 0) >= 3 || trim((string) ($registro['alertas'] ?? '')) !== '',
        'alertas' => (string) ($registro['alertas'] ?? ''),
        'total' => (float) ($registro['total'] ?? 0),
        'consumo' => (float) ($registro['consumo'] ?? 0),
        'tarifa' => (string) ($registro['tarifa_cfe'] ?? '')
    ];
}

$meses = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];
$etiquetaReporte = $ultimoReporte ? ($meses[(int) $ultimoReporte['mes']] ?? 'Mes') . ' ' . $ultimoReporte['anio'] : 'Sin reporte cargado';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RPUs con escuelas | SEG Guerrero</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">
    <link href="css/seg-executive.css" rel="stylesheet">
</head>
<body>
<?php include_once __DIR__ . '/fragments/navbar.php'; ?>
<?php include_once __DIR__ . '/fragments/sidebar.php'; ?>
<main class="content map-directory-view">
    <section class="heading">
        <div>
            <span class="eyebrow">COBERTURA TERRITORIAL</span>
            <h1>Mapa de servicios escolares</h1>
            <p>Ubica escuelas con RPU confirmado, consulta su último consumo y detecta rápidamente los servicios con alerta.</p>
        </div>
        <span class="alert-gold"><i class="bi bi-calendar3 me-2"></i>Último reporte: <?= htmlspecialchars($etiquetaReporte, ENT_QUOTES, 'UTF-8') ?></span>
    </section>

    <section class="map-rpu-overview" aria-label="Resumen del mapa">
        <article><span class="map-rpu-overview-icon"><i class="bi bi-buildings"></i></span><div><strong id="map-total-links">0</strong><span>vínculos ubicados</span></div></article>
        <article><span class="map-rpu-overview-icon is-gold"><i class="bi bi-geo-alt"></i></span><div><strong id="map-total-municipios">0</strong><span>municipios con cobertura</span></div></article>
        <article><span class="map-rpu-overview-icon is-alert"><i class="bi bi-eye"></i></span><div><strong id="map-total-alerts">0</strong><span>alertas en último reporte</span></div></article>
        <article><span class="map-rpu-overview-icon is-green"><i class="bi bi-check2-circle"></i></span><div><strong id="map-total-normal">0</strong><span>sin alerta reciente</span></div></article>
    </section>

    <section class="map-rpu-layout">
        <aside class="results-card map-rpu-controls">
            <div class="results-head">
                <div>
                    <span class="eyebrow">FILTROS</span>
                    <h2>Escuelas ubicadas</h2>
                    <p class="section-note">Selecciona un resultado para localizarlo en el mapa.</p>
                </div>
            </div>
            <label class="search-field map-rpu-search">
                <i class="bi bi-search"></i>
                <input id="map-rpu-search" type="search" placeholder="RPU, CCT, escuela o localidad">
            </label>
            <label class="map-rpu-select-label" for="map-municipio">Municipio</label>
            <select id="map-municipio" class="form-select map-rpu-select">
                <option value="">Todos los municipios</option>
            </select>
            <span class="map-rpu-select-label map-rpu-level-label">Nivel educativo</span>
            <div class="map-level-filter" role="group" aria-label="Filtrar por nivel educativo">
                <button class="active" type="button" data-map-level=""><i class="bi bi-grid-3x3-gap"></i><span>Todo</span></button>
                <button type="button" data-map-level="PREESCOLAR"><img src="../imgs/prescolar.png" alt=""><span>Preescolar</span></button>
                <button type="button" data-map-level="PRIMARIA"><img src="../imgs/primaria.png" alt=""><span>Primaria</span></button>
                <button type="button" data-map-level="SECUNDARIA"><img src="../imgs/secundaria.png" alt=""><span>Secundaria</span></button>
            </div>
            <div class="map-rpu-filter-actions">
                <button id="map-alert-filter" class="map-rpu-eye-filter" type="button" aria-pressed="false" title="Mostrar solo escuelas con alerta en el último reporte">
                    <i class="bi bi-eye"></i><span>Solo con alertas</span>
                </button>
                <button id="map-reset-filter" class="icon-action" type="button" title="Limpiar filtros" aria-label="Limpiar filtros"><i class="bi bi-arrow-counterclockwise"></i></button>
            </div>
            <div class="map-rpu-summary"><strong id="map-visible-count">0</strong><span>vínculos visibles</span></div>
            <div id="map-rpu-list" class="map-rpu-list" aria-live="polite"></div>
        </aside>
        <section class="results-card map-rpu-canvas-card">
            <div class="map-rpu-map-head">
                <div class="map-rpu-map-title"><i class="bi bi-buildings"></i><span>Escuelas con RPU confirmado<small id="map-map-caption">Cargando cobertura territorial...</small></span></div>
                <div class="map-rpu-map-tools">
                    <div class="map-view-mode" role="group" aria-label="Tipo de mapa">
                        <button class="active" type="button" data-map-base="plano"><i class="bi bi-map"></i><span>Plano</span></button>
                        <button type="button" data-map-base="satelital"><i class="bi bi-globe-americas"></i><span>Satelital</span></button>
                    </div>
                    <div class="map-rpu-legend">
                        <span><img src="../imgs/prescolar.png" alt="">Preescolar</span>
                        <span><img src="../imgs/primaria.png" alt="">Primaria</span>
                        <span><img src="../imgs/secundaria.png" alt="">Secundaria</span>
                        <span class="alert"><i></i>Alerta</span>
                    </div>
                    <button id="map-recenter" class="map-canvas-action" type="button" title="Mostrar todo Guerrero" aria-label="Mostrar todo Guerrero"><i class="bi bi-arrows-angle-contract"></i></button>
                    <button id="map-locate" class="map-canvas-action" type="button" title="Usar mi ubicación" aria-label="Usar mi ubicación"><i class="bi bi-crosshair"></i></button>
                    <button id="map-fullscreen" class="map-canvas-action" type="button" title="Pantalla completa" aria-label="Pantalla completa"><i class="bi bi-fullscreen"></i></button>
                </div>
            </div>
            <div id="map-rpu-canvas" aria-label="Mapa de escuelas vinculadas a RPUs en Guerrero"></div>
        </section>
    </section>
</main>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script>
const puntosRpu = <?= json_encode($puntos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const mapa = L.map('map-rpu-canvas', { zoomControl: true }).setView([17.55, -99.55], 7);
const capasBase = {
    plano: L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '&copy; OpenStreetMap' }),
    satelital: L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 18, attribution: 'Tiles &copy; Esri' })
};
let capaBaseActiva = capasBase.plano;
capaBaseActiva.addTo(mapa);
const agrupador = L.markerClusterGroup({
    showCoverageOnHover: false,
    maxClusterRadius: 52,
    chunkedLoading: true,
    chunkInterval: 80,
    chunkDelay: 18,
    iconCreateFunction: (grupo) => {
        const total = grupo.getChildCount();
        return L.divIcon({
            className: 'map-rpu-cluster-wrap',
            html: `<span class="map-rpu-cluster"><strong>${total.toLocaleString('es-MX')}</strong><small>escuelas</small></span>`,
            iconSize: [64, 64],
            iconAnchor: [32, 32]
        });
    }
});
mapa.addLayer(agrupador);

const campoBusqueda = document.getElementById('map-rpu-search');
const municipio = document.getElementById('map-municipio');
const botonAlertas = document.getElementById('map-alert-filter');
const lista = document.getElementById('map-rpu-list');
const contador = document.getElementById('map-visible-count');
const resumenMapa = document.getElementById('map-map-caption');
let soloAlertas = false;
let marcadores = new Map();
let resultadoActivo = '';
let temporizadorBusqueda;
let nivelSeleccionado = '';

const textoSeguro = (valor) => String(valor || '').replace(/[&<>"']/g, (caracter) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[caracter]));
const normalizar = (valor) => String(valor || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toUpperCase();
const moneda = (valor) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', maximumFractionDigits: 2 }).format(Number(valor || 0));
const iconosNivel = {
    PREESCOLAR: '../imgs/prescolar.png',
    PRIMARIA: '../imgs/primaria.png',
    SECUNDARIA: '../imgs/secundaria.png'
};

const municipios = [...new Set(puntosRpu.map((punto) => punto.municipio).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'es'));
municipios.forEach((nombre) => municipio.insertAdjacentHTML('beforeend', `<option value="${textoSeguro(nombre)}">${textoSeguro(nombre)}</option>`));
document.getElementById('map-total-links').textContent = puntosRpu.length.toLocaleString('es-MX');
document.getElementById('map-total-municipios').textContent = municipios.length.toLocaleString('es-MX');
document.getElementById('map-total-alerts').textContent = puntosRpu.filter((punto) => punto.alerta).length.toLocaleString('es-MX');
document.getElementById('map-total-normal').textContent = puntosRpu.filter((punto) => !punto.alerta).length.toLocaleString('es-MX');

puntosRpu.forEach((punto) => {
    const marcador = L.marker([punto.latitud, punto.longitud], { icon: iconoPunto(punto), title: `${punto.rpu} - ${punto.nombre}` }).bindPopup(popupPunto(punto));
    marcadores.set(clavePunto(punto), marcador);
});

function categoriaNivel(punto) {
    const texto = normalizar(`${punto.nivel || ''} ${punto.subnivel || ''}`);
    if (texto.includes('PREESCOLAR') || texto.includes('JARDIN') || texto.includes('KINDER')) return 'PREESCOLAR';
    if (texto.includes('PRIMARIA')) return 'PRIMARIA';
    if (texto.includes('SECUNDARIA') || texto.includes('TELESECUNDARIA')) return 'SECUNDARIA';
    return 'OTRO';
}

function iconoPunto(punto) {
    const categoria = categoriaNivel(punto);
    const imagen = iconosNivel[categoria];
    return L.divIcon({
        className: 'map-rpu-marker-wrap',
        html: imagen
            ? `<span class="map-rpu-marker map-rpu-marker-image${punto.alerta ? ' is-alert' : ''}"><img src="${imagen}" alt="${categoria}">${punto.alerta ? '<b><i class="bi bi-exclamation"></i></b>' : ''}</span>`
            : `<span class="map-rpu-marker${punto.alerta ? ' is-alert' : ''}"><i class="bi ${punto.alerta ? 'bi-exclamation-lg' : 'bi-building'}"></i></span>`,
        iconSize: imagen ? [46, 46] : [34, 34],
        iconAnchor: imagen ? [23, 23] : [17, 17],
        popupAnchor: [0, imagen ? -24 : -18]
    });
}

function clavePunto(punto) {
    return `${punto.rpu}-${punto.cct}`;
}

function popupPunto(punto) {
    const estado = punto.alerta ? '<span class="map-popup-alert">Revisar último reporte</span>' : '<span class="map-popup-ok">Sin alerta reciente</span>';
    const alerta = punto.alertas ? `<p class="map-popup-warning"><strong>Alerta:</strong> ${textoSeguro(punto.alertas)}</p>` : '';
    const ubicacion = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(`${punto.latitud},${punto.longitud}`)}`;
    const expediente = `rpus.php?rpu=${encodeURIComponent(punto.rpu)}`;
    return `<div class="map-popup"><div class="map-popup-top"><span class="map-popup-rpu">RPU ${textoSeguro(punto.rpu)}</span>${estado}</div><h3>${textoSeguro(punto.nombre)}</h3><p class="map-popup-school"><strong>${textoSeguro(punto.cct)}</strong><span>${textoSeguro(punto.nivel || 'Nivel no registrado')}</span></p><p class="map-popup-location"><i class="bi bi-geo-alt"></i><span>${textoSeguro(punto.localidad)} · ${textoSeguro(punto.municipio)}<br>${textoSeguro(punto.domicilio || 'Domicilio no registrado')}</span></p><div class="map-popup-metrics"><span><small>Tarifa</small><strong>${textoSeguro(punto.tarifa || 'Sin dato')}</strong></span><span><small>Consumo</small><strong>${Number(punto.consumo || 0).toLocaleString('es-MX')} kWh</strong></span><span><small>Último total</small><strong>${moneda(punto.total)}</strong></span></div>${alerta}<div class="map-popup-actions"><a class="map-rpu-link" href="${expediente}"><i class="bi bi-file-earmark-text"></i>Abrir expediente RPU</a><a class="map-google-link" href="${ubicacion}" target="_blank" rel="noopener noreferrer"><i class="bi bi-box-arrow-up-right"></i>Google Maps</a></div></div>`;
}

function obtenerVisibles() {
    const termino = normalizar(campoBusqueda.value);
    const municipioElegido = normalizar(municipio.value);
    return puntosRpu.filter((punto) => {
        const contenido = normalizar([punto.rpu, punto.cct, punto.nombre, punto.municipio, punto.localidad, punto.domicilio, punto.nivel, punto.subnivel].join(' '));
        return (!termino || contenido.includes(termino)) && (!municipioElegido || normalizar(punto.municipio) === municipioElegido) && (!nivelSeleccionado || categoriaNivel(punto) === nivelSeleccionado) && (!soloAlertas || punto.alerta);
    });
}

function pintarMapa(ajustarVista = false) {
    const visibles = obtenerVisibles();
    agrupador.clearLayers();
    agrupador.addLayers(visibles.map((punto) => marcadores.get(clavePunto(punto))).filter(Boolean));
    contador.textContent = visibles.length.toLocaleString('es-MX');
    resumenMapa.textContent = visibles.length ? `${visibles.length.toLocaleString('es-MX')} escuelas visibles` : 'Sin escuelas con estos filtros';
    const muestra = visibles.slice(0, 160);
    lista.innerHTML = muestra.length ? muestra.map((punto, indice) => {
        const identificador = clavePunto(punto);
        return `<button class="map-rpu-result${punto.alerta ? ' is-alert' : ''}${resultadoActivo === identificador ? ' is-selected' : ''}" type="button" data-marker="${textoSeguro(identificador)}"><span>${textoSeguro(punto.rpu)}</span><strong>${textoSeguro(punto.nombre)}</strong><small>${textoSeguro(punto.localidad)} · ${textoSeguro(punto.municipio)}</small>${punto.alerta ? '<em><i class="bi bi-eye-fill"></i> Alerta reciente</em>' : ''}</button>`;
    }).join('') : '<div class="map-rpu-empty">No hay escuelas que coincidan con estos filtros.</div>';
    muestra.forEach((punto) => {
        const resultado = lista.querySelector(`[data-marker="${CSS.escape(clavePunto(punto))}"]`);
        if (!resultado) return;
        const categoria = categoriaNivel(punto);
        const imagen = iconosNivel[categoria];
        resultado.insertAdjacentHTML('afterbegin', imagen ? `<img src="${imagen}" alt="${categoria}">` : '<i class="bi bi-building"></i>');
        const nivelResultado = document.createElement('small');
        nivelResultado.className = 'map-rpu-result-level';
        nivelResultado.textContent = punto.nivel || 'Nivel no registrado';
        resultado.appendChild(nivelResultado);
    });
    if (visibles.length > muestra.length) lista.insertAdjacentHTML('beforeend', `<div class="map-rpu-empty">Se muestran las primeras ${muestra.length} escuelas. Acota la búsqueda para ver las demás.</div>`);
    if (ajustarVista && visibles.length) mapa.fitBounds(L.latLngBounds(visibles.map((punto) => [punto.latitud, punto.longitud])), { padding: [34, 34], maxZoom: 13 });
}

lista.addEventListener('click', (evento) => {
    const boton = evento.target.closest('[data-marker]');
    if (!boton) return;
    const marcador = marcadores.get(boton.dataset.marker);
    if (!marcador) return;
    resultadoActivo = boton.dataset.marker;
    lista.querySelectorAll('.map-rpu-result').forEach((resultado) => resultado.classList.toggle('is-selected', resultado.dataset.marker === resultadoActivo));
    mapa.setView(marcador.getLatLng(), Math.max(mapa.getZoom(), 15));
    marcador.openPopup();
});

campoBusqueda.addEventListener('input', () => {
    window.clearTimeout(temporizadorBusqueda);
    temporizadorBusqueda = window.setTimeout(() => pintarMapa(false), 300);
});
municipio.addEventListener('change', () => pintarMapa(true));
document.querySelectorAll('[data-map-level]').forEach((boton) => {
    boton.addEventListener('click', () => {
        nivelSeleccionado = boton.dataset.mapLevel;
        document.querySelectorAll('[data-map-level]').forEach((item) => item.classList.toggle('active', item === boton));
        pintarMapa(true);
    });
});
document.querySelectorAll('[data-map-base]').forEach((boton) => {
    boton.addEventListener('click', () => {
        const base = boton.dataset.mapBase;
        if (!capasBase[base] || capaBaseActiva === capasBase[base]) return;
        mapa.removeLayer(capaBaseActiva);
        capaBaseActiva = capasBase[base];
        capaBaseActiva.addTo(mapa);
        document.querySelectorAll('[data-map-base]').forEach((item) => item.classList.toggle('active', item === boton));
    });
});
botonAlertas.addEventListener('click', () => {
    soloAlertas = !soloAlertas;
    botonAlertas.classList.toggle('is-active', soloAlertas);
    botonAlertas.setAttribute('aria-pressed', String(soloAlertas));
    botonAlertas.querySelector('i').className = `bi ${soloAlertas ? 'bi-eye-fill' : 'bi-eye'}`;
    pintarMapa(true);
});
document.getElementById('map-reset-filter').addEventListener('click', () => {
    campoBusqueda.value = '';
    municipio.value = '';
    nivelSeleccionado = '';
    document.querySelectorAll('[data-map-level]').forEach((item) => item.classList.toggle('active', item.dataset.mapLevel === ''));
    soloAlertas = false;
    botonAlertas.classList.remove('is-active');
    botonAlertas.setAttribute('aria-pressed', 'false');
    botonAlertas.querySelector('i').className = 'bi bi-eye';
    pintarMapa(true);
});
document.getElementById('map-recenter').addEventListener('click', () => {
    resultadoActivo = '';
    pintarMapa(true);
});
document.getElementById('map-locate').addEventListener('click', () => {
    if (!navigator.geolocation) {
        resumenMapa.textContent = 'La ubicación no está disponible en este dispositivo';
        return;
    }
    navigator.geolocation.getCurrentPosition((posicion) => {
        const ubicacion = [posicion.coords.latitude, posicion.coords.longitude];
        mapa.setView(ubicacion, 14);
        L.circleMarker(ubicacion, { radius: 9, color: '#fff', weight: 3, fillColor: '#167c5b', fillOpacity: 1 }).addTo(mapa).bindPopup('Tu ubicación aproximada').openPopup();
    }, () => {
        resumenMapa.textContent = 'No se autorizó la ubicación del dispositivo';
    }, { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 });
});
document.getElementById('map-fullscreen').addEventListener('click', () => {
    const contenedor = document.querySelector('.map-rpu-canvas-card');
    if (document.fullscreenElement) {
        document.exitFullscreen();
        return;
    }
    contenedor.requestFullscreen?.();
});
document.addEventListener('fullscreenchange', () => setTimeout(() => mapa.invalidateSize(), 100));

pintarMapa(true);
</script>
</body>
</html>
