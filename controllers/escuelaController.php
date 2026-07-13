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
            $this->prepararTablaEscuelas(Conexion::conectar());
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
        $conexion = Conexion::conectar();
        $this->prepararTablaEscuelas($conexion);
        $consulta = $conexion->prepare(
            "SELECT er.RPU, er.CCT, e.NOMBRECT, e.DOMICILIO FROM escuelas_rpu er LEFT JOIN escuelas e ON e.CCT = er.CCT WHERE er.RPU IN ($marcadores) ORDER BY er.RPU, er.id"
        );
        $consulta->execute($rpus);
        $vinculos = [];
        foreach ($consulta->fetchAll() as $fila) {
            $vinculos[(string) $fila['RPU']][] = [
                'cct' => (string) $fila['CCT'],
                'nombre_escuela' => $fila['NOMBRECT'] ?? null,
                'direccion_escuela' => $fila['DOMICILIO'] ?? null
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
            $nivel = trim((string) ($_POST['nivel'] ?? ''));
            $poblacion = trim((string) ($_POST['poblacion'] ?? ''));
            $origen = trim((string) ($_POST['origen'] ?? ''));
            if (mb_strlen($termino) < 2 && $nivel === '' && mb_strlen($poblacion) < 2 && $origen === '') {
                $this->responder(['ok' => true, 'escuelas' => []]);
            }
            $conexion = Conexion::conectar();
            $this->prepararTablaEscuelas($conexion);
            $condiciones = [];
            $parametros = [];
            if (mb_strlen($termino) >= 2) {
                $busqueda = '%' . str_replace(['%', '_'], ['\%', '\_'], $termino) . '%';
                $condiciones[] = '(CCT LIKE :busqueda OR NOMBRECT LIKE :busqueda OR DOMICILIO LIKE :busqueda OR NOMBREMUN LIKE :busqueda OR NOMBRELOC LIKE :busqueda OR HOMO LIKE :busqueda OR ZONA LIKE :busqueda OR SECTOR LIKE :busqueda)';
                $parametros['busqueda'] = $busqueda;
            }
            if ($nivel !== '') {
                $condiciones[] = '(NIVEL LIKE :nivel OR SUBNIVEL LIKE :nivel)';
                $parametros['nivel'] = '%' . str_replace(['%', '_'], ['\%', '\_'], $nivel) . '%';
            }
            if (mb_strlen($poblacion) >= 2) {
                $condiciones[] = '(NOMBRELOC LIKE :poblacion OR NOMBREMUN LIKE :poblacion OR COLONIA LIKE :poblacion OR NOMBRECOL LIKE :poblacion)';
                $parametros['poblacion'] = '%' . str_replace(['%', '_'], ['\%', '\_'], $poblacion) . '%';
            }
            if ($origen !== '') {
                $condiciones[] = 'ORIGEN LIKE :origen';
                $parametros['origen'] = '%' . str_replace(['%', '_'], ['\%', '\_'], $origen) . '%';
            }
            $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';
            $consulta = $conexion->prepare(
                "SELECT CCT, NOMBRECT, DOMICILIO, NOMBREMUN, NOMBRELOC, STATUS, SUBNIVEL, NIVEL, HOMO, TURNO, ZONA, SECTOR, ORIGEN, CONTROL, SOST_CONTROL, C_NOM_VIALIDAD, N_EXTNUM
                 FROM escuelas
                 $where
                 ORDER BY CASE WHEN CCT = :termino_exacto THEN 0 WHEN CCT LIKE :termino_inicio THEN 1 ELSE 2 END, NOMBREMUN, NOMBRELOC, NOMBRECT
                 LIMIT 50"
            );
            $parametros['termino_exacto'] = $termino;
            $parametros['termino_inicio'] = $termino . '%';
            $consulta->execute($parametros);
            $this->responder(['ok' => true, 'escuelas' => $consulta->fetchAll()]);
        } catch (Throwable $e) {
            $this->responder(['ok' => false, 'error' => 'Fallo de busqueda: ' . $e->getMessage()], 500);
        }
    }

    public function exportarVinculos(): void
    {
        $this->validarToken();
        try {
            $conexion = Conexion::conectar();
            $this->prepararTablaEscuelas($conexion);
            $busqueda = trim((string) ($_POST['q'] ?? ''));
            $tarifa = trim((string) ($_POST['tarifa'] ?? ''));
            $subnivel = trim((string) ($_POST['subnivel'] ?? ''));
            $status = trim((string) ($_POST['status'] ?? ''));
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
            $consulta = $conexion->prepare(
                "SELECT er.RPU, er.CCT, e.NOMBRECT, e.DOMICILIO, e.NOMBREMUN, e.NOMBRELOC, e.STATUS, e.SUBNIVEL, er.nombre_recibo_cfe, er.poblacion_cfe, er.tarifa_cfe, conteo.total_rpu
                 FROM escuelas_rpu er
                 LEFT JOIN escuelas e ON e.CCT = er.CCT
                 LEFT JOIN (
                     SELECT RPU, COUNT(*) total_rpu
                     FROM escuelas_rpu
                     GROUP BY RPU
                 ) conteo ON conteo.RPU = er.RPU
                 $where
                 ORDER BY er.RPU, er.CCT"
            );
            $consulta->execute($parametros);
            $archivo = 'vinculos_escuelas_rpu_' . date('Ymd_His') . '.csv';
            http_response_code(200);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $archivo . '"');
            echo "\xEF\xBB\xBF";
            $salida = fopen('php://output', 'wb');
            fputcsv($salida, [
                'RPU',
                'CCT',
                'NOMBRE_ESCUELA_OFICIAL',
                'DOMICILIO_ESCUELA_OFICIAL',
                'MUNICIPIO_ESCUELA',
                'LOCALIDAD_ESCUELA',
                'STATUS',
                'NIVEL_ESCOLAR',
                'TOTAL_ESCUELAS_POR_RPU',
                'NOMBRE_RECIBO_CFE',
                'POBLACION_CFE',
                'TARIFA_CFE'
            ]);
            foreach ($consulta->fetchAll() as $fila) {
                fputcsv($salida, [
                    $fila['RPU'] ?? '',
                    $fila['CCT'] ?? '',
                    $fila['NOMBRECT'] ?? '',
                    $fila['DOMICILIO'] ?? '',
                    $fila['NOMBREMUN'] ?? '',
                    $fila['NOMBRELOC'] ?? '',
                    $fila['STATUS'] ?? '',
                    $fila['SUBNIVEL'] ?? '',
                    $fila['total_rpu'] ?? '',
                    $fila['nombre_recibo_cfe'] ?? '',
                    $fila['poblacion_cfe'] ?? '',
                    $fila['tarifa_cfe'] ?? ''
                ]);
            }
            fclose($salida);
            exit;
        } catch (Throwable $e) {
            $this->responder(['ok' => false, 'error' => 'Fallo al exportar vinculos: ' . $e->getMessage()], 500);
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

    private function prepararTablaEscuelas(PDO $conexion): void
    {
        $columnas = [
            'DOMICILIO' => 'VARCHAR(255) NULL',
            'NIVEL' => 'VARCHAR(100) NULL',
            'HOMO' => 'VARCHAR(30) NULL',
            'TURNO' => 'VARCHAR(100) NULL',
            'ZONA' => 'VARCHAR(50) NULL',
            'SECTOR' => 'VARCHAR(50) NULL',
            'ORIGEN' => 'VARCHAR(80) NULL',
            'TIPOCT' => 'VARCHAR(255) NULL',
            'TURNO_CV' => 'VARCHAR(255) NULL',
            'TURNO2' => 'VARCHAR(255) NULL',
            'TURNO2_DES' => 'VARCHAR(255) NULL',
            'STATUS_DES' => 'VARCHAR(255) NULL',
            'MPIO' => 'VARCHAR(255) NULL',
            'LOC' => 'VARCHAR(255) NULL',
            'AMBITO' => 'VARCHAR(255) NULL',
            'COLONIA' => 'VARCHAR(255) NULL',
            'NOMBRECOL' => 'VARCHAR(255) NULL',
            'ENTRECALLE' => 'VARCHAR(255) NULL',
            'YCALLE' => 'VARCHAR(255) NULL',
            'CALLEPOST' => 'VARCHAR(255) NULL',
            'CODPOST' => 'VARCHAR(255) NULL',
            'LATITUD' => 'VARCHAR(255) NULL',
            'LONGITUD' => 'VARCHAR(255) NULL',
            'CV_INMUEBLE' => 'VARCHAR(255) NULL',
            'MARGINACION' => 'VARCHAR(255) NULL',
            'CCT_ZONA' => 'VARCHAR(255) NULL',
            'CCT_SECTOR' => 'VARCHAR(255) NULL',
            'SERREG' => 'VARCHAR(255) NULL',
            'CCT_SERREG' => 'VARCHAR(255) NULL',
            'TIPO' => 'VARCHAR(255) NULL',
            'SERVICIO' => 'VARCHAR(255) NULL',
            'SERVICIO_DES' => 'VARCHAR(255) NULL',
            'CV_CARACT' => 'VARCHAR(255) NULL',
            'CARACTERISTICA' => 'VARCHAR(255) NULL',
            'SOST_CONTROL' => 'VARCHAR(255) NULL',
            'SOSTENIMIENTO' => 'VARCHAR(255) NULL',
            'SOSTENIMIENTO_DES' => 'VARCHAR(255) NULL',
            'NOM_DIR' => 'VARCHAR(255) NULL',
            'APELLIDO1' => 'VARCHAR(255) NULL',
            'APELLIDO2' => 'VARCHAR(255) NULL',
            'CURP' => 'VARCHAR(255) NULL',
            'RFC' => 'VARCHAR(255) NULL',
            'TELEFONO1' => 'VARCHAR(255) NULL',
            'CELULAR1' => 'VARCHAR(255) NULL',
            'CORREOELE' => 'VARCHAR(255) NULL',
            'PAGINAWEB' => 'VARCHAR(255) NULL',
            'ADM_DES' => 'VARCHAR(255) NULL',
            'NOR_DES' => 'VARCHAR(255) NULL',
            'OPERAT_DES' => 'VARCHAR(255) NULL',
            'FECHAFUNDA' => 'VARCHAR(255) NULL',
            'FECHAALTA' => 'VARCHAR(255) NULL',
            'FECHACLAUS' => 'VARCHAR(255) NULL',
            'FECHAREAPE' => 'VARCHAR(255) NULL',
            'FECHAACTUA' => 'VARCHAR(255) NULL',
            'CLAVE_ALTERNA' => 'VARCHAR(255) NULL',
            'CV_TURNO' => 'VARCHAR(255) NULL',
            'CV_MUN' => 'VARCHAR(255) NULL',
            'CV_LOC' => 'VARCHAR(255) NULL',
            'C_NOM_VIALIDAD' => 'VARCHAR(255) NULL',
            'N_EXTNUM' => 'VARCHAR(255) NULL',
            'CONTROL' => 'VARCHAR(255) NULL',
            'SUBCONTROL' => 'VARCHAR(255) NULL',
            'C_CARACTERIZAN2' => 'VARCHAR(255) NULL',
            'JEFSEC' => 'VARCHAR(255) NULL',
            'SERVREG' => 'VARCHAR(255) NULL',
            'REGION' => 'VARCHAR(255) NULL',
            'CV_ESTATUS_CAPTURA' => 'VARCHAR(255) NULL',
            'HOMBRE' => 'VARCHAR(255) NULL',
            'MUJER' => 'VARCHAR(255) NULL',
            'TOTAL' => 'VARCHAR(255) NULL',
            'GRUPOS' => 'VARCHAR(255) NULL',
            'LENGUA' => 'VARCHAR(255) NULL',
            'DATOS_SEG_JSON' => 'LONGTEXT NULL',
            'DATOS_OFICIALIZACION_JSON' => 'LONGTEXT NULL'
        ];
        $existentes = [];
        $consulta = $conexion->query("SHOW COLUMNS FROM escuelas");
        foreach ($consulta->fetchAll() as $fila) {
            $existentes[strtoupper((string) $fila['Field'])] = true;
        }
        foreach ($columnas as $columna => $definicion) {
            if (!isset($existentes[$columna])) {
                $conexion->exec('ALTER TABLE escuelas ADD COLUMN `' . $columna . '` ' . $definicion);
            }
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

if ($accion === 'exportar_vinculos') {
    $controlador->exportarVinculos();
}

http_response_code(400);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => false, 'error' => 'Accion no reconocida.'], JSON_UNESCAPED_UNICODE);
