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
    $condiciones[] = '(er.RPU LIKE :busqueda OR er.CCT LIKE :busqueda OR er.nombre_recibo_cfe LIKE :busqueda OR er.poblacion_cfe LIKE :busqueda OR e.NOMBRECT LIKE :busqueda OR e.NOMBREMUN LIKE :busqueda OR e.NOMBRELOC LIKE :busqueda OR e.DOMICILIO LIKE :busqueda)';
    $parametros['busqueda'] = '%' . $busqueda . '%';
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
            <p>Relaciones confirmadas entre medidores CFE y escuelas oficiales.</p>
        </div>
        <span class="alert-gold"><?= number_format($totalFiltrado) ?> visibles</span>
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
</script>
</body>
</html>
