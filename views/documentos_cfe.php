<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/services/auth.php';
segRequireLogin('../login.php');
require_once dirname(__DIR__) . '/services/conexion.php';

$segBasePath = '';
$esAdmin = segIsAdmin();
$documentos = [];
$anios = [];
$porPagina = 12;
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$totalDocumentos = 0;
$totalPaginas = 1;
$filtroAnio = preg_match('/^20\d{2}$/', (string) ($_GET['anio'] ?? '')) ? (int) $_GET['anio'] : null;
$filtroMes = filter_var($_GET['mes'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]) ?: null;
$textoBusqueda = trim((string) ($_GET['buscar'] ?? ''));
$condiciones = [];
$parametros = [];
if ($filtroAnio !== null) {
    $condiciones[] = 'anio = :anio';
    $parametros[':anio'] = $filtroAnio;
}
if ($filtroMes !== null) {
    $condiciones[] = 'mes = :mes';
    $parametros[':mes'] = $filtroMes;
}
if ($textoBusqueda !== '') {
    $condiciones[] = '(nombre_original LIKE :buscar OR descripcion LIKE :buscar)';
    $parametros[':buscar'] = '%' . $textoBusqueda . '%';
}
$whereDocumentos = $condiciones ? ' WHERE ' . implode(' AND ', $condiciones) : '';
$parametrosUrl = array_filter(['anio' => $filtroAnio, 'mes' => $filtroMes, 'buscar' => $textoBusqueda], static fn (mixed $valor): bool => $valor !== null && $valor !== '');
$urlPagina = static fn (int $numero): string => '?' . http_build_query($parametrosUrl + ['pagina' => $numero]);
try {
    $conexion = Conexion::conectar();
    $conexion->exec(
        'CREATE TABLE IF NOT EXISTS cfe_documentos_pdf (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            anio SMALLINT UNSIGNED NOT NULL,
            mes TINYINT UNSIGNED NOT NULL,
            nombre_original VARCHAR(255) NOT NULL,
            ruta_archivo VARCHAR(255) NOT NULL,
            descripcion VARCHAR(255) NULL,
            tamano_bytes INT UNSIGNED NOT NULL DEFAULT 0,
            usuario_id INT UNSIGNED NULL,
            creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_cfe_documentos_periodo (anio, mes)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
    $consultaTotal = $conexion->prepare('SELECT COUNT(*) FROM cfe_documentos_pdf' . $whereDocumentos);
    $consultaTotal->execute($parametros);
    $totalDocumentos = (int) $consultaTotal->fetchColumn();
    $totalPaginas = max(1, (int) ceil($totalDocumentos / $porPagina));
    $pagina = min($pagina, $totalPaginas);
    $offset = ($pagina - 1) * $porPagina;
    $consulta = $conexion->prepare('SELECT id, anio, mes, nombre_original, descripcion, tamano_bytes, creado_en FROM cfe_documentos_pdf' . $whereDocumentos . ' ORDER BY anio DESC, mes DESC, id DESC LIMIT ' . $porPagina . ' OFFSET ' . $offset);
    foreach ($parametros as $nombre => $valor) {
        $consulta->bindValue($nombre, $valor);
    }
    $consulta->execute();
    $documentos = $consulta->fetchAll();
    $anios = array_map(static fn (mixed $anio): int => (int) $anio, $conexion->query('SELECT DISTINCT anio FROM cfe_documentos_pdf ORDER BY anio DESC')->fetchAll(PDO::FETCH_COLUMN));
} catch (Throwable) {
}
if (empty($_SESSION['seg_csrf'])) {
    $_SESSION['seg_csrf'] = bin2hex(random_bytes(24));
}
$meses = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['seg_csrf'], ENT_QUOTES, 'UTF-8') ?>">
    <title>Documentos CFE | SIEE Guerrero</title>
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
<main class="content document-library-view">
    <section class="heading document-heading">
        <div>
            <span class="eyebrow">ARCHIVO INSTITUCIONAL</span>
            <h1>Documentos CFE</h1>
            <p>Consulta los reportes de facturacion publicados por periodo.</p>
        </div>
        <span class="alert-gold"><i class="bi bi-shield-check me-1"></i><?= $esAdmin ? 'Administracion de documentos' : 'Consulta de documentos' ?></span>
    </section>

    <?php if ($esAdmin): ?>
        <section class="results-card document-upload-card">
            <div class="results-head"><div><span class="eyebrow">NUEVO DOCUMENTO</span><h2>Publicar reporte PDF</h2><p>Selecciona el periodo y agrega el reporte para consulta institucional.</p></div></div>
            <form id="pdf-upload-form" class="document-upload-form" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="subir_pdf">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['seg_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                <label class="pdf-drop-zone" for="documento_pdf"><input id="documento_pdf" name="documento_pdf" type="file" accept="application/pdf,.pdf" required><i class="bi bi-file-earmark-pdf"></i><strong>Seleccionar reporte PDF</strong><small id="pdf-file-name">Hasta 25 MB</small></label>
                <label><span>Mes</span><select name="mes" required><?php foreach ($meses as $numero => $nombre): ?><option value="<?= $numero ?>" <?= $numero === (int) date('n') ? 'selected' : '' ?>><?= $nombre ?></option><?php endforeach; ?></select></label>
                <label><span>Año</span><input name="anio" type="number" min="2020" max="2100" value="<?= (int) date('Y') ?>" required></label>
                <label class="document-description"><span>Referencia</span><input name="descripcion" maxlength="255" placeholder="Ej. Reporte de facturacion mensual"></label>
                <button class="btn-seg compact-action" type="submit"><i class="bi bi-cloud-arrow-up me-2"></i>Publicar PDF</button>
            </form>
        </section>
    <?php endif; ?>

    <section class="results-card document-library-card">
        <div class="results-head document-library-head"><div><span class="eyebrow">BIBLIOTECA CFE</span><h2>Reportes disponibles</h2><p><?= $totalDocumentos ? number_format($totalDocumentos) . ' documento(s) encontrado(s).' : 'No hay documentos con esos filtros.' ?></p></div><form id="document-filter-form" class="document-filters" method="get"><select id="document-year-filter" name="anio"><option value="">Todos los años</option><?php foreach ($anios as $anio): ?><option value="<?= $anio ?>" <?= $filtroAnio === $anio ? 'selected' : '' ?>><?= $anio ?></option><?php endforeach; ?></select><select id="document-month-filter" name="mes"><option value="">Todos los meses</option><?php foreach ($meses as $numero => $nombre): ?><option value="<?= $numero ?>" <?= $filtroMes === $numero ? 'selected' : '' ?>><?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select><input id="document-search" name="buscar" type="search" value="<?= htmlspecialchars($textoBusqueda, ENT_QUOTES, 'UTF-8') ?>" placeholder="Buscar reporte o referencia"><button class="document-filter-reset" type="button" id="document-filter-reset" title="Limpiar filtros"><i class="bi bi-arrow-counterclockwise"></i><span>Limpiar</span></button></form></div>
        <div id="document-grid" class="document-grid">
            <?php foreach ($documentos as $documento): ?>
                <article class="document-card">
                    <a class="document-preview" target="_blank" rel="noopener" href="../controllers/documentosCfeController.php?accion=ver_pdf&id=<?= (int) $documento['id'] ?>" aria-label="Abrir <?= htmlspecialchars((string) $documento['nombre_original'], ENT_QUOTES, 'UTF-8') ?>"><canvas data-pdf-preview="../controllers/documentosCfeController.php?accion=ver_pdf&id=<?= (int) $documento['id'] ?>"></canvas><span><i class="bi bi-file-earmark-pdf"></i>Vista previa</span></a>
                    <div class="document-card-body"><span class="document-periodo">DOC-CFE-<?= (int) $documento['anio'] ?>-<?= str_pad((string) $documento['mes'], 2, '0', STR_PAD_LEFT) ?>-<?= str_pad((string) $documento['id'], 4, '0', STR_PAD_LEFT) ?></span><strong>Reporte CFE · <?= htmlspecialchars(($meses[(int) $documento['mes']] ?? 'Mes') . ' ' . $documento['anio'], ENT_QUOTES, 'UTF-8') ?></strong><small class="document-file-name" title="<?= htmlspecialchars((string) $documento['nombre_original'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $documento['nombre_original'], ENT_QUOTES, 'UTF-8') ?></small><?php if (!empty($documento['descripcion'])): ?><small><?= htmlspecialchars((string) $documento['descripcion'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?><em><?= number_format(((int) $documento['tamano_bytes']) / 1048576, 1) ?> MB · Publicado <?= htmlspecialchars(date('d/m/Y', strtotime((string) $documento['creado_en'])), ENT_QUOTES, 'UTF-8') ?></em></div>
                    <div class="document-card-actions"><a class="btn-seg compact-action" target="_blank" rel="noopener" href="../controllers/documentosCfeController.php?accion=ver_pdf&id=<?= (int) $documento['id'] ?>"><i class="bi bi-eye"></i><span>Ver</span></a><a class="document-download" href="../controllers/documentosCfeController.php?accion=descargar_pdf&id=<?= (int) $documento['id'] ?>" title="Descargar PDF"><i class="bi bi-download"></i></a><?php if ($esAdmin): ?><button class="document-delete" type="button" data-document-id="<?= (int) $documento['id'] ?>" title="Eliminar documento"><i class="bi bi-trash3"></i></button><?php endif; ?></div>
                </article>
            <?php endforeach; ?>
            <p id="documents-empty" class="document-empty" <?= $documentos ? 'hidden' : '' ?>>No hay documentos para mostrar.</p>
        </div>
        <?php if ($totalPaginas > 1): ?>
            <nav class="document-pagination" aria-label="Paginacion de documentos">
                <?php if ($pagina > 1): ?><a href="<?= htmlspecialchars($urlPagina($pagina - 1), ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-chevron-left"></i>Anterior</a><?php endif; ?>
                <?php for ($numero = max(1, $pagina - 2); $numero <= min($totalPaginas, $pagina + 2); $numero++): ?><a class="<?= $numero === $pagina ? 'active' : '' ?>" href="<?= htmlspecialchars($urlPagina($numero), ENT_QUOTES, 'UTF-8') ?>"><?= $numero ?></a><?php endfor; ?>
                <?php if ($pagina < $totalPaginas): ?><a href="<?= htmlspecialchars($urlPagina($pagina + 1), ENT_QUOTES, 'UTF-8') ?>">Siguiente<i class="bi bi-chevron-right"></i></a><?php endif; ?>
            </nav>
        <?php endif; ?>
    </section>
</main>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
const pdfForm = document.getElementById('pdf-upload-form');
const pdfInput = document.getElementById('documento_pdf');
const fileLabel = document.getElementById('pdf-file-name');
const yearFilter = document.getElementById('document-year-filter');
const monthFilter = document.getElementById('document-month-filter');
const searchInput = document.getElementById('document-search');
const documentFilterForm = document.getElementById('document-filter-form');
const documentFilterReset = document.getElementById('document-filter-reset');
const csrf = document.querySelector('meta[name="csrf-token"]').content;
let searchTimeout;
yearFilter.addEventListener('change', () => documentFilterForm.submit());
monthFilter.addEventListener('change', () => documentFilterForm.submit());
searchInput.addEventListener('input', () => { clearTimeout(searchTimeout); searchTimeout = window.setTimeout(() => documentFilterForm.submit(), 450); });
documentFilterReset.addEventListener('click', () => { window.location.href = 'documentos_cfe.php'; });
if (pdfInput) { pdfInput.addEventListener('change', () => { fileLabel.textContent = pdfInput.files[0] ? pdfInput.files[0].name : 'Hasta 25 MB'; }); pdfForm.addEventListener('submit', async event => { event.preventDefault(); const body = new FormData(pdfForm); Swal.fire({title:'Publicando documento...',text:'Optimizando y guardando el PDF para consulta institucional.',allowOutsideClick:false,didOpen:() => Swal.showLoading()}); try { const response = await fetch('../controllers/documentosCfeController.php',{method:'POST',headers:{'X-CSRF-Token':csrf},body}); const data = await response.json(); if (!response.ok || !data.ok) throw new Error(data.error || 'No fue posible publicar el documento.'); const detalle = data.tamano_original ? `${data.mensaje}\n${(data.tamano_original / 1048576).toFixed(1)} MB -> ${(data.tamano_final / 1048576).toFixed(1)} MB` : data.mensaje; await Swal.fire({icon:'success',title:'Documento publicado',text:detalle,confirmButtonColor:'#6a1b29'}); window.location.reload(); } catch (error) { Swal.fire({icon:'error',title:'No se pudo publicar',text:error.message || 'Intenta nuevamente.',confirmButtonColor:'#6a1b29'}); } }); }
document.querySelectorAll('.document-delete').forEach(button => button.addEventListener('click', async () => { const confirm = await Swal.fire({icon:'warning',title:'Eliminar documento',text:'El PDF dejara de estar disponible para todos los usuarios.',showCancelButton:true,confirmButtonText:'Eliminar',cancelButtonText:'Cancelar',confirmButtonColor:'#8b1d2c'}); if (!confirm.isConfirmed) return; const body = new FormData(); body.append('accion','eliminar_pdf'); body.append('id',button.dataset.documentId); body.append('csrf',csrf); try { const response = await fetch('../controllers/documentosCfeController.php',{method:'POST',headers:{'X-CSRF-Token':csrf},body}); const data = await response.json(); if (!response.ok || !data.ok) throw new Error(data.error || 'No fue posible eliminar el documento.'); window.location.reload(); } catch (error) { Swal.fire({icon:'error',title:'No se pudo eliminar',text:error.message || 'Intenta nuevamente.',confirmButtonColor:'#6a1b29'}); } }));
if (window.pdfjsLib) { pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js'; document.querySelectorAll('[data-pdf-preview]').forEach(async canvas => { try { const pdf = await pdfjsLib.getDocument(canvas.dataset.pdfPreview).promise; const page = await pdf.getPage(1); const base = page.getViewport({scale: 1}); const scale = Math.min(1, 220 / base.width); const viewport = page.getViewport({scale}); const context = canvas.getContext('2d'); canvas.width = Math.ceil(viewport.width); canvas.height = Math.ceil(viewport.height); await page.render({canvasContext: context, viewport}).promise; canvas.closest('.document-preview').classList.add('loaded'); } catch (_) { canvas.closest('.document-preview').classList.add('failed'); } }); }
</script>
</body>
</html>
