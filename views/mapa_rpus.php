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

function normalizarTextoRegion(string $texto): string
{
    $t = mb_strtoupper(trim($texto), 'UTF-8');
    $t = str_replace(
        ['Á', 'É', 'Í', 'Ó', 'Ú', 'Ü', 'Ñ'],
        ['A', 'E', 'I', 'O', 'U', 'U', 'N'],
        $t
    );
    return preg_replace('/[^A-Z]/', '', $t) ?: '';
}

function determinarRegionGuerrero(?string $municipio, ?string $regionOriginal = null): string
{
    if ($regionOriginal !== null && trim($regionOriginal) !== '') {
        $regNorm = normalizarTextoRegion($regionOriginal);
        if (str_contains($regNorm, 'ACAPULCO')) return 'Acapulco';
        if (str_contains($regNorm, 'COSTAGRANDE')) return 'Costa Grande';
        if (str_contains($regNorm, 'COSTACHICA')) return 'Costa Chica';
        if (str_contains($regNorm, 'CENTRO')) return 'Centro';
        if (str_contains($regNorm, 'MONTAN') || str_contains($regNorm, 'MONTANA')) return 'Montaña';
        if (str_contains($regNorm, 'NORTE')) return 'Norte';
        if (str_contains($regNorm, 'TIERRACALIENTE')) return 'Tierra Caliente';
    }

    $munNorm = normalizarTextoRegion((string) $municipio);

    static $mapaMunicipios = [
        // Acapulco
        'ACAPULCODEJUAREZ' => 'Acapulco',
        'ACAPULCO' => 'Acapulco',

        // Costa Grande
        'ATOYACDEALVAREZ' => 'Costa Grande',
        'ATOYAC' => 'Costa Grande',
        'BENITOJUAREZ' => 'Costa Grande',
        'COAHUAYUTLADEJOSEMARIAIZAZAGA' => 'Costa Grande',
        'COAHUAYUTLA' => 'Costa Grande',
        'COYUCADEBENITEZ' => 'Costa Grande',
        'LAUNIONDEISIDOROMONTESDEOCA' => 'Costa Grande',
        'LAUNION' => 'Costa Grande',
        'PETATLAN' => 'Costa Grande',
        'TECPANDEGALEANA' => 'Costa Grande',
        'ZIHUATANEJODEAZUETA' => 'Costa Grande',
        'JOSEAZUETA' => 'Costa Grande',
        'ZIHUATANEJO' => 'Costa Grande',

        // Costa Chica
        'AYUTLADELOSLIBRES' => 'Costa Chica',
        'AYUTLA' => 'Costa Chica',
        'AZOYU' => 'Costa Chica',
        'COPALA' => 'Costa Chica',
        'CUAJINICUILAPA' => 'Costa Chica',
        'CUAUTEPEC' => 'Costa Chica',
        'FLORENCIOVILLARREAL' => 'Costa Chica',
        'IGUALAPA' => 'Costa Chica',
        'JUCHITAN' => 'Costa Chica',
        'MARQUELIA' => 'Costa Chica',
        'OMETEPEC' => 'Costa Chica',
        'SANLUISACATLAN' => 'Costa Chica',
        'SANMARCOS' => 'Costa Chica',
        'TECOANAPA' => 'Costa Chica',
        'TLACOACHISTLAHUACA' => 'Costa Chica',
        'XOCHISTLAHUACA' => 'Costa Chica',
        'LASVIGAS' => 'Costa Chica',
        'SANNICOLAS' => 'Costa Chica',
        'NUUSAVI' => 'Costa Chica',

        // Centro
        'AHUACUOTZINGO' => 'Centro',
        'CHILAPADEALVAREZ' => 'Centro',
        'CHILAPA' => 'Centro',
        'CHILPANCINGODELOSBRAVO' => 'Centro',
        'CHILPANCINGO' => 'Centro',
        'EDUARDONERI' => 'Centro',
        'GENERALHELIODOROCASTILLO' => 'Centro',
        'HELIODOROCASTILLO' => 'Centro',
        'JOSEJOAQUINDEHERRERA' => 'Centro',
        'JUANRESCUDERO' => 'Centro',
        'LEONARDOBRAVO' => 'Centro',
        'MARTIRDECUILAPAN' => 'Centro',
        'MOCHITLAN' => 'Centro',
        'QUECHULTENANGO' => 'Centro',
        'TIXTLADEGUERRERO' => 'Centro',
        'TIXTLA' => 'Centro',
        'ZITLALA' => 'Centro',

        // Montaña
        'ACATEPEC' => 'Montaña',
        'ALCOZAUCADEGUERRERO' => 'Montaña',
        'ALCOZAUCA' => 'Montaña',
        'ALPOYECA' => 'Montaña',
        'ATLAMAJALCINGODELMONTE' => 'Montaña',
        'ATLIXTAC' => 'Montaña',
        'COCHOAPAELGRANDE' => 'Montaña',
        'COPANATOYAC' => 'Montaña',
        'CUALAC' => 'Montaña',
        'HUAMUXTITLAN' => 'Montaña',
        'ILIATENCO' => 'Montaña',
        'MALINALTEPEC' => 'Montaña',
        'METLATONOC' => 'Montaña',
        'OLINALA' => 'Montaña',
        'TLACOAPA' => 'Montaña',
        'TLALIXTAQUILLADEMALDONADO' => 'Montaña',
        'TLALIXTAQUILLA' => 'Montaña',
        'TLAPADECOMONFORT' => 'Montaña',
        'TLAPA' => 'Montaña',
        'XALPATLAHUAC' => 'Montaña',
        'XOCHIHUEHUETLAN' => 'Montaña',
        'ZAPOTITLANTABLAS' => 'Montaña',
        'SANTACRUZDELRINCON' => 'Montaña',

        // Norte
        'APAXTLA' => 'Norte',
        'APAXTLADECASTREJON' => 'Norte',
        'ATENANGODELRIO' => 'Norte',
        'BUENAVISTADECUELLAR' => 'Norte',
        'COCULA' => 'Norte',
        'COPALILLO' => 'Norte',
        'CUETZALADELPROGRESO' => 'Norte',
        'GENERALCANUTOANERI' => 'Norte',
        'CANUTOANERI' => 'Norte',
        'ACAPETLAHUAYA' => 'Norte',
        'HUITZUCODELOSFIGUEROA' => 'Norte',
        'HUITZUCO' => 'Norte',
        'IGUALADELAINDEPENDENCIA' => 'Norte',
        'IGUALA' => 'Norte',
        'IXCATEOPANDECUAUHTEMOC' => 'Norte',
        'IXCATEOPAN' => 'Norte',
        'PEDROASCENCIOALQUISIRAS' => 'Norte',
        'PILCAYA' => 'Norte',
        'TAXCODEALARCON' => 'Norte',
        'TAXCO' => 'Norte',
        'TELOLOAPAN' => 'Norte',
        'TEPECOACUILCODETRUJANO' => 'Norte',
        'TEPECOACUILCO' => 'Norte',
        'TETIPAC' => 'Norte',

        // Tierra Caliente
        'AJUCHITLANDELPROGRESO' => 'Tierra Caliente',
        'AJUCHITLAN' => 'Tierra Caliente',
        'ARCELIA' => 'Tierra Caliente',
        'COYUCADECATALAN' => 'Tierra Caliente',
        'CUTZAMALADEPINZON' => 'Tierra Caliente',
        'CUTZAMALA' => 'Tierra Caliente',
        'PUNGARABATO' => 'Tierra Caliente',
        'SANMIGUELTOTOLAPAN' => 'Tierra Caliente',
        'TLALCHAPA' => 'Tierra Caliente',
        'TLAPEHUALA' => 'Tierra Caliente',
        'ZIRANDARO' => 'Tierra Caliente',
    ];

    if (isset($mapaMunicipios[$munNorm])) {
        return $mapaMunicipios[$munNorm];
    }

    foreach ($mapaMunicipios as $k => $reg) {
        if (str_contains($munNorm, $k) || str_contains($k, $munNorm)) {
            return $reg;
        }
    }

    return 'Centro';
}

$ultimoReporte = $conexion->query('SELECT id, anio, mes FROM cfe_reportes ORDER BY anio DESC, mes DESC, id DESC LIMIT 1')->fetch();
$puntos = [];
$consulta = $conexion->prepare(
    'SELECT er.RPU, er.CCT, e.NOMBRECT, e.DOMICILIO, e.NOMBREMUN, e.NOMBRELOC, e.NIVEL, e.SUBNIVEL, e.REGION, e.LATITUD, e.LONGITUD,
            consumo.tiene_lectura, consumo.severidad, consumo.alertas, consumo.total, consumo.consumo, consumo.tarifa_cfe
     FROM escuelas_rpu er
     INNER JOIN escuelas e ON e.CCT = er.CCT
     LEFT JOIN (
        SELECT RPU, COUNT(*) AS tiene_lectura, MAX(severidad) AS severidad, GROUP_CONCAT(DISTINCT NULLIF(alertas, \'\') SEPARATOR \' | \') AS alertas,
               MAX(total) AS total, MAX(consumo) AS consumo, MAX(tarifa_cfe) AS tarifa_cfe
        FROM cfe_consumos
        WHERE reporte_id = ?
        GROUP BY RPU
     ) consumo ON consumo.RPU = er.RPU
     WHERE e.LATITUD IS NOT NULL AND e.LATITUD <> \'\' AND e.LONGITUD IS NOT NULL AND e.LONGITUD <> \'\'
     ORDER BY e.NOMBREMUN, e.NOMBRELOC, e.NOMBRECT'
);
$consulta->execute([(int) ($ultimoReporte['id'] ?? 0)]);

$resumenPorRegion = [
    'Acapulco' => ['total' => 0, 'alertas' => 0, 'color' => '#0d9488', 'border' => '#0f766e'],
    'Costa Grande' => ['total' => 0, 'alertas' => 0, 'color' => '#16a34a', 'border' => '#15803d'],
    'Costa Chica' => ['total' => 0, 'alertas' => 0, 'color' => '#059669', 'border' => '#047857'],
    'Centro' => ['total' => 0, 'alertas' => 0, 'color' => '#d97706', 'border' => '#b45309'],
    'Montaña' => ['total' => 0, 'alertas' => 0, 'color' => '#ea580c', 'border' => '#c2410c'],
    'Norte' => ['total' => 0, 'alertas' => 0, 'color' => '#7c3aed', 'border' => '#6d28d9'],
    'Tierra Caliente' => ['total' => 0, 'alertas' => 0, 'color' => '#9333ea', 'border' => '#7e22ce']
];

foreach ($consulta->fetchAll(PDO::FETCH_ASSOC) as $registro) {
    $latitud = coordenadaGuerrero($registro['LATITUD']);
    $longitud = coordenadaGuerrero($registro['LONGITUD']);
    if ($latitud === null || $longitud === null || $latitud < 16 || $latitud > 19.1 || $longitud < -102.8 || $longitud > -97.4) {
        continue;
    }
    $region = determinarRegionGuerrero((string) $registro['NOMBREMUN'], $registro['REGION'] ?? null);
    $esAlerta = (int) ($registro['severidad'] ?? 0) >= 3 || trim((string) ($registro['alertas'] ?? '')) !== '';

    if (isset($resumenPorRegion[$region])) {
        $resumenPorRegion[$region]['total']++;
        if ($esAlerta) $resumenPorRegion[$region]['alertas']++;
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
        'region' => $region,
        'latitud' => $latitud,
        'longitud' => $longitud,
        'alerta' => $esAlerta,
        'alertas' => (string) ($registro['alertas'] ?? ''),
        'tiene_lectura' => (int) ($registro['tiene_lectura'] ?? 0) > 0,
        'total' => (float) ($registro['total'] ?? 0),
        'consumo' => (float) ($registro['consumo'] ?? 0),
        'tarifa' => (string) ($registro['tarifa_cfe'] ?? '')
    ];
}

$sinCoordenadas = [];
$consultaSinCoordenadas = $conexion->prepare(
    'SELECT er.RPU, er.CCT, e.NOMBRECT, e.NOMBREMUN, e.NOMBRELOC, e.NIVEL, e.SUBNIVEL, e.REGION, e.LATITUD, e.LONGITUD,
            consumo.tiene_lectura, consumo.severidad, consumo.alertas, consumo.total, consumo.consumo, consumo.tarifa_cfe
     FROM escuelas_rpu er
     INNER JOIN escuelas e ON e.CCT = er.CCT
     LEFT JOIN (
        SELECT RPU, COUNT(*) AS tiene_lectura, MAX(severidad) AS severidad, GROUP_CONCAT(DISTINCT NULLIF(alertas, \'\') SEPARATOR \' | \') AS alertas,
               MAX(total) AS total, MAX(consumo) AS consumo, MAX(tarifa_cfe) AS tarifa_cfe
        FROM cfe_consumos
        WHERE reporte_id = ?
        GROUP BY RPU
     ) consumo ON consumo.RPU = er.RPU
     WHERE e.LATITUD IS NULL OR e.LATITUD = \'\' OR e.LONGITUD IS NULL OR e.LONGITUD = \'\'
        OR CAST(REPLACE(e.LATITUD, \',\', \'.\') AS DECIMAL(10,6)) < 16
        OR CAST(REPLACE(e.LATITUD, \',\', \'.\') AS DECIMAL(10,6)) > 19.1
        OR CAST(REPLACE(e.LONGITUD, \',\', \'.\') AS DECIMAL(10,6)) < -102.8
        OR CAST(REPLACE(e.LONGITUD, \',\', \'.\') AS DECIMAL(10,6)) > -97.4
     ORDER BY e.NOMBREMUN, e.NOMBRELOC, e.NOMBRECT'
);
$consultaSinCoordenadas->execute([(int) ($ultimoReporte['id'] ?? 0)]);
foreach ($consultaSinCoordenadas->fetchAll(PDO::FETCH_ASSOC) as $registro) {
    $sinCoordenadas[] = [
        'rpu' => (string) $registro['RPU'],
        'cct' => (string) $registro['CCT'],
        'nombre' => (string) $registro['NOMBRECT'],
        'municipio' => (string) $registro['NOMBREMUN'],
        'localidad' => (string) $registro['NOMBRELOC'],
        'nivel' => (string) $registro['NIVEL'],
        'subnivel' => (string) $registro['SUBNIVEL'],
        'region' => determinarRegionGuerrero((string) $registro['NOMBREMUN'], $registro['REGION'] ?? null),
        'alerta' => (int) ($registro['severidad'] ?? 0) >= 3 || trim((string) ($registro['alertas'] ?? '')) !== '',
        'tiene_lectura' => (int) ($registro['tiene_lectura'] ?? 0) > 0,
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
    <title>RPUs con escuelas por Regiones de Guerrero | SEG</title>
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
            <span class="eyebrow">COBERTURA TERRITORIAL POR REGIONES</span>
            <h1>Mapa de servicios escolares de Guerrero</h1>
            <p>Ubica escuelas con RPU confirmado divididas por las regiones del estado de Guerrero con sus líneas divisorias, último consumo y alertas detectadas.</p>
        </div>
        <span class="alert-gold"><i class="bi bi-calendar3 me-2"></i>Último reporte: <?= htmlspecialchars($etiquetaReporte, ENT_QUOTES, 'UTF-8') ?></span>
    </section>

    <div class="map-view-tabs" role="tablist" aria-label="Vistas de cobertura">
        <button class="active" type="button" data-map-panel-button="coverage" role="tab"><i class="bi bi-map"></i>Mapa de cobertura</button>
        <button type="button" data-map-panel-button="missing" role="tab"><i class="bi bi-geo-alt"></i>Sin coordenadas <span><?= number_format(count($sinCoordenadas)) ?></span></button>
    </div>

    <div data-map-panel="coverage">
    <section class="map-rpu-overview" aria-label="Resumen del mapa">
        <article><span class="map-rpu-overview-icon"><i class="bi bi-buildings"></i></span><div><strong id="map-total-links"><?= number_format(count($puntos)) ?></strong><span>vínculos ubicados</span></div></article>
        <article><span class="map-rpu-overview-icon is-gold"><i class="bi bi-geo-alt"></i></span><div><strong id="map-total-municipios">0</strong><span>municipios con cobertura</span></div></article>
        <article><span class="map-rpu-overview-icon is-alert"><i class="bi bi-eye"></i></span><div><strong id="map-total-alerts">0</strong><span>alertas en último reporte</span></div></article>
        <article><span class="map-rpu-overview-icon is-green"><i class="bi bi-check2-circle"></i></span><div><strong id="map-total-normal">0</strong><span>sin alerta reciente</span></div></article>
    </section>

    <!-- Barra de Filtro Rápido por Regiones de Guerrero -->
    <div class="map-region-toolbar">
        <span class="map-region-toolbar-title"><i class="bi bi-layers-half text-primary me-1"></i>Regiones:</span>
        <div class="map-region-filter" role="group" aria-label="Filtrar por región de Guerrero">
            <button class="map-region-btn active" type="button" data-map-region="">
                <i class="bi bi-grid-fill"></i>
                <span>Todas</span>
                <span class="map-region-badge"><?= number_format(count($puntos)) ?></span>
            </button>
            <?php foreach ($resumenPorRegion as $nombreReg => $infoReg): ?>
                <button class="map-region-btn" type="button" data-map-region="<?= htmlspecialchars($nombreReg, ENT_QUOTES, 'UTF-8') ?>">
                    <span class="map-region-dot" style="background-color: <?= $infoReg['color'] ?>;"></span>
                    <span><?= htmlspecialchars($nombreReg, ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="map-region-badge"><?= number_format($infoReg['total']) ?></span>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

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
            <label class="map-rpu-select-label" for="map-select-region">Región</label>
            <select id="map-select-region" class="form-select map-rpu-select">
                <option value="">Todas las regiones</option>
                <?php foreach (array_keys($resumenPorRegion) as $nombreReg): ?>
                    <option value="<?= htmlspecialchars($nombreReg, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($nombreReg, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
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
            <span class="map-rpu-select-label">Situación reciente</span>
            <div class="map-status-filter" role="group" aria-label="Filtrar por situación reciente">
                <button class="active" type="button" data-map-state=""><i class="bi bi-circle-fill"></i><span>Todos</span></button>
                <button type="button" data-map-state="ALERTA"><i class="bi bi-exclamation-circle-fill"></i><span>Alerta</span></button>
                <button type="button" data-map-state="SIN_CONSUMO"><i class="bi bi-pause-circle-fill"></i><span>Consumo cero</span></button>
                <button type="button" data-map-state="NORMAL"><i class="bi bi-check-circle-fill"></i><span>Normal</span></button>
            </div>
            <div class="map-rpu-filter-actions">
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
                        <span class="zero"><i></i>Consumo cero</span>
                        <span class="normal"><i></i>Normal</span>
                    </div>
                    <button id="map-recenter" class="map-canvas-action" type="button" title="Mostrar todo Guerrero" aria-label="Mostrar todo Guerrero"><i class="bi bi-arrows-angle-contract"></i></button>
                    <button id="map-locate" class="map-canvas-action" type="button" title="Usar mi ubicación" aria-label="Usar mi ubicación"><i class="bi bi-crosshair"></i></button>
                    <button id="map-fullscreen" class="map-canvas-action" type="button" title="Pantalla completa" aria-label="Pantalla completa"><i class="bi bi-fullscreen"></i></button>
                </div>
            </div>
            <div id="map-rpu-canvas" aria-label="Mapa de escuelas vinculadas a RPUs en Guerrero con división de regiones"></div>
        </section>
    </section>
    </div>

    <section class="results-card map-missing-card" data-map-panel="missing" hidden>
        <div class="results-head">
            <div><span class="eyebrow">CALIDAD DE UBICACIÓN</span><h2>Escuelas vinculadas sin coordenadas</h2><p class="section-note">Estos servicios tienen RPU confirmado, pero aún no pueden aparecer en el mapa por falta de latitud o longitud.</p></div>
            <span class="alert-gold"><i class="bi bi-geo-alt me-1"></i><span id="missing-visible-count">0</span> pendientes</span>
        </div>
        <div class="map-missing-filters">
            <label class="search-field"><i class="bi bi-search"></i><input id="missing-search" type="search" placeholder="RPU, CCT, escuela, localidad o municipio"></label>
            <select id="missing-municipio" class="form-select"><option value="">Todos los municipios</option></select>
        </div>
        <div id="missing-list" class="map-missing-list"></div>
    </section>
</main>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script>
const puntosRpu = <?= json_encode($puntos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const puntosSinCoordenadas = <?= json_encode($sinCoordenadas, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

// Configuración de las 7 regiones de Guerrero
const configRegiones = {
    'Acapulco': { color: '#0d9488', border: '#0f766e', lat: 16.863, lng: -99.882 },
    'Costa Grande': { color: '#16a34a', border: '#15803d', lat: 17.550, lng: -100.950 },
    'Costa Chica': { color: '#059669', border: '#047857', lat: 16.700, lng: -98.900 },
    'Centro': { color: '#d97706', border: '#b45309', lat: 17.550, lng: -99.400 },
    'Montaña': { color: '#ea580c', border: '#c2410c', lat: 17.500, lng: -98.650 },
    'Norte': { color: '#7c3aed', border: '#6d28d9', lat: 18.350, lng: -99.550 },
    'Tierra Caliente': { color: '#9333ea', border: '#7e22ce', lat: 18.250, lng: -100.600 }
};

const mapa = L.map('map-rpu-canvas', { zoomControl: true }).setView([17.55, -99.55], 8);
const capasBase = {
    plano: L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '&copy; OpenStreetMap' }),
    satelital: L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 18, attribution: 'Tiles &copy; Esri' })
};
let capaBaseActiva = capasBase.plano;
capaBaseActiva.addTo(mapa);

// Agrupador original de escuelas con estilo institucional
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

// Capas para las líneas divisorias de las regiones
let capaRegionesGeoJson = null;

const campoBusqueda = document.getElementById('map-rpu-search');
const selectRegion = document.getElementById('map-select-region');
const selectMunicipio = document.getElementById('map-municipio');
const lista = document.getElementById('map-rpu-list');
const contador = document.getElementById('map-visible-count');
const resumenMapa = document.getElementById('map-map-caption');
const busquedaSinCoordenadas = document.getElementById('missing-search');
const municipioSinCoordenadas = document.getElementById('missing-municipio');
const listaSinCoordenadas = document.getElementById('missing-list');
const contadorSinCoordenadas = document.getElementById('missing-visible-count');

let marcadores = new Map();
let resultadoActivo = '';
let temporizadorBusqueda;
let nivelSeleccionado = '';
let estadoSeleccionado = '';
let regionSeleccionada = '';

const textoSeguro = (valor) => String(valor || '').replace(/[&<>"']/g, (caracter) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[caracter]));
const normalizar = (valor) => String(valor || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toUpperCase();
const moneda = (valor) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', maximumFractionDigits: 2 }).format(Number(valor || 0));
const iconosNivel = {
    PREESCOLAR: '../imgs/prescolar.png',
    PRIMARIA: '../imgs/primaria.png',
    SECUNDARIA: '../imgs/secundaria.png'
};

const municipios = [...new Set(puntosRpu.map((punto) => punto.municipio).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'es'));
municipios.forEach((nombre) => selectMunicipio.insertAdjacentHTML('beforeend', `<option value="${textoSeguro(nombre)}">${textoSeguro(nombre)}</option>`));
const municipiosSinCoordenadas = [...new Set(puntosSinCoordenadas.map((punto) => punto.municipio).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'es'));
municipiosSinCoordenadas.forEach((nombre) => municipioSinCoordenadas.insertAdjacentHTML('beforeend', `<option value="${textoSeguro(nombre)}">${textoSeguro(nombre)}</option>`));

document.getElementById('map-total-links').textContent = puntosRpu.length.toLocaleString('es-MX');
document.getElementById('map-total-municipios').textContent = municipios.length.toLocaleString('es-MX');
document.getElementById('map-total-alerts').textContent = puntosRpu.filter((punto) => punto.alerta).length.toLocaleString('es-MX');
document.getElementById('map-total-normal').textContent = puntosRpu.filter((punto) => !punto.alerta).length.toLocaleString('es-MX');

function categoriaNivel(punto) {
    const texto = normalizar(`${punto.nivel || ''} ${punto.subnivel || ''}`);
    if (texto.includes('PREESCOLAR') || texto.includes('JARDIN') || texto.includes('KINDER')) return 'PREESCOLAR';
    if (texto.includes('PRIMARIA')) return 'PRIMARIA';
    if (texto.includes('SECUNDARIA') || texto.includes('TELESECUNDARIA')) return 'SECUNDARIA';
    return 'OTRO';
}

function estadoEnergetico(punto) {
    if (punto.alerta) return 'ALERTA';
    if (punto.tiene_lectura && Number(punto.consumo || 0) <= 0) return 'SIN_CONSUMO';
    return 'NORMAL';
}

// Icono original con imágenes de nivel escolar (Preescolar, Primaria, Secundaria) y anillo de alerta
function iconoPunto(punto) {
    const categoria = categoriaNivel(punto);
    const imagen = iconosNivel[categoria];
    const estado = estadoEnergetico(punto).toLowerCase().replace('_', '-');

    return L.divIcon({
        className: 'map-rpu-marker-wrap',
        html: imagen
            ? `<span class="map-rpu-marker map-rpu-marker-image map-rpu-marker-${estado}"><img src="${imagen}" alt="${categoria}">${punto.alerta ? '<b><i class="bi bi-exclamation"></i></b>' : ''}</span>`
            : `<span class="map-rpu-marker map-rpu-marker-${estado}"><i class="bi ${punto.alerta ? 'bi-exclamation-lg' : 'bi-building'}"></i></span>`,
        iconSize: imagen ? [46, 46] : [34, 34],
        iconAnchor: imagen ? [23, 23] : [17, 17],
        popupAnchor: [0, imagen ? -24 : -18]
    });
}

function clavePunto(punto) {
    return `${punto.rpu}-${punto.cct}`;
}

function popupPunto(punto) {
    const region = punto.region || 'Centro';
    const confReg = configRegiones[region] || { color: '#6a1b29' };
    const estado = punto.alerta ? '<span class="map-popup-alert">Revisar último reporte</span>' : '<span class="map-popup-ok">Sin alerta reciente</span>';
    const alerta = punto.alertas ? `<p class="map-popup-warning"><strong>Alerta:</strong> ${textoSeguro(punto.alertas)}</p>` : '';
    const consumo = punto.tiene_lectura ? `${Number(punto.consumo || 0).toLocaleString('es-MX')} kWh` : 'Sin lectura';
    const total = punto.tiene_lectura ? moneda(punto.total) : 'Sin lectura';
    const ubicacion = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(`${punto.latitud},${punto.longitud}`)}`;
    const expediente = `rpus.php?rpu=${encodeURIComponent(punto.rpu)}`;

    return `<div class="map-popup"><div class="map-popup-top"><span class="map-popup-rpu">RPU ${textoSeguro(punto.rpu)}</span>${estado}</div><span class="badge rounded-pill mb-2 px-2" style="background-color: ${confReg.color}; color:#fff; font-size:10.5px;"><i class="bi bi-geo-alt me-1"></i>Región ${textoSeguro(region)}</span><h3>${textoSeguro(punto.nombre)}</h3><p class="map-popup-school"><strong>${textoSeguro(punto.cct)}</strong><span>${textoSeguro(punto.nivel || 'Nivel no registrado')}</span></p><p class="map-popup-location"><i class="bi bi-geo-alt"></i><span>${textoSeguro(punto.localidad)} · ${textoSeguro(punto.municipio)}<br>${textoSeguro(punto.domicilio || 'Domicilio no registrado')}</span></p><div class="map-popup-metrics"><span><small>Tarifa</small><strong>${textoSeguro(punto.tarifa || 'Sin dato')}</strong></span><span><small>Consumo</small><strong>${consumo}</strong></span><span><small>Último total</small><strong>${total}</strong></span></div>${alerta}<div class="map-popup-actions"><a class="map-rpu-link" href="${expediente}"><i class="bi bi-file-earmark-text"></i>Abrir expediente RPU</a><a class="map-google-link" href="${ubicacion}" target="_blank" rel="noopener noreferrer"><i class="bi bi-box-arrow-up-right"></i>Google Maps</a></div></div>`;
}

function inicializarMarcadores() {
    marcadores.clear();
    puntosRpu.forEach((punto) => {
        const marcador = L.marker([punto.latitud, punto.longitud], {
            icon: iconoPunto(punto),
            title: `${punto.rpu} - ${punto.nombre}`
        }).bindPopup(popupPunto(punto));
        marcadores.set(clavePunto(punto), marcador);
    });
}

function cargarCapaRegiones() {
    fetch('js/guerrero_regiones.json')
        .then(response => {
            if (!response.ok) throw new Error('No se pudo cargar el archivo de regiones');
            return response.json();
        })
        .then(data => {
            if (capaRegionesGeoJson) {
                mapa.removeLayer(capaRegionesGeoJson);
            }

            capaRegionesGeoJson = L.geoJSON(data, {
                style: (feature) => {
                    const regName = feature.properties.region;
                    const conf = configRegiones[regName] || { color: '#6a1b29', border: '#44111a' };
                    const esSeleccionada = regionSeleccionada === regName;
                    return {
                        color: conf.border || conf.color,
                        weight: esSeleccionada ? 3.6 : 2.2,
                        opacity: 0.95,
                        fillColor: conf.color,
                        fillOpacity: esSeleccionada ? 0.20 : 0.07,
                        dashArray: esSeleccionada ? null : '3, 2'
                    };
                },
                onEachFeature: (feature, layer) => {
                    const props = feature.properties;
                    const regName = props.region;
                    const munName = props.municipio;
                    const conf = configRegiones[regName] || { color: '#6a1b29', border: '#44111a' };

                    const escuelasReg = puntosRpu.filter(p => p.region === regName).length;
                    const alertasReg = puntosRpu.filter(p => p.region === regName && p.alerta).length;

                    layer.bindTooltip(`
                        <div class="map-region-tooltip" style="--region-border: ${conf.border || conf.color}; --region-color: ${conf.color};">
                            <h4><i class="bi bi-bounding-box-circles me-1"></i>Región ${textoSeguro(regName)}</h4>
                            <p><strong>Municipio:</strong> ${textoSeguro(munName)}</p>
                            <div class="region-tooltip-stats">
                                <span>Escuelas: <strong>${escuelasReg.toLocaleString('es-MX')}</strong></span>
                                <span>Alertas: <strong>${alertasReg.toLocaleString('es-MX')}</strong></span>
                            </div>
                        </div>
                    `, { sticky: true, className: 'leaflet-tooltip-clean' });

                    layer.on({
                        mouseover: (e) => {
                            const l = e.target;
                            l.setStyle({
                                weight: 3.8,
                                fillOpacity: 0.26,
                                color: conf.border || '#000'
                            });
                            if (!L.Browser.ie && !L.Browser.opera && !L.Browser.edge) {
                                l.bringToFront();
                            }
                        },
                        mouseout: (e) => {
                            if (capaRegionesGeoJson) {
                                capaRegionesGeoJson.resetStyle(e.target);
                            }
                        },
                        click: () => {
                            seleccionarRegion(regName);
                        }
                    });
                }
            });

            capaRegionesGeoJson.addTo(mapa);
            capaRegionesGeoJson.bringToBack();
        })
        .catch(err => {
            console.warn('Información regional:', err);
        });
}

function seleccionarRegion(nombreRegion) {
    regionSeleccionada = nombreRegion;
    selectRegion.value = nombreRegion;
    
    document.querySelectorAll('[data-map-region]').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.mapRegion === nombreRegion);
    });

    actualizarOpcionesMunicipios();
    pintarMapa(true);

    if (capaRegionesGeoJson) {
        capaRegionesGeoJson.eachLayer(layer => {
            if (capaRegionesGeoJson) capaRegionesGeoJson.resetStyle(layer);
        });
    }
}

function actualizarOpcionesMunicipios() {
    const municipioAnterior = selectMunicipio.value;
    selectMunicipio.innerHTML = '<option value="">Todos los municipios</option>';
    
    const municipiosFiltrados = [...new Set(
        puntosRpu
            .filter(p => !regionSeleccionada || p.region === regionSeleccionada)
            .map(p => p.municipio)
            .filter(Boolean)
    )].sort((a, b) => a.localeCompare(b, 'es'));

    municipiosFiltrados.forEach(nombre => {
        selectMunicipio.insertAdjacentHTML('beforeend', `<option value="${textoSeguro(nombre)}">${textoSeguro(nombre)}</option>`);
    });

    if (municipiosFiltrados.includes(municipioAnterior)) {
        selectMunicipio.value = municipioAnterior;
    } else {
        selectMunicipio.value = '';
    }
}

function obtenerVisibles() {
    const termino = normalizar(campoBusqueda.value);
    const municipioElegido = normalizar(selectMunicipio.value);
    const regionElegida = regionSeleccionada;

    return puntosRpu.filter((punto) => {
        const contenido = normalizar([punto.rpu, punto.cct, punto.nombre, punto.municipio, punto.localidad, punto.domicilio, punto.nivel, punto.subnivel, punto.region].join(' '));
        return (!termino || contenido.includes(termino)) &&
               (!regionElegida || punto.region === regionElegida) &&
               (!municipioElegido || normalizar(punto.municipio) === municipioElegido) &&
               (!nivelSeleccionado || categoriaNivel(punto) === nivelSeleccionado) &&
               (!estadoSeleccionado || estadoEnergetico(punto) === estadoSeleccionado);
    });
}

function pintarMapa(ajustarVista = false) {
    const visibles = obtenerVisibles();
    
    agrupador.clearLayers();
    agrupador.addLayers(visibles.map((punto) => marcadores.get(clavePunto(punto))).filter(Boolean));

    contador.textContent = visibles.length.toLocaleString('es-MX');
    resumenMapa.textContent = visibles.length ? `${visibles.length.toLocaleString('es-MX')} escuelas visibles` : 'Sin escuelas con estos filtros';

    const muestra = visibles.slice(0, 160);
    lista.innerHTML = muestra.length ? muestra.map((punto) => {
        const identificador = clavePunto(punto);
        const conf = configRegiones[punto.region] || { color: '#6a1b29' };
        const nivel = punto.nivel || punto.subnivel || 'Nivel no registrado';
        const categoria = categoriaNivel(punto);
        const imagen = iconosNivel[categoria];

        return `
            <article class="map-rpu-result ${punto.alerta ? 'is-alert' : ''} ${resultadoActivo === identificador ? 'is-selected' : ''}" data-marker="${textoSeguro(identificador)}" style="--card-border-color: ${conf.color};">
                <div class="map-rpu-result-header">
                    <span class="map-rpu-badge-rpu">${imagen ? `<img src="${imagen}" alt="" style="width:16px; height:16px; vertical-align:middle; margin-right:4px;">` : ''}RPU ${textoSeguro(punto.rpu)}</span>
                    <span class="map-rpu-badge-region" style="background-color: ${conf.color};">${textoSeguro(punto.region)}</span>
                </div>
                <h4 class="map-rpu-result-title">${textoSeguro(punto.nombre)}</h4>
                <div class="map-rpu-result-meta">
                    <span><i class="bi bi-geo-alt-fill"></i> ${textoSeguro(punto.localidad)} · ${textoSeguro(punto.municipio)}</span>
                    <span class="map-rpu-result-cct">${textoSeguro(punto.cct)} · ${textoSeguro(nivel)}</span>
                </div>
                ${punto.alerta ? '<div class="map-rpu-result-alert"><i class="bi bi-exclamation-triangle-fill"></i> Alerta en último reporte</div>' : ''}
            </article>`;
    }).join('') : '<div class="map-rpu-empty">No hay escuelas que coincidan con estos filtros.</div>';

    if (visibles.length > muestra.length) {
        lista.insertAdjacentHTML('beforeend', `<div class="map-rpu-empty">Se muestran las primeras ${muestra.length} escuelas de ${visibles.length.toLocaleString('es-MX')}. Acota la búsqueda para ver más.</div>`);
    }

    if (ajustarVista && visibles.length) {
        mapa.fitBounds(L.latLngBounds(visibles.map((punto) => [punto.latitud, punto.longitud])), { padding: [34, 34], maxZoom: 13 });
    }
}

lista.addEventListener('click', (evento) => {
    const card = evento.target.closest('[data-marker]');
    if (!card) return;
    const marcador = marcadores.get(card.dataset.marker);
    if (!marcador) return;
    resultadoActivo = card.dataset.marker;
    lista.querySelectorAll('.map-rpu-result').forEach((r) => r.classList.toggle('is-selected', r.dataset.marker === resultadoActivo));
    mapa.setView(marcador.getLatLng(), Math.max(mapa.getZoom(), 15));
    marcador.openPopup();
});

campoBusqueda.addEventListener('input', () => {
    window.clearTimeout(temporizadorBusqueda);
    temporizadorBusqueda = window.setTimeout(() => pintarMapa(false), 300);
});

selectRegion.addEventListener('change', () => {
    seleccionarRegion(selectRegion.value);
});

selectMunicipio.addEventListener('change', () => pintarMapa(true));

document.querySelectorAll('[data-map-region]').forEach((boton) => {
    boton.addEventListener('click', () => {
        seleccionarRegion(boton.dataset.mapRegion);
    });
});

document.querySelectorAll('[data-map-level]').forEach((boton) => {
    boton.addEventListener('click', () => {
        nivelSeleccionado = boton.dataset.mapLevel;
        document.querySelectorAll('[data-map-level]').forEach((item) => item.classList.toggle('active', item === boton));
        pintarMapa(true);
    });
});

document.querySelectorAll('[data-map-state]').forEach((boton) => {
    boton.addEventListener('click', () => {
        estadoSeleccionado = boton.dataset.mapState;
        document.querySelectorAll('[data-map-state]').forEach((item) => item.classList.toggle('active', item === boton));
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
        if (capaRegionesGeoJson) {
            capaRegionesGeoJson.bringToBack();
        }
        document.querySelectorAll('[data-map-base]').forEach((item) => item.classList.toggle('active', item === boton));
    });
});

document.getElementById('map-reset-filter').addEventListener('click', () => {
    campoBusqueda.value = '';
    selectRegion.value = '';
    regionSeleccionada = '';
    actualizarOpcionesMunicipios();
    selectMunicipio.value = '';
    nivelSeleccionado = '';
    document.querySelectorAll('[data-map-level]').forEach((item) => item.classList.toggle('active', item.dataset.mapLevel === ''));
    estadoSeleccionado = '';
    document.querySelectorAll('[data-map-state]').forEach((item) => item.classList.toggle('active', item.dataset.mapState === ''));
    document.querySelectorAll('[data-map-region]').forEach((item) => item.classList.toggle('active', item.dataset.mapRegion === ''));
    pintarMapa(true);
});

document.getElementById('map-recenter').addEventListener('click', () => {
    resultadoActivo = '';
    seleccionarRegion('');
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
window.addEventListener('resize', () => mapa.invalidateSize());

function renderSinCoordenadas() {
    const termino = normalizar(busquedaSinCoordenadas.value);
    const municipioElegido = normalizar(municipioSinCoordenadas.value);
    const visibles = puntosSinCoordenadas.filter((punto) => {
        const contenido = normalizar([punto.rpu, punto.cct, punto.nombre, punto.localidad, punto.municipio, punto.nivel, punto.subnivel, punto.region].join(' '));
        return (!termino || contenido.includes(termino)) && (!municipioElegido || normalizar(punto.municipio) === municipioElegido);
    });
    contadorSinCoordenadas.textContent = visibles.length.toLocaleString('es-MX');
    const muestra = visibles.slice(0, 200);
    listaSinCoordenadas.innerHTML = muestra.length ? muestra.map((punto) => {
        const estado = estadoEnergetico(punto).toLowerCase().replace('_', '-');
        const conf = configRegiones[punto.region] || { color: '#6a1b29' };
        return `<article class="map-missing-item is-${estado}"><span class="map-missing-icon"><i class="bi bi-geo-alt"></i></span><div><strong>${textoSeguro(punto.nombre)}</strong><small>${textoSeguro(punto.rpu)} · ${textoSeguro(punto.cct)}</small><small><span class="badge rounded-pill me-1" style="background-color:${conf.color}; color:#fff; font-size:9px;">${textoSeguro(punto.region)}</span>${textoSeguro(punto.localidad)} · ${textoSeguro(punto.municipio)}</small></div><div class="map-missing-metrics"><span>${textoSeguro(punto.nivel || 'Nivel no registrado')}</span><strong>${moneda(punto.total)}</strong><a href="rpus.php?rpu=${encodeURIComponent(punto.rpu)}">Abrir RPU</a></div></article>`;
    }).join('') : '<div class="map-rpu-empty">No hay escuelas sin coordenadas que coincidan con la búsqueda.</div>';
    if (visibles.length > muestra.length) listaSinCoordenadas.insertAdjacentHTML('beforeend', `<div class="map-rpu-empty">Se muestran las primeras ${muestra.length} escuelas. Acota la búsqueda para ver más.</div>`);
}

busquedaSinCoordenadas.addEventListener('input', () => {
    window.clearTimeout(temporizadorBusqueda);
    temporizadorBusqueda = window.setTimeout(renderSinCoordenadas, 250);
});
municipioSinCoordenadas.addEventListener('change', renderSinCoordenadas);

document.querySelectorAll('[data-map-panel-button]').forEach((boton) => {
    boton.addEventListener('click', () => {
        const panel = boton.dataset.mapPanelButton;
        document.querySelectorAll('[data-map-panel-button]').forEach((item) => item.classList.toggle('active', item === boton));
        document.querySelectorAll('[data-map-panel]').forEach((item) => {
            item.hidden = item.dataset.mapPanel !== panel;
        });
        if (panel === 'coverage') setTimeout(() => mapa.invalidateSize(), 80);
    });
});

// Inicialización
inicializarMarcadores();
cargarCapaRegiones();
pintarMapa(true);
renderSinCoordenadas();
setTimeout(() => mapa.invalidateSize(), 150);
</script>
</body>
</html>
