<?php

declare(strict_types=1);

session_start();

require_once dirname(__DIR__) . '/services/conexion.php';

class EscuelaController
{
    public function procesarArchivos(): void
    {
        set_time_limit(0);
        $this->validarToken();
        $camposRequeridos = ['archivo_seg', 'archivo_oficializacion', 'archivo_cfe_a'];
        $campos = $camposRequeridos;
        if (isset($_FILES['archivo_cfe_b']) && $_FILES['archivo_cfe_b']['error'] === UPLOAD_ERR_OK) {
            $campos[] = 'archivo_cfe_b';
        }
        foreach ($camposRequeridos as $campo) {
            if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] !== UPLOAD_ERR_OK) {
                $this->responder(['ok' => false, 'error' => 'Carga estructura SEG, Oficializacion 911 y al menos un reporte CFE.'], 422);
            }
        }
        foreach ($campos as $campo) {
            $extension = strtolower(pathinfo($_FILES[$campo]['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, ['xlsx', 'xls'], true)) {
                $this->responder(['ok' => false, 'error' => 'Solo se admiten archivos Excel XLSX o XLS.'], 422);
            }
        }
        $rutas = [];
        try {
            foreach ($campos as $campo) {
                $extension = strtolower(pathinfo($_FILES[$campo]['name'], PATHINFO_EXTENSION));
                $ruta = sys_get_temp_dir() . DIRECTORY_SEPARATOR . bin2hex(random_bytes(16)) . '.' . $extension;
                if (!move_uploaded_file($_FILES[$campo]['tmp_name'], $ruta)) {
                    throw new RuntimeException('No fue posible almacenar temporalmente los archivos.');
                }
                $rutas[$campo] = $ruta;
            }
            $python = $this->localizarPython();
            $script = dirname(__DIR__) . '/services/procesar_archivos.py';
            $comando = escapeshellarg($python)
                . ' ' . escapeshellarg($script)
                . ' ' . escapeshellarg($rutas['archivo_seg'])
                . ' ' . escapeshellarg($rutas['archivo_oficializacion'])
                . ' ' . escapeshellarg($rutas['archivo_cfe_a']);
            if (isset($rutas['archivo_cfe_b'])) {
                $comando .= ' ' . escapeshellarg($rutas['archivo_cfe_b']);
            }
            $comando .= ' 2>&1';
            $salida = shell_exec($comando);
            $lineas = is_string($salida)
                ? array_values(array_filter(array_map('trim', preg_split('/\R/', $salida))))
                : [];
            $json = $lineas ? end($lineas) : '';
            $resultado = json_decode($json, true);
            if (!is_array($resultado)) {
                $detalle = trim(strip_tags((string) $salida));
                $this->responder([
                    'ok' => false,
                    'error' => $detalle !== ''
                        ? 'No se pudo ejecutar el motor predictivo: ' . mb_substr($detalle, 0, 500)
                        : 'No se encontro una instalacion funcional de Python.'
                ], 500);
            }
            $resultado = $this->marcarVinculosExistentes($resultado);
            $this->responder($resultado, !empty($resultado['ok']) ? 200 : 422);
        } catch (Throwable $e) {
            $this->responder(['ok' => false, 'error' => $e->getMessage()], 500);
        } finally {
            foreach ($rutas as $ruta) {
                if (is_file($ruta)) {
                    unlink($ruta);
                }
            }
        }
    }

    public function sincronizarCatalogos(): void
    {
        set_time_limit(0);
        $this->validarToken();
        $campos = ['catalogo_seg', 'oficializacion_911'];
        $archivos = [];
        foreach ($campos as $campo) {
            if (isset($_FILES[$campo]) && $_FILES[$campo]['error'] === UPLOAD_ERR_OK) {
                $archivos[] = $campo;
            }
        }
        if (!$archivos) {
            $this->responder(['ok' => false, 'error' => 'Carga al menos un catalogo para sincronizar.'], 422);
        }
        foreach ($archivos as $campo) {
            $extension = strtolower(pathinfo($_FILES[$campo]['name'], PATHINFO_EXTENSION));
            $permitidas = $campo === 'catalogo_seg' ? ['csv', 'xlsx', 'xls'] : ['xlsx', 'xls'];
            if (!in_array($extension, $permitidas, true)) {
                $this->responder(['ok' => false, 'error' => 'El Catalogo SEG debe ser CSV o Excel y la Oficializacion 911 debe ser Excel.'], 422);
            }
        }
        $rutas = [];
        try {
            foreach ($archivos as $campo) {
                $extension = strtolower(pathinfo($_FILES[$campo]['name'], PATHINFO_EXTENSION));
                $ruta = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $campo . '_' . bin2hex(random_bytes(16)) . '.' . $extension;
                if (!move_uploaded_file($_FILES[$campo]['tmp_name'], $ruta)) {
                    throw new RuntimeException('No fue posible almacenar temporalmente los catalogos.');
                }
                $rutas[$campo] = $ruta;
            }
            $python = $this->localizarPython();
            $script = dirname(__DIR__) . '/services/guardar_escuelas_bd.py';
            $comando = escapeshellarg($python) . ' ' . escapeshellarg($script);
            foreach ($rutas as $ruta) {
                $comando .= ' ' . escapeshellarg($ruta);
            }
            $comando .= ' 2>&1';
            $salida = shell_exec($comando);
            $lineas = is_string($salida)
                ? array_values(array_filter(array_map('trim', preg_split('/\R/', $salida))))
                : [];
            $json = $lineas ? end($lineas) : '';
            $resultado = json_decode($json, true);
            if (!is_array($resultado)) {
                $detalle = trim(strip_tags((string) $salida));
                $this->responder([
                    'ok' => false,
                    'error' => $detalle !== ''
                        ? 'No se pudo sincronizar la base local: ' . mb_substr($detalle, 0, 500)
                        : 'No se encontro una respuesta valida del sincronizador.'
                ], 500);
            }
            $this->responder($resultado, !empty($resultado['ok']) ? 200 : 422);
        } catch (Throwable $e) {
            $this->responder(['ok' => false, 'error' => $e->getMessage()], 500);
        } finally {
            foreach ($rutas as $ruta) {
                if (is_file($ruta)) {
                    unlink($ruta);
                }
            }
        }
    }

    private function marcarVinculosExistentes(array $resultado): array
    {
        if (empty($resultado['ok']) || empty($resultado['resultados']) || !is_array($resultado['resultados'])) {
            return $resultado;
        }
        $rpus = array_values(array_unique(array_filter(array_map(
            static fn (array $registro): string => trim((string) ($registro['rpu'] ?? '')),
            $resultado['resultados']
        ))));
        if (!$rpus) {
            return $resultado;
        }
        $marcadores = implode(',', array_fill(0, count($rpus), '?'));
        $consulta = Conexion::conectar()->prepare(
            "SELECT er.RPU, er.CCT, e.NOMBRECT FROM escuelas_rpu er LEFT JOIN escuelas e ON e.CCT = er.CCT WHERE er.RPU IN ($marcadores) ORDER BY er.RPU, er.id"
        );
        $consulta->execute($rpus);
        $vinculos = [];
        foreach ($consulta->fetchAll() as $fila) {
            $vinculos[(string) $fila['RPU']][] = [
                'cct' => (string) $fila['CCT'],
                'nombre_escuela' => $fila['NOMBRECT'] ?? null
            ];
        }
        foreach ($resultado['resultados'] as &$registro) {
            $rpu = (string) ($registro['rpu'] ?? '');
            $vinculosRpu = $vinculos[$rpu] ?? [];
            $cctsVinculados = array_map(static fn (array $vinculo): string => (string) $vinculo['cct'], $vinculosRpu);
            $registro['vinculo_confirmado'] = $vinculosRpu !== [];
            $registro['vinculos_confirmados'] = $vinculosRpu;
            $registro['cct_vinculado'] = $cctsVinculados[0] ?? null;
            $registro['nombre_escuela_vinculada'] = $vinculosRpu[0]['nombre_escuela'] ?? null;
            if (!empty($registro['opciones']) && is_array($registro['opciones'])) {
                foreach ($registro['opciones'] as &$opcion) {
                    $opcion['vinculado'] = in_array((string) ($opcion['cct'] ?? ''), $cctsVinculados, true);
                }
                unset($opcion);
            }
        }
        unset($registro);
        return $resultado;
    }

    private function localizarPython(): string
    {
        $configurado = trim((string) getenv('PYTHON_BIN'));
        if ($configurado !== '' && is_file($configurado)) {
            return $configurado;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $localAppData = getenv('LOCALAPPDATA') ?: 'C:\\Users\\' . get_current_user() . '\\AppData\\Local';
            $instalaciones = glob($localAppData . '\\Programs\\Python\\Python*\\python.exe') ?: [];
            rsort($instalaciones, SORT_NATURAL);
            foreach ($instalaciones as $instalacion) {
                if (is_file($instalacion)) {
                    return $instalacion;
                }
            }
        }

        return 'python';
    }

    public function confirmarVinculo(): void
    {
        $this->validarToken();
        try {
            $conexion = Conexion::conectar();
            $this->prepararTablaVinculos($conexion);
            $cct = trim((string) ($_POST['CCT'] ?? $_POST['cct'] ?? ''));
            $rpu = trim((string) ($_POST['RPU'] ?? $_POST['rpu'] ?? ''));
            $nombreRecibo = trim((string) ($_POST['nombre_recibo_cfe'] ?? ''));
            $poblacion = trim((string) ($_POST['poblacion_cfe'] ?? ''));
            $tarifa = trim((string) ($_POST['tarifa_cfe'] ?? ''));

            if ($cct === '' || $rpu === '') {
                throw new RuntimeException('Faltan los parametros obligatorios CCT o RPU.');
            }

            $consulta = $conexion->prepare(
                'INSERT INTO escuelas_rpu (CCT, RPU, nombre_recibo_cfe, poblacion_cfe, tarifa_cfe)
                 VALUES (:cct, :rpu, :nombre_recibo, :poblacion, :tarifa)
                 ON DUPLICATE KEY UPDATE nombre_recibo_cfe = VALUES(nombre_recibo_cfe), poblacion_cfe = VALUES(poblacion_cfe), tarifa_cfe = VALUES(tarifa_cfe)'
            );
            $consulta->execute([
                'cct' => $cct,
                'rpu' => $rpu,
                'nombre_recibo' => $nombreRecibo !== '' ? $nombreRecibo : null,
                'poblacion' => $poblacion !== '' ? $poblacion : null,
                'tarifa' => $tarifa !== '' ? $tarifa : null
            ]);

            $this->responder(['ok' => true, 'mensaje' => 'Vinculo registrado con exito en el padron de escuelas_rpu.']);
        } catch (Throwable $e) {
            $this->responder(['ok' => false, 'error' => 'Fallo de base de datos: ' . $e->getMessage()], 500);
        }
    }

    public function eliminarVinculo(): void
    {
        $this->validarToken();
        try {
            $conexion = Conexion::conectar();
            $this->prepararTablaVinculos($conexion);
            $cct = trim((string) ($_POST['CCT'] ?? $_POST['cct'] ?? ''));
            $rpu = trim((string) ($_POST['RPU'] ?? $_POST['rpu'] ?? ''));
            if ($cct === '' || $rpu === '') {
                throw new RuntimeException('Faltan los parametros obligatorios CCT o RPU.');
            }
            $consulta = $conexion->prepare('DELETE FROM escuelas_rpu WHERE CCT = :cct AND RPU = :rpu');
            $consulta->execute(['cct' => $cct, 'rpu' => $rpu]);
            $this->responder(['ok' => true, 'mensaje' => 'Vinculo eliminado correctamente.']);
        } catch (Throwable $e) {
            $this->responder(['ok' => false, 'error' => 'Fallo de base de datos: ' . $e->getMessage()], 500);
        }
    }

    public function autoVincularMasivo(): void
    {
        $this->validarToken();
        try {
            $vinculos = json_decode((string) ($_POST['vinculos'] ?? '[]'), true);
            if (!is_array($vinculos) || !$vinculos) {
                throw new RuntimeException('No se recibieron vinculos para procesar.');
            }
            $conexion = Conexion::conectar();
            $this->prepararTablaVinculos($conexion);
            $conexion->beginTransaction();
            $consulta = $conexion->prepare(
                'INSERT INTO escuelas_rpu (CCT, RPU, nombre_recibo_cfe, poblacion_cfe, tarifa_cfe)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE nombre_recibo_cfe = VALUES(nombre_recibo_cfe), poblacion_cfe = VALUES(poblacion_cfe), tarifa_cfe = VALUES(tarifa_cfe)'
            );
            $total = 0;
            foreach ($vinculos as $vinculo) {
                if (!is_array($vinculo)) {
                    continue;
                }
                $cct = trim((string) ($vinculo['CCT'] ?? $vinculo['cct'] ?? ''));
                $rpu = trim((string) ($vinculo['RPU'] ?? $vinculo['rpu'] ?? ''));
                if ($cct === '' || $rpu === '') {
                    continue;
                }
                $nombreRecibo = trim((string) ($vinculo['nombre_recibo_cfe'] ?? ''));
                $poblacion = trim((string) ($vinculo['poblacion_cfe'] ?? ''));
                $tarifa = trim((string) ($vinculo['tarifa_cfe'] ?? ''));
                $consulta->execute([
                    $cct,
                    $rpu,
                    $nombreRecibo !== '' ? $nombreRecibo : null,
                    $poblacion !== '' ? $poblacion : null,
                    $tarifa !== '' ? $tarifa : null
                ]);
                $total++;
            }
            if ($total === 0) {
                throw new RuntimeException('No se encontraron vinculos validos para insertar.');
            }
            $conexion->commit();
            $this->responder(['ok' => true, 'total' => $total, 'mensaje' => 'Auto-vinculacion masiva completada.']);
        } catch (Throwable $e) {
            if (isset($conexion) && $conexion instanceof PDO && $conexion->inTransaction()) {
                $conexion->rollBack();
            }
            $this->responder(['ok' => false, 'error' => 'Fallo de auto-vinculacion: ' . $e->getMessage()], 500);
        }
    }

    public function buscarEscuelas(): void
    {
        $this->validarToken();
        try {
            $termino = trim((string) ($_POST['q'] ?? ''));
            if (mb_strlen($termino) < 2) {
                $this->responder(['ok' => true, 'escuelas' => []]);
            }
            $conexion = Conexion::conectar();
            $busqueda = '%' . str_replace(['%', '_'], ['\%', '\_'], $termino) . '%';
            $consulta = $conexion->prepare(
                "SELECT CCT, NOMBRECT, NOMBREMUN, NOMBRELOC, STATUS, SUBNIVEL
                 FROM escuelas
                 WHERE CCT LIKE ? OR NOMBRECT LIKE ? OR NOMBREMUN LIKE ? OR NOMBRELOC LIKE ?
                 ORDER BY CASE WHEN CCT = ? THEN 0 WHEN CCT LIKE ? THEN 1 ELSE 2 END, NOMBRECT
                 LIMIT 25"
            );
            $consulta->execute([$busqueda, $busqueda, $busqueda, $busqueda, $termino, $termino . '%']);
            $this->responder(['ok' => true, 'escuelas' => $consulta->fetchAll()]);
        } catch (Throwable $e) {
            $this->responder(['ok' => false, 'error' => 'Fallo de busqueda: ' . $e->getMessage()], 500);
        }
    }

    private function prepararTablaVinculos(PDO $conexion): void
    {
        $consulta = $conexion->query(
            "SELECT INDEX_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'escuelas_rpu' AND NON_UNIQUE = 0 GROUP BY INDEX_NAME HAVING SUM(COLUMN_NAME = 'RPU') = 1 AND COUNT(*) = 1"
        );
        foreach ($consulta->fetchAll(PDO::FETCH_COLUMN) as $indice) {
            if ($indice !== 'PRIMARY') {
                $conexion->exec('ALTER TABLE escuelas_rpu DROP INDEX `' . str_replace('`', '``', (string) $indice) . '`');
            }
        }
        $consulta = $conexion->query(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'escuelas_rpu' AND INDEX_NAME = 'uniq_escuela_rpu'"
        );
        if ((int) $consulta->fetchColumn() === 0) {
            $conexion->exec('ALTER TABLE escuelas_rpu ADD UNIQUE KEY `uniq_escuela_rpu` (`CCT`, `RPU`)');
        }
    }

    private function validarToken(): void
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf'] ?? '';
        if (!hash_equals($_SESSION['seg_csrf'] ?? '', $token)) {
            $this->responder(['ok' => false, 'error' => 'La sesion de seguridad no es valida.'], 419);
        }
    }

    private function responder(array $datos, int $estado = 200): never
    {
        http_response_code($estado);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$controlador = new EscuelaController();
$accion = $_POST['accion'] ?? '';

if ($accion === 'procesar_archivos') {
    $controlador->procesarArchivos();
}

if ($accion === 'sincronizar_catalogos') {
    $controlador->sincronizarCatalogos();
}

if ($accion === 'confirmar_vinculo') {
    $controlador->confirmarVinculo();
}

if ($accion === 'eliminar_vinculo') {
    $controlador->eliminarVinculo();
}

if ($accion === 'auto_vincular_masivo') {
    $controlador->autoVincularMasivo();
}

if ($accion === 'buscar_escuelas') {
    $controlador->buscarEscuelas();
}

http_response_code(400);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => false, 'error' => 'Accion no reconocida.'], JSON_UNESCAPED_UNICODE);
