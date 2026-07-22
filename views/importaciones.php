<?php

declare(strict_types=1);

session_start();

require_once dirname(__DIR__) . '/services/conexion.php';

$segBasePath = '';
$conexion = Conexion::conectar();

if (empty($_SESSION['seg_csrf'])) {
    $_SESSION['seg_csrf'] = bin2hex(random_bytes(24));
}

function valorFiltro(string $clave): string
{
    return trim((string) ($_GET[$clave] ?? ''));
}

function contar(PDO $conexion, string $query, array $parametros = []): int
{
    $consulta = $conexion->prepare($query);
    $consulta->execute($parametros);
    return (int) $consulta->fetchColumn();
}

function opciones(PDO $conexion, string $query): array
{
    return $conexion->query($query)->fetchAll(PDO::FETCH_COLUMN);
}

$busqueda = valorFiltro('q');
$tarifa = valorFiltro('tarifa');
$subnivel = valorFiltro('subnivel');
$status = valorFiltro('status');
$porPagina = 25;
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$offset = ($pagina - 1) * $porPagina;

$condiciones = [];
$parametros = [];

if ($busqueda !== '') {
    $valorBusqueda = '%' . $busqueda . '%';
    $columnasBusqueda = ['er.RPU', 'er.CCT', 'er.nombre_recibo_cfe', 'er.poblacion_cfe', 'e.NOMBRECT', 'e.NOMBREMUN', 'e.NOMBRELOC', 'e.DOMICILIO'];
    $partesBusqueda = [];
    foreach ($columnasBusqueda as $indice => $columna) {
        $clave = 'busqueda_' . $indice;
        $partesBusqueda[] = $columna . ' LIKE :' . $clave;
        $parametros[$clave] = $valorBusqueda;
    }
    $condiciones[] = '(' . implode(' OR ', $partesBusqueda) . ')';
}

if ($tarifa !== '') {
    $condiciones[] = 'er.tarifa_cfe = :tarifa';
    $parametros['tarifa'] = $tarifa;
}

if ($subnivel !== '') {
    $condiciones[] = 'e.SUBNIVEL = :subnivel';
    $parametros['subnivel'] = $subnivel;
}

if ($status !== '') {
    $condiciones[] = 'e.STATUS = :status';
    $parametros['status'] = $status;
}

$where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';
$totalVinculos = contar($conexion, 'SELECT COUNT(*) FROM escuelas_rpu');
$totalFiltrado = contar(
    $conexion,
    "SELECT COUNT(*) FROM escuelas_rpu er LEFT JOIN escuelas e ON e.CCT = er.CCT $where",
    $parametros
);
$rpusUnicos = contar($conexion, 'SELECT COUNT(DISTINCT RPU) FROM escuelas_rpu');
$escuelasUnicas = contar($conexion, 'SELECT COUNT(DISTINCT CCT) FROM escuelas_rpu');
$rpusCompartidos = contar($conexion, 'SELECT COUNT(*) FROM (SELECT RPU FROM escuelas_rpu GROUP BY RPU HAVING COUNT(*) > 1) repetidos');
$paginas = max(1, (int) ceil($totalFiltrado / $porPagina));
$pagina = min($pagina, $paginas);
$offset = ($pagina - 1) * $porPagina;
$tarifas = opciones($conexion, "SELECT DISTINCT tarifa_cfe FROM escuelas_rpu WHERE tarifa_cfe IS NOT NULL AND tarifa_cfe <> '' ORDER BY tarifa_cfe");
$subniveles = opciones($conexion, "SELECT DISTINCT SUBNIVEL FROM escuelas WHERE SUBNIVEL IS NOT NULL AND SUBNIVEL <> '' ORDER BY SUBNIVEL");
$estatus = opciones($conexion, "SELECT DISTINCT STATUS FROM escuelas WHERE STATUS IS NOT NULL AND STATUS <> '' ORDER BY STATUS");
$consulta = $conexion->prepare(
    "SELECT er.id, er.RPU, er.CCT, er.nombre_recibo_cfe, er.poblacion_cfe, er.tarifa_cfe, e.NOMBRECT, e.DOMICILIO, e.NOMBREMUN, e.NOMBRELOC, e.STATUS, e.SUBNIVEL, conteo.total_rpu
     FROM escuelas_rpu er
     LEFT JOIN escuelas e ON e.CCT = er.CCT
     LEFT JOIN (
         SELECT RPU, COUNT(*) total_rpu
         FROM escuelas_rpu
         GROUP BY RPU
     ) conteo ON conteo.RPU = er.RPU
     $where
     ORDER BY er.id DESC
     LIMIT :limite OFFSET :offset"
);

foreach ($parametros as $clave => $valor) {
    $consulta->bindValue(':' . $clave, $valor, PDO::PARAM_STR);
}

$consulta->bindValue(':limite', $porPagina, PDO::PARAM_INT);
$consulta->bindValue(':offset', $offset, PDO::PARAM_INT);
$consulta->execute();
$vinculos = $consulta->fetchAll();
$queryBase = $_GET;
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vinculos RPU-CCT | SEG Guerrero</title>
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
            <span class="eyebrow">ESCUELAS_RPU</span>
            <h1>Vinculos RPU-CCT</h1>
            <p>Consulta y exporta las relaciones confirmadas entre medidores de luz (RPU) y escuelas oficiales (CCT). Filtra por tarifa, subnivel educativo o estatus.</p>
        </div>
        <span class="alert-gold"><i class="bi bi-eye me-1"></i><?= number_format($totalFiltrado) ?> visibles</span>
    </section>
    <section class="quick-actions">
        <article class="quick-card">
            <span class="quick-icon"><i class="bi bi-link-45deg"></i></span>
            <div><strong><?= number_format($totalVinculos) ?></strong><span>Vinculos guardados</span></div>
            <small>Total</small>
        </article>
        <article class="quick-card">
            <span class="quick-icon"><i class="bi bi-lightning-charge"></i></span>
            <div><strong><?= number_format($rpusUnicos) ?></strong><span>RPU controlados</span></div>
            <small>Medidores</small>
        </article>
        <article class="quick-card">
            <span class="quick-icon"><i class="bi bi-building-check"></i></span>
            <div><strong><?= number_format($escuelasUnicas) ?></strong><span>Escuelas vinculadas</span></div>
            <small>CCT</small>
        </article>
        <article class="quick-card">
            <span class="quick-icon"><i class="bi bi-intersect"></i></span>
            <div><strong><?= number_format($rpusCompartidos) ?></strong><span>RPU con 2 o mas CCT</span></div>
            <small>Compartidos</small>
        </article>
    </section>
    <section class="results-card link-workbench">
        <div class="results-head">
            <div>
                <span class="eyebrow">EMPAREJAR RPU</span>
                <h2>Relaciona un medidor con el padrón maestro</h2>
                <p>Busca un RPU cargado y revisa escuelas sugeridas por nombre, domicilio, localidad, nivel y estatus.</p>
            </div>
        </div>
        <form id="rpu-match-form" class="import-filters link-match-form">
            <label class="search-field">
                <i class="bi bi-lightning-charge"></i>
                <input type="search" name="rpu" required placeholder="Captura el RPU del reporte CFE">
            </label>
            <button class="btn-seg compact-action" type="submit"><i class="bi bi-diagram-3 me-2"></i>Buscar coincidencias</button>
        </form>
        <div id="rpu-match-status" class="adjustment-status">Consulta un RPU que ya exista en los reportes CFE cargados.</div>
        <div id="rpu-match-result" class="link-match-result" hidden></div>
    </section>
    <section class="results-card import-control">
        <div class="results-head">
            <div>
                <span class="eyebrow">CONTROL</span>
                <h2>Padron de vinculos confirmados</h2>
            </div>
            <form class="export-form" method="post" action="../controllers/escuelaController.php">
                <input type="hidden" name="accion" value="exportar_vinculos">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['seg_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="q" value="<?= htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="tarifa" value="<?= htmlspecialchars($tarifa, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="subnivel" value="<?= htmlspecialchars($subnivel, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="status" value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>">
                <button class="btn-seg compact-action" type="submit"><i class="bi bi-download me-2"></i>Exportar <?= number_format($totalFiltrado) ?></button>
            </form>
        </div>
        <form class="import-filters" method="get" data-auto-filter>
            <label class="search-field">
                <i class="bi bi-search"></i>
                <input type="search" name="q" value="<?= htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8') ?>" placeholder="Buscar por RPU, CCT, escuela, domicilio o poblacion CFE">
            </label>
            <select name="tarifa" aria-label="Filtrar por tarifa">
                <option value="">Todas las tarifas</option>
                <?php foreach ($tarifas as $opcion): ?>
                    <option value="<?= htmlspecialchars((string) $opcion, ENT_QUOTES, 'UTF-8') ?>" <?= $tarifa === (string) $opcion ? 'selected' : '' ?>><?= htmlspecialchars((string) $opcion, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
            <select name="subnivel" aria-label="Filtrar por subnivel">
                <option value="">Todos los subniveles</option>
                <?php foreach ($subniveles as $opcion): ?>
                    <option value="<?= htmlspecialchars((string) $opcion, ENT_QUOTES, 'UTF-8') ?>" <?= $subnivel === (string) $opcion ? 'selected' : '' ?>><?= htmlspecialchars((string) $opcion, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status" aria-label="Filtrar por estatus">
                <option value="">Todos los estatus</option>
                <?php foreach ($estatus as $opcion): ?>
                    <option value="<?= htmlspecialchars((string) $opcion, ENT_QUOTES, 'UTF-8') ?>" <?= $status === (string) $opcion ? 'selected' : '' ?>>STATUS <?= htmlspecialchars((string) $opcion, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
            <span class="filter-state"><i class="bi bi-funnel"></i> Filtro automatico</span>
            <a class="clear-link" href="importaciones.php">Limpiar</a>
        </form>
        <div class="table-wrap">
            <table class="control-table">
                <thead>
                    <tr>
                        <th>RPU</th>
                        <th>Escuela oficial</th>
                        <th>Ubicacion</th>
                        <th>Recibo CFE</th>
                        <th>Tarifa</th>
                        <th>Conteo</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$vinculos): ?>
                    <tr>
                        <td colspan="7" class="empty-state">
                            <i class="bi bi-search"></i>
                            <strong>No se encontraron vinculos</strong>
                            <span>Ajusta los filtros para ampliar la busqueda.</span>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($vinculos as $vinculo): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars((string) $vinculo['RPU'], ENT_QUOTES, 'UTF-8') ?></strong>
                            <small>ID <?= number_format((int) $vinculo['id']) ?></small>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars((string) ($vinculo['NOMBRECT'] ?: 'Escuela sin catalogo'), ENT_QUOTES, 'UTF-8') ?></strong>
                            <small><?= htmlspecialchars((string) $vinculo['CCT'], ENT_QUOTES, 'UTF-8') ?> &middot; <?= htmlspecialchars((string) ($vinculo['SUBNIVEL'] ?: 'Sin subnivel'), ENT_QUOTES, 'UTF-8') ?></small>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars((string) ($vinculo['NOMBRELOC'] ?: 'Sin localidad'), ENT_QUOTES, 'UTF-8') ?></strong>
                            <small><?= htmlspecialchars((string) ($vinculo['DOMICILIO'] ?: 'Sin domicilio'), ENT_QUOTES, 'UTF-8') ?> &middot; <?= htmlspecialchars((string) ($vinculo['NOMBREMUN'] ?: 'Sin municipio'), ENT_QUOTES, 'UTF-8') ?></small>
                        </td>
                        <td>
                            <strong><?= htmlspecialchars((string) ($vinculo['nombre_recibo_cfe'] ?: 'Sin nombre CFE'), ENT_QUOTES, 'UTF-8') ?></strong>
                            <small><?= htmlspecialchars((string) ($vinculo['poblacion_cfe'] ?: 'Sin poblacion CFE'), ENT_QUOTES, 'UTF-8') ?></small>
                        </td>
                        <td><span class="status-pill"><?= htmlspecialchars((string) ($vinculo['tarifa_cfe'] ?: 'N/D'), ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td><span class="status-pill <?= (int) ($vinculo['total_rpu'] ?? 0) > 1 ? 'status-warn' : 'status-ok' ?>"><?= number_format((int) ($vinculo['total_rpu'] ?? 0)) ?> CCT</span></td>
                        <td><span class="status-pill status-ok">STATUS <?= htmlspecialchars((string) ($vinculo['STATUS'] ?: 'N/D'), ENT_QUOTES, 'UTF-8') ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="pager">
            <span>Pagina <?= number_format($pagina) ?> de <?= number_format($paginas) ?></span>
            <div>
                <?php $queryBase['pagina'] = max(1, $pagina - 1); ?>
                <a class="<?= $pagina <= 1 ? 'disabled' : '' ?>" href="?<?= htmlspecialchars(http_build_query($queryBase), ENT_QUOTES, 'UTF-8') ?>">Anterior</a>
                <?php $queryBase['pagina'] = min($paginas, $pagina + 1); ?>
                <a class="<?= $pagina >= $paginas ? 'disabled' : '' ?>" href="?<?= htmlspecialchars(http_build_query($queryBase), ENT_QUOTES, 'UTF-8') ?>">Siguiente</a>
            </div>
        </div>
    </section>
</main>
<script>
const autoFilter = document.querySelector('[data-auto-filter]');
if (autoFilter) {
    const search = autoFilter.querySelector('input[type="search"]');
    const selects = autoFilter.querySelectorAll('select');
    let timer = null;
    const submitFilters = () => autoFilter.requestSubmit();

    selects.forEach((select) => select.addEventListener('change', submitFilters));
    search.addEventListener('input', () => {
        clearTimeout(timer);
        const value = search.value.trim();
        if (value.length === 1) {
            return;
        }
        timer = setTimeout(submitFilters, 450);
    });
}

const csrf = <?= json_encode($_SESSION['seg_csrf'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const rpuMatchForm = document.getElementById('rpu-match-form');
const rpuMatchStatus = document.getElementById('rpu-match-status');
const rpuMatchResult = document.getElementById('rpu-match-result');
const matchEscape = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[character]));

function renderRpuMatch(data) {
    const cfe = data.cfe || {};
    const cfeCard = `<article class="match-cfe-card"><span>RECIBO CFE</span><strong>${matchEscape(cfe.rpu || data.rpu)} - ${matchEscape(cfe.nombre || 'Sin nombre')}</strong><small>${matchEscape(cfe.direccion || 'Sin dirección')} · ${matchEscape(cfe.poblacion || 'Sin población')}</small><small>División ${matchEscape(cfe.division || 'Sin división')} · Tarifa ${matchEscape(cfe.tarifa || 'N/D')} · Periodo ${matchEscape(cfe.periodo || 'Sin periodo')}</small></article>`;
    const vinculados = data.vinculos || [];
    if (vinculados.length) {
        rpuMatchResult.innerHTML = `${cfeCard}<div class="match-linked"><strong>Este RPU ya tiene ${vinculados.length} vínculo${vinculados.length === 1 ? '' : 's'} confirmado${vinculados.length === 1 ? '' : 's'}</strong>${vinculados.map((escuela) => `<span>${matchEscape(escuela.cct)} · ${matchEscape(escuela.nombre)} · ${matchEscape(escuela.nivel || escuela.subnivel || 'Sin nivel')}</span>`).join('')}</div>`;
        return;
    }
    const sugerencias = data.sugerencias || [];
    const cards = sugerencias.length ? sugerencias.map((escuela) => `<article class="school-match-card"><div><span class="status-pill ${Number(escuela.score) >= 70 ? 'status-ok' : 'status-warn'}">${matchEscape(escuela.score)}% coincidencia</span><strong>${matchEscape(escuela.cct)} · ${matchEscape(escuela.nombre)}</strong><small>${matchEscape(escuela.domicilio || 'Sin domicilio')}</small><small>${matchEscape(escuela.localidad || 'Sin localidad')} · ${matchEscape(escuela.municipio || 'Sin municipio')}</small><small>${matchEscape(escuela.nivel || 'Sin nivel')} · ${matchEscape(escuela.subnivel || 'Sin subnivel')} · ${matchEscape(escuela.status || 'Sin estatus')}</small><small>${matchEscape(escuela.clasificacion || escuela.fuente || 'Padrón maestro')}</small></div><button class="btn-seg compact-action" type="button" data-link-cct="${matchEscape(escuela.cct)}">Vincular</button></article>`).join('') : '<div class="empty-state"><i class="bi bi-search"></i><strong>Sin coincidencias automáticas</strong><span>Busca la escuela por CCT en el padrón o revisa localidad y domicilio del recibo.</span></div>';
    rpuMatchResult.innerHTML = `${cfeCard}<div class="match-suggestions"><div class="match-title"><strong>Escuelas sugeridas</strong><span>${sugerencias.length} opciones</span></div>${cards}</div>`;
}

async function buscarCoincidenciasRpu(rpu) {
    rpuMatchStatus.textContent = 'Comparando RPU con nombre, domicilio, localidad y padrón escolar...';
    const body = new URLSearchParams({accion: 'buscar_rpu', csrf, rpu});
    const response = await fetch('../controllers/rpuController.php', {method: 'POST', headers: {'X-CSRF-Token': csrf}, body});
    const data = await response.json();
    if (!response.ok || !data.ok) {
        throw new Error(data.error || 'No fue posible consultar el RPU.');
    }
    rpuMatchStatus.textContent = data.encontrado ? `Resultado listo para RPU ${data.rpu}.` : `El RPU ${data.rpu} no aparece todavía en los reportes CFE.`;
    rpuMatchResult.hidden = false;
    renderRpuMatch(data);
}

rpuMatchForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    try {
        await buscarCoincidenciasRpu(rpuMatchForm.rpu.value.trim());
    } catch (error) {
        rpuMatchStatus.textContent = error.message;
        rpuMatchResult.hidden = true;
    }
});

rpuMatchResult.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-link-cct]');
    if (!button) return;
    const rpu = rpuMatchForm.rpu.value.trim();
    const cct = button.dataset.linkCct;
    if (!window.confirm(`¿Confirmas vincular el RPU ${rpu} con la escuela ${cct}?`)) return;
    button.disabled = true;
    try {
        const body = new URLSearchParams({accion: 'vincular_rpu', csrf, rpu, cct});
        const response = await fetch('../controllers/rpuController.php', {method: 'POST', headers: {'X-CSRF-Token': csrf}, body});
        const data = await response.json();
        if (!response.ok || !data.ok) throw new Error(data.error || 'No fue posible guardar el vínculo.');
        rpuMatchStatus.textContent = data.mensaje;
        await buscarCoincidenciasRpu(rpu);
    } catch (error) {
        rpuMatchStatus.textContent = error.message;
        button.disabled = false;
    }
});
</script>
</body>
</html>
