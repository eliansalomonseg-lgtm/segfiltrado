<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/services/conexion.php';

$segBasePath = '';

function contarRegistros(PDO $conexion, string $tabla): int
{
    $consulta = $conexion->query("SELECT COUNT(*) FROM `$tabla`");
    return (int) $consulta->fetchColumn();
}

function ultimaActualizacion(PDO $conexion, string $tabla): string
{
    $consulta = $conexion->prepare(
        "SELECT UPDATE_TIME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
    );
    $consulta->execute([$tabla]);
    $fecha = $consulta->fetchColumn();
    return $fecha ? (string) $fecha : 'Sin registro';
}

$conexion = Conexion::conectar();
$tablas = [
    ['tabla' => 'escuelas', 'nombre' => 'Catalogo de escuelas', 'descripcion' => 'CCT, nombre oficial, domicilio, municipio, localidad, status y subnivel.'],
    ['tabla' => 'escuelas_rpu', 'nombre' => 'Vinculos RPU-CCT', 'descripcion' => 'Relaciones confirmadas entre medidores CFE y escuelas oficiales.'],
    ['tabla' => 'cfe_precarga', 'nombre' => 'Precarga CFE', 'descripcion' => 'Registros auxiliares de recibos CFE cuando se utilice carga previa.'],
];

$resumen = [];
foreach ($tablas as $tabla) {
    $resumen[] = [
        'tabla' => $tabla['tabla'],
        'nombre' => $tabla['nombre'],
        'descripcion' => $tabla['descripcion'],
        'total' => contarRegistros($conexion, $tabla['tabla']),
        'actualizacion' => ultimaActualizacion($conexion, $tabla['tabla']),
    ];
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Importaciones | SEG Guerrero</title>
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
            <span class="eyebrow">CONTROL DE DATOS</span>
            <h1>Importaciones</h1>
            <p>Consulta las tablas locales que alimentan la consolidacion de la base seg.</p>
        </div>
        <span class="alert-gold">Datos sincronizados</span>
    </section>
    <section class="quick-actions">
        <article class="quick-card">
            <span class="quick-icon"><i class="bi bi-building"></i></span>
            <div><strong><?= number_format($resumen[0]['total']) ?></strong><span>Escuelas cargadas</span></div>
            <small>Catalogo</small>
        </article>
        <article class="quick-card">
            <span class="quick-icon"><i class="bi bi-link-45deg"></i></span>
            <div><strong><?= number_format($resumen[1]['total']) ?></strong><span>Vinculos guardados</span></div>
            <small>RPU-CCT</small>
        </article>
        <article class="quick-card">
            <span class="quick-icon"><i class="bi bi-file-earmark-spreadsheet"></i></span>
            <div><strong><?= number_format($resumen[2]['total']) ?></strong><span>Registros CFE</span></div>
            <small>Precarga</small>
        </article>
    </section>
    <section class="results-card">
        <div class="results-head">
            <div><span class="eyebrow">TABLAS</span><h2>Resumen de importaciones</h2></div>
            <span class="alert-gold"><?= count($resumen) ?> tablas monitoreadas</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Tabla</th>
                        <th>Contenido</th>
                        <th>Registros</th>
                        <th>Ultima actualizacion</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($resumen as $fila): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($fila['nombre'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($fila['tabla'], ENT_QUOTES, 'UTF-8') ?></small></td>
                        <td><?= htmlspecialchars($fila['descripcion'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><strong><?= number_format($fila['total']) ?></strong></td>
                        <td><?= htmlspecialchars($fila['actualizacion'], ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
