<?php

declare(strict_types=1);

session_start();

require_once dirname(__DIR__) . '/services/conexion.php';

class AjustesController
{
    public function importarReportesMasivos(): void
    {
        set_time_limit(0);
        $this->validarToken();
        $archivos = $_FILES['reportes_cfe'] ?? null;
        if (!is_array($archivos) || !is_array($archivos['name'] ?? null) || !$archivos['name']) {
            $this->responder(['ok' => false, 'error' => 'Selecciona uno o mas reportes CFE.'], 422);
        }

        $conexion = Conexion::conectar();
        $this->prepararHistorialCfe($conexion);
        $python = $this->localizarPython();
        $script = dirname(__DIR__) . '/services/detectar_ajustes_cfe.py';
        $procesados = [];
        $errores = [];
        $totalRegistros = 0;

        foreach ($archivos['name'] as $indice => $nombreOriginal) {
            if (($archivos['error'][$indice] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $errores[] = ['archivo' => $nombreOriginal, 'error' => 'No fue posible recibir el archivo.'];
                continue;
            }
            $extension = strtolower(pathinfo((string) $nombreOriginal, PATHINFO_EXTENSION));
            if (!in_array($extension, ['xlsx', 'xls'], true)) {
                $errores[] = ['archivo' => $nombreOriginal, 'error' => 'Solo se admiten archivos XLSX o XLS.'];
                continue;
            }
            if (!preg_match('/(20\d{2})[-_](0[1-9]|1[0-2])/', (string) $nombreOriginal, $periodo)) {
                $errores[] = ['archivo' => $nombreOriginal, 'error' => 'El nombre debe incluir el periodo AAAA-MM.'];
                continue;
            }
            $ruta = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cfe_masivo_' . bin2hex(random_bytes(12)) . '.' . $extension;
            try {
                if (!move_uploaded_file($archivos['tmp_name'][$indice], $ruta)) {
                    throw new RuntimeException('No fue posible preparar el archivo.');
                }
                $anio = (int) $periodo[1];
                $mes = (int) $periodo[2];
                $comando = escapeshellarg($python)
                    . ' ' . escapeshellarg($script)
                    . ' ' . escapeshellarg($ruta)
                    . ' ' . escapeshellarg((string) $anio)
                    . ' ' . escapeshellarg((string) $mes)
                    . ' automatico 2>&1';
                $salida = shell_exec($comando);
                $lineas = is_string($salida) ? array_values(array_filter(array_map('trim', preg_split('/\R/', $salida)))) : [];
                $resultado = json_decode($lineas ? end($lineas) : '', true);
                if (!is_array($resultado) || empty($resultado['ok'])) {
                    throw new RuntimeException((string) ($resultado['error'] ?? 'El analizador no devolvio una respuesta valida.'));
                }
                $resultado['archivo'] = (string) $nombreOriginal;
                $conexion->prepare('DELETE FROM cfe_reportes WHERE anio = ? AND mes = ?')->execute([$anio, $mes]);
                $guardado = $this->guardarHistorial($conexion, $resultado, $anio, $mes, 'automatico');
                $this->anexarSugerencias($conexion, $guardado);
                $registros = (int) ($guardado['historial_guardado'] ?? 0);
                $totalRegistros += $registros;
                $procesados[] = ['archivo' => $nombreOriginal, 'periodo' => sprintf('%04d-%02d', $anio, $mes), 'registros' => $registros];
            } catch (Throwable $e) {
                $errores[] = ['archivo' => $nombreOriginal, 'error' => $e->getMessage()];
            } finally {
                if (is_file($ruta)) {
                    unlink($ruta);
                }
            }
        }

        $this->responder([
            'ok' => $procesados !== [],
            'reportes' => count($procesados),
            'registros' => $totalRegistros,
            'procesados' => $procesados,
            'errores' => $errores
        ], $procesados !== [] ? 200 : 422);
    }

    public function analizar(): void
    {
        $this->validarToken();
        if (!isset($_FILES['reporte_cfe']) || $_FILES['reporte_cfe']['error'] !== UPLOAD_ERR_OK) {
            $this->responder(['ok' => false, 'error' => 'Carga un reporte CFE en Excel.'], 422);
        }

        $extension = strtolower(pathinfo($_FILES['reporte_cfe']['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['xlsx', 'xls'], true)) {
            $this->responder(['ok' => false, 'error' => 'Solo se admiten reportes Excel XLSX o XLS.'], 422);
        }
        $anio = (int) ($_POST['anio_reporte'] ?? 0);
        $mes = (int) ($_POST['mes_reporte'] ?? 0);
        $modoPeriodo = (string) ($_POST['modo_periodo'] ?? 'automatico');

        if ($anio < 2020 || $anio > 2100 || $mes < 1 || $mes > 12) {
            $this->responder(['ok' => false, 'error' => 'Selecciona mes y anio validos del reporte.'], 422);
        }

        if (!in_array($modoPeriodo, ['automatico', 'mensual', 'bimestral'], true)) {
            $this->responder(['ok' => false, 'error' => 'Selecciona un modo de periodo valido.'], 422);
        }

        $ruta = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ajustes_cfe_' . bin2hex(random_bytes(16)) . '.' . $extension;

        try {
            if (!move_uploaded_file($_FILES['reporte_cfe']['tmp_name'], $ruta)) {
                throw new RuntimeException('No fue posible almacenar temporalmente el reporte.');
            }

            $python = $this->localizarPython();
            $script = dirname(__DIR__) . '/services/detectar_ajustes_cfe.py';
            $comando = escapeshellarg($python)
                . ' ' . escapeshellarg($script)
                . ' ' . escapeshellarg($ruta)
                . ' ' . escapeshellarg((string) $anio)
                . ' ' . escapeshellarg((string) $mes)
                . ' ' . escapeshellarg($modoPeriodo)
                . ' 2>&1';
            $salida = shell_exec($comando);
            $lineas = is_string($salida)
                ? array_values(array_filter(array_map('trim', preg_split('/\R/', $salida))))
                : [];
            $json = $lineas ? end($lineas) : '';
            $resultado = json_decode($json, true);

            if (!is_array($resultado)) {
                $detalle = trim(strip_tags((string) $salida));
                throw new RuntimeException($detalle !== '' ? mb_substr($detalle, 0, 500) : 'No se encontro una respuesta valida del analizador.');
            }

            if (!empty($resultado['ok'])) {
                $conexion = Conexion::conectar();
                $this->prepararHistorialCfe($conexion);
                $resultado = $this->guardarHistorial($conexion, $resultado, $anio, $mes, $modoPeriodo);
                $resultado = $this->anexarSugerencias($conexion, $resultado);
            }

            $this->responder($resultado, !empty($resultado['ok']) ? 200 : 422);
        } catch (Throwable $e) {
            $this->responder(['ok' => false, 'error' => 'Fallo al analizar ajustes: ' . $e->getMessage()], 500);
        } finally {
            if (is_file($ruta)) {
                unlink($ruta);
            }
        }
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

    public function consultarReporteGuardado(): void
    {
        $this->validarToken();
        $reporteId = (int) ($_POST['reporte_id'] ?? 0);
        if ($reporteId <= 0) {
            $this->responder(['ok' => false, 'error' => 'Selecciona un reporte CFE guardado.'], 422);
        }

        try {
            $conexion = Conexion::conectar();
            $this->prepararHistorialCfe($conexion);
            $consultaReporte = $conexion->prepare(
                'SELECT id, archivo, anio, mes, modo_periodo FROM cfe_reportes WHERE id = ? LIMIT 1'
            );
            $consultaReporte->execute([$reporteId]);
            $reporte = $consultaReporte->fetch();
            if (!$reporte) {
                $this->responder(['ok' => false, 'error' => 'El reporte seleccionado ya no existe.'], 404);
            }

            $consultaConsumos = $conexion->prepare(
                'SELECT RPU, division_cfe, nombre_cfe, direccion_cfe, poblacion_cfe, tarifa_cfe, tipo_periodo,
                        desde, hasta, dias, consumo, demanda, reactivos, factor_potencia, factor_carga,
                        energia, iva, dap, cargos_depositos, creditos_redondeos, total, formula_validacion,
                        diferencia, severidad, alertas
                 FROM cfe_consumos
                 WHERE reporte_id = ?
                 ORDER BY severidad DESC, total DESC, RPU'
            );
            $consultaConsumos->execute([$reporteId]);
            $registros = [];
            foreach ($consultaConsumos->fetchAll() as $fila) {
                $alertas = array_values(array_filter(array_map('trim', explode('|', (string) ($fila['alertas'] ?? '')))));
                $registros[] = [
                    'rpu' => (string) $fila['RPU'],
                    'division' => (string) ($fila['division_cfe'] ?? ''),
                    'nombre' => (string) ($fila['nombre_cfe'] ?? ''),
                    'direccion' => (string) ($fila['direccion_cfe'] ?? ''),
                    'poblacion' => (string) ($fila['poblacion_cfe'] ?? ''),
                    'tarifa' => (string) ($fila['tarifa_cfe'] ?? ''),
                    'tipo_periodo' => (string) ($fila['tipo_periodo'] ?? ''),
                    'desde' => (string) ($fila['desde'] ?? ''),
                    'hasta' => (string) ($fila['hasta'] ?? ''),
                    'dias' => $fila['dias'] !== null ? (int) $fila['dias'] : null,
                    'consumo' => (float) $fila['consumo'],
                    'demanda' => (float) $fila['demanda'],
                    'reactivos' => (float) $fila['reactivos'],
                    'factor_potencia' => (float) $fila['factor_potencia'],
                    'factor_carga' => (float) $fila['factor_carga'],
                    'energia' => (float) $fila['energia'],
                    'iva' => (float) $fila['iva'],
                    'dap' => (float) $fila['dap'],
                    'cargos_depositos' => (float) $fila['cargos_depositos'],
                    'creditos_redondeos' => (float) $fila['creditos_redondeos'],
                    'total' => (float) $fila['total'],
                    'formula_validacion' => (float) $fila['formula_validacion'],
                    'diferencia' => (float) $fila['diferencia'],
                    'severidad' => (int) $fila['severidad'],
                    'alertas' => $alertas
                ];
            }

            $periodoCorrecto = 0;
            $conAlerta = 0;
            $severos = 0;
            $importeTotal = 0.0;
            foreach ($registros as $registro) {
                $tipo = $registro['tipo_periodo'] === 'mensual' ? 'mensual' : 'bimestral';
                $minimo = $tipo === 'mensual' ? 25 : 50;
                $maximo = $tipo === 'mensual' ? 35 : 75;
                $dias = $registro['dias'];
                if ($dias !== null && $dias >= $minimo && $dias <= $maximo) {
                    $periodoCorrecto++;
                }
                if ($registro['alertas']) {
                    $conAlerta++;
                }
                if ($registro['severidad'] >= 7) {
                    $severos++;
                }
                $importeTotal += $registro['total'];
            }

            $resultado = [
                'ok' => true,
                'reporte_id' => $reporteId,
                'archivo' => (string) $reporte['archivo'],
                'mes_reporte' => sprintf('%04d-%02d', (int) $reporte['anio'], (int) $reporte['mes']),
                'modo_periodo' => (string) $reporte['modo_periodo'],
                'resumen' => [
                    'registros' => count($registros),
                    'con_alerta' => $conAlerta,
                    'severos' => $severos,
                    'periodo_bimestral' => $periodoCorrecto,
                    'importe_total' => round($importeTotal, 2)
                ],
                'registros' => $registros
            ];
            $this->responder($this->anexarSugerencias($conexion, $resultado));
        } catch (Throwable $e) {
            $this->responder(['ok' => false, 'error' => 'No fue posible consultar el reporte guardado: ' . $e->getMessage()], 500);
        }
    }

    private function prepararHistorialCfe(PDO $conexion): void
    {
        $conexion->exec(
            "CREATE TABLE IF NOT EXISTS cfe_reportes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                archivo VARCHAR(255) NOT NULL,
                anio INT NOT NULL,
                mes INT NOT NULL,
                modo_periodo VARCHAR(20) NOT NULL,
                total_registros INT NOT NULL DEFAULT 0,
                con_alerta INT NOT NULL DEFAULT 0,
                severos INT NOT NULL DEFAULT 0,
                periodo_correcto INT NOT NULL DEFAULT 0,
                ajuste_muchos_dias INT NOT NULL DEFAULT 0,
                periodo_correcto_con_aumento INT NOT NULL DEFAULT 0,
                sin_alerta_con_aumento INT NOT NULL DEFAULT 0,
                importe_total DECIMAL(14,2) NOT NULL DEFAULT 0,
                creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_cfe_reportes_periodo (anio, mes)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $conexion->exec(
            "CREATE TABLE IF NOT EXISTS cfe_consumos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                reporte_id INT NOT NULL,
                RPU VARCHAR(20) NOT NULL,
                CCT VARCHAR(50) NULL,
                division_cfe VARCHAR(80) NULL,
                nombre_cfe VARCHAR(255) NULL,
                direccion_cfe VARCHAR(255) NULL,
                poblacion_cfe VARCHAR(255) NULL,
                tarifa_cfe VARCHAR(10) NULL,
                tipo_periodo VARCHAR(20) NULL,
                desde DATE NULL,
                hasta DATE NULL,
                dias INT NULL,
                consumo DECIMAL(14,2) NOT NULL DEFAULT 0,
                demanda DECIMAL(14,2) NOT NULL DEFAULT 0,
                reactivos DECIMAL(14,2) NOT NULL DEFAULT 0,
                factor_potencia DECIMAL(14,4) NOT NULL DEFAULT 0,
                factor_carga DECIMAL(14,4) NOT NULL DEFAULT 0,
                energia DECIMAL(14,2) NOT NULL DEFAULT 0,
                iva DECIMAL(14,2) NOT NULL DEFAULT 0,
                dap DECIMAL(14,2) NOT NULL DEFAULT 0,
                cargos_depositos DECIMAL(14,2) NOT NULL DEFAULT 0,
                creditos_redondeos DECIMAL(14,2) NOT NULL DEFAULT 0,
                total DECIMAL(14,2) NOT NULL DEFAULT 0,
                formula_validacion DECIMAL(14,2) NOT NULL DEFAULT 0,
                diferencia DECIMAL(14,2) NOT NULL DEFAULT 0,
                severidad INT NOT NULL DEFAULT 0,
                alertas TEXT NULL,
                INDEX idx_cfe_consumos_rpu (RPU),
                INDEX idx_cfe_consumos_cct (CCT),
                INDEX idx_cfe_consumos_reporte (reporte_id),
                FOREIGN KEY (reporte_id) REFERENCES cfe_reportes(id) ON DELETE CASCADE,
                FOREIGN KEY (CCT) REFERENCES escuelas(CCT) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $this->asegurarColumna($conexion, 'cfe_consumos', 'division_cfe', 'VARCHAR(80) NULL');
        $this->asegurarColumna($conexion, 'cfe_consumos', 'direccion_cfe', 'VARCHAR(255) NULL');
        $this->asegurarColumna($conexion, 'cfe_consumos', 'demanda', 'DECIMAL(14,2) NOT NULL DEFAULT 0');
        $this->asegurarColumna($conexion, 'cfe_consumos', 'reactivos', 'DECIMAL(14,2) NOT NULL DEFAULT 0');
        $this->asegurarColumna($conexion, 'cfe_consumos', 'factor_potencia', 'DECIMAL(14,4) NOT NULL DEFAULT 0');
        $this->asegurarColumna($conexion, 'cfe_consumos', 'factor_carga', 'DECIMAL(14,4) NOT NULL DEFAULT 0');
        $this->asegurarColumna($conexion, 'cfe_consumos', 'iva', 'DECIMAL(14,2) NOT NULL DEFAULT 0');
        $this->asegurarColumna($conexion, 'cfe_consumos', 'formula_validacion', 'DECIMAL(14,2) NOT NULL DEFAULT 0');
        $this->asegurarColumna($conexion, 'cfe_reportes', 'ajuste_muchos_dias', 'INT NOT NULL DEFAULT 0');
        $this->asegurarColumna($conexion, 'cfe_reportes', 'periodo_correcto_con_aumento', 'INT NOT NULL DEFAULT 0');
        $this->asegurarColumna($conexion, 'cfe_reportes', 'sin_alerta_con_aumento', 'INT NOT NULL DEFAULT 0');
    }

    private function asegurarColumna(PDO $conexion, string $tabla, string $columna, string $definicion): void
    {
        $consulta = $conexion->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $consulta->execute([$tabla, $columna]);
        if ((int) $consulta->fetchColumn() === 0) {
            $conexion->exec('ALTER TABLE `' . $tabla . '` ADD COLUMN `' . $columna . '` ' . $definicion);
        }
    }

    public function exportarExcelDirectores(): void
    {
        $this->validarToken();
        try {
            $conexion = Conexion::conectar();
            $this->prepararHistorialCfe($conexion);
            $tipo = (string) ($_POST['exportar_tipo'] ?? 'ajustes_mes');
            $anio = (int) ($_POST['anio_exportacion'] ?? 0);
            $mes = (int) ($_POST['mes_exportacion'] ?? 0);
            $modo = 'problemas';
            $titulo = 'REPORTE ENTENDIBLE DE PROBLEMAS CFE - ULTIMOS 3 REPORTES';

            if ($tipo === 'ajustes_mes' || $tipo === 'bajo_consumo_mes') {
                if ($anio < 2020 || $anio > 2100 || $mes < 1 || $mes > 12) {
                    $this->responder(['ok' => false, 'error' => 'Selecciona mes y anio validos para exportar.'], 422);
                }
                $consultaReportes = $conexion->prepare(
                    'SELECT id, archivo, anio, mes, total_registros, con_alerta, severos, periodo_correcto, ajuste_muchos_dias, periodo_correcto_con_aumento, sin_alerta_con_aumento, importe_total
                     FROM cfe_reportes
                     WHERE anio = ? AND mes = ?
                     ORDER BY id DESC'
                );
                $consultaReportes->execute([$anio, $mes]);
                $reportes = $consultaReportes->fetchAll();
                $titulo = $tipo === 'bajo_consumo_mes'
                    ? 'REPORTE DE ESCUELAS CON CONSUMO MUY BAJO POR MES'
                    : 'REPORTE DE AJUSTES CFE POR MES';
                $modo = $tipo === 'bajo_consumo_mes' ? 'bajo_consumo' : 'problemas';
            } else {
                $reportes = $conexion->query(
                    'SELECT id, archivo, anio, mes, total_registros, con_alerta, severos, periodo_correcto, ajuste_muchos_dias, periodo_correcto_con_aumento, sin_alerta_con_aumento, importe_total
                     FROM cfe_reportes
                     ORDER BY anio DESC, mes DESC, id DESC
                     LIMIT 3'
                )->fetchAll();
            }

            if (!$reportes) {
                $periodosDisponibles = $this->periodosCfeDisponibles($conexion);
                $mensaje = $tipo === 'ajustes_mes' || $tipo === 'bajo_consumo_mes'
                    ? 'No hay reportes CFE guardados para ' . sprintf('%04d-%02d', $anio, $mes) . '.'
                    : 'Aun no hay reportes CFE guardados para exportar.';
                if ($periodosDisponibles !== '') {
                    $mensaje .= ' Meses disponibles: ' . $periodosDisponibles . '.';
                }
                $this->responder(['ok' => false, 'error' => $mensaje], 422);
            }

            $casos = $this->obtenerCasosExcelDirectores($conexion, array_map(static fn (array $reporte): int => (int) $reporte['id'], $reportes), $modo);
            $csv = $this->construirCsvDirectores($reportes, $casos, $modo);

            http_response_code(200);
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="reporte_cfe_' . preg_replace('/[^a-z0-9_]+/i', '_', $tipo) . '_' . date('Ymd_His') . '.csv"');
            echo "\xEF\xBB\xBF" . $csv;
            exit;
        } catch (Throwable $e) {
            $this->responder(['ok' => false, 'error' => 'Fallo al exportar CSV: ' . $e->getMessage()], 500);
        }
    }

    private function guardarHistorial(PDO $conexion, array $resultado, int $anio, int $mes, string $modoPeriodo): array
    {
        $resumen = $resultado['resumen'] ?? [];
        $registros = is_array($resultado['registros'] ?? null) ? $resultado['registros'] : [];
        $conexion->beginTransaction();
        try {
            $consultaReporte = $conexion->prepare(
                'INSERT INTO cfe_reportes (archivo, anio, mes, modo_periodo, total_registros, con_alerta, severos, periodo_correcto, importe_total)
                 VALUES (:archivo, :anio, :mes, :modo, :total_registros, :con_alerta, :severos, :periodo_correcto, :importe_total)'
            );
            $consultaReporte->execute([
                'archivo' => (string) ($resultado['archivo'] ?? 'reporte_cfe.xlsx'),
                'anio' => $anio,
                'mes' => $mes,
                'modo' => $modoPeriodo,
                'total_registros' => (int) ($resumen['registros'] ?? count($registros)),
                'con_alerta' => (int) ($resumen['con_alerta'] ?? 0),
                'severos' => (int) ($resumen['severos'] ?? 0),
                'periodo_correcto' => (int) ($resumen['periodo_bimestral'] ?? 0),
                'importe_total' => (float) ($resumen['importe_total'] ?? 0)
            ]);
            $reporteId = (int) $conexion->lastInsertId();
            $vinculos = $this->obtenerVinculosPorRpu($conexion, array_column($registros, 'rpu'));
            $consultaConsumo = $conexion->prepare(
                'INSERT INTO cfe_consumos (reporte_id, RPU, CCT, division_cfe, nombre_cfe, direccion_cfe, poblacion_cfe, tarifa_cfe, tipo_periodo, desde, hasta, dias, consumo, demanda, reactivos, factor_potencia, factor_carga, energia, iva, dap, cargos_depositos, creditos_redondeos, total, formula_validacion, diferencia, severidad, alertas)
                 VALUES (:reporte_id, :rpu, :cct, :division, :nombre, :direccion, :poblacion, :tarifa, :tipo_periodo, :desde, :hasta, :dias, :consumo, :demanda, :reactivos, :factor_potencia, :factor_carga, :energia, :iva, :dap, :cargos, :creditos, :total, :formula_validacion, :diferencia, :severidad, :alertas)'
            );
            foreach ($registros as $registro) {
                if (!is_array($registro)) {
                    continue;
                }
                $rpu = trim((string) ($registro['rpu'] ?? ''));
                if ($rpu === '') {
                    continue;
                }
                $consultaConsumo->execute([
                    'reporte_id' => $reporteId,
                    'rpu' => $rpu,
                    'cct' => $vinculos[$rpu][0]['cct'] ?? null,
                    'division' => $this->textoONulo($registro['division'] ?? null),
                    'nombre' => $this->textoONulo($registro['nombre'] ?? null),
                    'direccion' => $this->textoONulo($registro['direccion'] ?? null),
                    'poblacion' => $this->textoONulo($registro['poblacion'] ?? null),
                    'tarifa' => $this->textoONulo($registro['tarifa'] ?? null),
                    'tipo_periodo' => $this->textoONulo($registro['tipo_periodo'] ?? null),
                    'desde' => $this->fechaONulo($registro['desde'] ?? null),
                    'hasta' => $this->fechaONulo($registro['hasta'] ?? null),
                    'dias' => is_numeric($registro['dias'] ?? null) ? (int) $registro['dias'] : null,
                    'consumo' => (float) ($registro['consumo'] ?? 0),
                    'demanda' => (float) ($registro['demanda'] ?? 0),
                    'reactivos' => (float) ($registro['reactivos'] ?? 0),
                    'factor_potencia' => (float) ($registro['factor_potencia'] ?? 0),
                    'factor_carga' => (float) ($registro['factor_carga'] ?? 0),
                    'energia' => (float) ($registro['energia'] ?? 0),
                    'iva' => (float) ($registro['iva'] ?? 0),
                    'dap' => (float) ($registro['dap'] ?? 0),
                    'cargos' => (float) ($registro['cargos_depositos'] ?? 0),
                    'creditos' => (float) ($registro['creditos_redondeos'] ?? 0),
                    'total' => (float) ($registro['total'] ?? 0),
                    'formula_validacion' => (float) ($registro['formula_validacion'] ?? 0),
                    'diferencia' => (float) ($registro['diferencia'] ?? 0),
                    'severidad' => (int) ($registro['severidad'] ?? 0),
                    'alertas' => implode(' | ', array_map('strval', is_array($registro['alertas'] ?? null) ? $registro['alertas'] : []))
                ]);
            }
            $conexion->commit();
            $resultado['reporte_id'] = $reporteId;
            $resultado['historial_guardado'] = count($registros);
            return $resultado;
        } catch (Throwable $e) {
            if ($conexion->inTransaction()) {
                $conexion->rollBack();
            }
            throw $e;
        }
    }

    private function anexarSugerencias(PDO $conexion, array $resultado): array
    {
        $registros = is_array($resultado['registros'] ?? null) ? $resultado['registros'] : [];
        $vinculos = $this->obtenerVinculosPorRpu($conexion, array_column($registros, 'rpu'));
        $historicas = $this->obtenerSugerenciasHistoricas($conexion, array_column($registros, 'rpu'));
        $tendencias = $this->obtenerTendencias($conexion, array_column($registros, 'rpu'), (int) ($resultado['reporte_id'] ?? 0));
        $conVinculo = 0;
        $conSugerencia = 0;
        $mejorando = 0;
        foreach ($registros as &$registro) {
            if (!is_array($registro)) {
                continue;
            }
            $rpu = (string) ($registro['rpu'] ?? '');
            $registro['escuelas_vinculadas'] = $vinculos[$rpu] ?? [];
            $registro['sugerencias_escuela'] = $historicas[$rpu] ?? [];
            $registro['tendencia'] = $tendencias[$rpu] ?? null;
            if (is_array($registro['tendencia']) && (float) ($registro['tendencia']['diferencia_total'] ?? 0) < 0) {
                $mejorando++;
            }
            if ($registro['escuelas_vinculadas']) {
                $conVinculo++;
            } elseif ($registro['sugerencias_escuela']) {
                $conSugerencia++;
            }
        }
        unset($registro);
        $resultado['registros'] = $registros;
        $resultado['resumen']['rpu_con_vinculo'] = $conVinculo;
        $resultado['resumen']['rpu_sugeridos_por_historial'] = $conSugerencia;
        $resultado['resumen']['rpu_mejorando'] = $mejorando;
        $resultado = $this->anexarResumenReporte($conexion, $resultado);
        return $resultado;
    }

    private function anexarResumenReporte(PDO $conexion, array $resultado): array
    {
        $registros = is_array($resultado['registros'] ?? null) ? $resultado['registros'] : [];
        $muchosDias = 0;
        $periodoCorrectoConAumento = 0;
        $sinAlertaConAumento = 0;
        foreach ($registros as $registro) {
            if (!is_array($registro)) {
                continue;
            }
            $dias = is_numeric($registro['dias'] ?? null) ? (int) $registro['dias'] : null;
            $tipo = (string) ($registro['tipo_periodo'] ?? '');
            $maximo = $tipo === 'mensual' ? 35 : 75;
            $minimo = $tipo === 'mensual' ? 25 : 50;
            $periodoCorrecto = $dias !== null && $dias >= $minimo && $dias <= $maximo;
            $subio = is_array($registro['tendencia'] ?? null) && (float) ($registro['tendencia']['diferencia_total'] ?? 0) > 0;
            $alertas = is_array($registro['alertas'] ?? null) ? $registro['alertas'] : [];
            if ($dias !== null && $dias > $maximo) {
                $muchosDias++;
            }
            if ($periodoCorrecto && $subio) {
                $periodoCorrectoConAumento++;
            }
            if (!$alertas && $subio) {
                $sinAlertaConAumento++;
            }
        }
        $resultado['resumen']['ajuste_muchos_dias'] = $muchosDias;
        $resultado['resumen']['periodo_correcto_con_aumento'] = $periodoCorrectoConAumento;
        $resultado['resumen']['sin_alerta_con_aumento'] = $sinAlertaConAumento;
        $reporteId = (int) ($resultado['reporte_id'] ?? 0);
        if ($reporteId > 0) {
            $consulta = $conexion->prepare(
                'UPDATE cfe_reportes SET ajuste_muchos_dias = :muchos_dias, periodo_correcto_con_aumento = :periodo_correcto_aumento, sin_alerta_con_aumento = :sin_alerta_aumento WHERE id = :id'
            );
            $consulta->execute([
                'muchos_dias' => $muchosDias,
                'periodo_correcto_aumento' => $periodoCorrectoConAumento,
                'sin_alerta_aumento' => $sinAlertaConAumento,
                'id' => $reporteId
            ]);
        }
        return $resultado;
    }

    private function obtenerVinculosPorRpu(PDO $conexion, array $rpus): array
    {
        $rpus = array_values(array_unique(array_filter(array_map(static fn ($rpu): string => trim((string) $rpu), $rpus))));
        if (!$rpus) {
            return [];
        }
        $marcadores = implode(',', array_fill(0, count($rpus), '?'));
        $consulta = $conexion->prepare(
            "SELECT er.RPU, er.CCT, e.NOMBRECT, e.NOMBREMUN, e.NOMBRELOC, e.SUBNIVEL
             FROM escuelas_rpu er
             LEFT JOIN escuelas e ON e.CCT = er.CCT
             WHERE er.RPU IN ($marcadores)
             ORDER BY er.RPU, er.id"
        );
        $consulta->execute($rpus);
        $vinculos = [];
        foreach ($consulta->fetchAll() as $fila) {
            $vinculos[(string) $fila['RPU']][] = [
                'cct' => (string) $fila['CCT'],
                'nombre' => $fila['NOMBRECT'] ?? '',
                'municipio' => $fila['NOMBREMUN'] ?? '',
                'localidad' => $fila['NOMBRELOC'] ?? '',
                'subnivel' => $fila['SUBNIVEL'] ?? ''
            ];
        }
        return $vinculos;
    }

    private function obtenerSugerenciasHistoricas(PDO $conexion, array $rpus): array
    {
        $rpus = array_values(array_unique(array_filter(array_map(static fn ($rpu): string => trim((string) $rpu), $rpus))));
        if (!$rpus) {
            return [];
        }
        $marcadores = implode(',', array_fill(0, count($rpus), '?'));
        $consulta = $conexion->prepare(
            "SELECT cc.RPU, cc.CCT, e.NOMBRECT, e.NOMBREMUN, e.NOMBRELOC, e.SUBNIVEL, COUNT(*) apariciones, MAX(cc.hasta) ultimo_periodo
             FROM cfe_consumos cc
             INNER JOIN escuelas e ON e.CCT = cc.CCT
             WHERE cc.RPU IN ($marcadores)
             GROUP BY cc.RPU, cc.CCT, e.NOMBRECT, e.NOMBREMUN, e.NOMBRELOC, e.SUBNIVEL
             ORDER BY apariciones DESC, ultimo_periodo DESC"
        );
        $consulta->execute($rpus);
        $sugerencias = [];
        foreach ($consulta->fetchAll() as $fila) {
            $sugerencias[(string) $fila['RPU']][] = [
                'cct' => (string) $fila['CCT'],
                'nombre' => $fila['NOMBRECT'] ?? '',
                'municipio' => $fila['NOMBREMUN'] ?? '',
                'localidad' => $fila['NOMBRELOC'] ?? '',
                'subnivel' => $fila['SUBNIVEL'] ?? '',
                'apariciones' => (int) ($fila['apariciones'] ?? 0),
                'ultimo_periodo' => $fila['ultimo_periodo'] ?? ''
            ];
        }
        return $sugerencias;
    }

    private function obtenerTendencias(PDO $conexion, array $rpus, int $reporteId): array
    {
        $rpus = array_values(array_unique(array_filter(array_map(static fn ($rpu): string => trim((string) $rpu), $rpus))));
        if (!$rpus || $reporteId <= 0) {
            return [];
        }
        $marcadores = implode(',', array_fill(0, count($rpus), '?'));
        $consultaActual = $conexion->prepare("SELECT RPU, total, consumo FROM cfe_consumos WHERE reporte_id = ? AND RPU IN ($marcadores)");
        $consultaActual->execute(array_merge([$reporteId], $rpus));
        $actuales = [];
        foreach ($consultaActual->fetchAll() as $fila) {
            $actuales[(string) $fila['RPU']] = [
                'total' => (float) $fila['total'],
                'consumo' => (float) $fila['consumo']
            ];
        }
        $consultaAnterior = $conexion->prepare(
            "SELECT cc.RPU, cc.total, cc.consumo, cc.hasta, cr.anio, cr.mes
             FROM cfe_consumos cc
             INNER JOIN cfe_reportes cr ON cr.id = cc.reporte_id
             INNER JOIN (
                 SELECT RPU, MAX(id) ultimo_id
                 FROM cfe_consumos
                 WHERE reporte_id <> ? AND RPU IN ($marcadores)
                 GROUP BY RPU
             ) ultimos ON ultimos.ultimo_id = cc.id"
        );
        $consultaAnterior->execute(array_merge([$reporteId], $rpus));
        $tendencias = [];
        foreach ($consultaAnterior->fetchAll() as $fila) {
            $rpu = (string) $fila['RPU'];
            if (!isset($actuales[$rpu])) {
                continue;
            }
            $totalAnterior = (float) $fila['total'];
            $consumoAnterior = (float) $fila['consumo'];
            $tendencias[$rpu] = [
                'total_anterior' => $totalAnterior,
                'consumo_anterior' => $consumoAnterior,
                'diferencia_total' => $actuales[$rpu]['total'] - $totalAnterior,
                'diferencia_consumo' => $actuales[$rpu]['consumo'] - $consumoAnterior,
                'periodo_anterior' => sprintf('%04d-%02d', (int) $fila['anio'], (int) $fila['mes'])
            ];
        }
        return $tendencias;
    }

    private function obtenerCasosExcelDirectores(PDO $conexion, array $reportesIds, string $modo = 'problemas'): array
    {
        $reportesIds = array_values(array_filter(array_map('intval', $reportesIds)));
        if (!$reportesIds) {
            return [];
        }
        $marcadores = implode(',', array_fill(0, count($reportesIds), '?'));
        $consulta = $conexion->prepare(
            "SELECT cc.id, cc.reporte_id, cc.RPU, cc.CCT, cc.nombre_cfe, cc.poblacion_cfe, cc.tarifa_cfe, cc.tipo_periodo,
                    cc.desde, cc.hasta, cc.dias, cc.consumo, cc.total, cc.diferencia, cc.severidad, cc.alertas,
                    cr.anio, cr.mes,
                    (
                        SELECT GROUP_CONCAT(DISTINCT er2.CCT ORDER BY er2.CCT SEPARATOR ' / ')
                        FROM escuelas_rpu er2
                        WHERE er2.RPU = cc.RPU
                    ) ccts_vinculados,
                    (
                        SELECT GROUP_CONCAT(DISTINCT e2.NOMBRECT ORDER BY e2.NOMBRECT SEPARATOR ' / ')
                        FROM escuelas_rpu er2
                        LEFT JOIN escuelas e2 ON e2.CCT = er2.CCT
                        WHERE er2.RPU = cc.RPU
                    ) escuelas_vinculadas,
                    (
                        SELECT GROUP_CONCAT(DISTINCT e2.SUBNIVEL ORDER BY e2.SUBNIVEL SEPARATOR ' / ')
                        FROM escuelas_rpu er2
                        LEFT JOIN escuelas e2 ON e2.CCT = er2.CCT
                        WHERE er2.RPU = cc.RPU
                    ) niveles_vinculados,
                    (
                        SELECT prev.total
                        FROM cfe_consumos prev
                        INNER JOIN cfe_reportes pr ON pr.id = prev.reporte_id
                        WHERE prev.RPU = cc.RPU
                          AND (pr.anio < cr.anio OR (pr.anio = cr.anio AND pr.mes < cr.mes) OR (pr.anio = cr.anio AND pr.mes = cr.mes AND pr.id < cr.id))
                        ORDER BY pr.anio DESC, pr.mes DESC, pr.id DESC, prev.id DESC
                        LIMIT 1
                    ) total_anterior
             FROM cfe_consumos cc
             INNER JOIN cfe_reportes cr ON cr.id = cc.reporte_id
             WHERE cc.reporte_id IN ($marcadores)
             ORDER BY cr.anio DESC, cr.mes DESC, cr.id DESC, cc.total DESC"
        );
        $consulta->execute($reportesIds);
        $casos = [];
        foreach ($consulta->fetchAll() as $fila) {
            $caso = $this->prepararCasoExcelDirectores($fila, $modo);
            if ($caso !== null) {
                $casos[(int) $fila['reporte_id']][] = $caso;
            }
        }
        return $casos;
    }

    private function periodosCfeDisponibles(PDO $conexion): string
    {
        $consulta = $conexion->query('SELECT DISTINCT anio, mes FROM cfe_reportes ORDER BY anio DESC, mes DESC');
        $periodos = [];
        foreach ($consulta->fetchAll() as $fila) {
            $periodos[] = sprintf('%04d-%02d', (int) $fila['anio'], (int) $fila['mes']);
        }
        return implode(', ', $periodos);
    }

    private function prepararCasoExcelDirectores(array $fila, string $modo = 'problemas'): ?array
    {
        $dias = is_numeric($fila['dias'] ?? null) ? (int) $fila['dias'] : null;
        $tipoPeriodo = (string) ($fila['tipo_periodo'] ?? '');
        [$minimo, $maximo, $periodoEsperado] = $this->rangoPeriodoEsperado($tipoPeriodo, (int) ($fila['anio'] ?? 0), (int) ($fila['mes'] ?? 0));
        $periodoCorrecto = $dias !== null && $dias >= $minimo && $dias <= $maximo;
        $totalActual = (float) ($fila['total'] ?? 0);
        $totalAnterior = $fila['total_anterior'] !== null ? (float) $fila['total_anterior'] : null;
        $aumento = $totalAnterior !== null ? $totalActual - $totalAnterior : 0.0;
        $hasta = trim((string) ($fila['hasta'] ?? ''));
        $fechaHastaFuera = false;
        if (preg_match('/^(\d{4})-(\d{2})-\d{2}$/', $hasta, $coincidencia)) {
            $fechaHastaFuera = (int) $coincidencia[1] !== (int) ($fila['anio'] ?? 0) || (int) $coincidencia[2] !== (int) ($fila['mes'] ?? 0);
        }
        $periodoFueraRegla = $dias === null || !$periodoCorrecto || $fechaHastaFuera;
        $subioSinAjuste = $periodoCorrecto && $aumento > 0;
        $sinVinculo = trim((string) ($fila['ccts_vinculados'] ?? '')) === '';
        if ($modo === 'bajo_consumo') {
            $consumo = (float) ($fila['consumo'] ?? 0);
            if ($consumo > 50) {
                return null;
            }
            $seccion = 'bajo_consumo';
            $situacion = 'CONSUMO MUY BAJO';
            $mensaje = 'Consume 50 kWh o menos; revisar si la escuela esta operando o si tiene falta de servicio.';
            if ($sinVinculo) {
                $mensaje .= ' No tiene escuela vinculada; validarlo por RPU.';
            }

            return [
                'seccion' => $seccion,
                'situacion' => $situacion,
                'escuela' => trim((string) ($fila['escuelas_vinculadas'] ?? '')) !== '' ? (string) $fila['escuelas_vinculadas'] : 'RPU SIN ESCUELA VINCULADA',
                'cct' => (string) ($fila['ccts_vinculados'] ?? ''),
                'nivel' => (string) ($fila['niveles_vinculados'] ?? ''),
                'rpu' => (string) ($fila['RPU'] ?? ''),
                'recibo' => (string) ($fila['nombre_cfe'] ?? ''),
                'poblacion' => (string) ($fila['poblacion_cfe'] ?? ''),
                'tarifa' => (string) ($fila['tarifa_cfe'] ?? ''),
                'periodo' => trim((string) ($fila['desde'] ?? '') . ' / ' . (string) ($fila['hasta'] ?? '')),
                'periodo_esperado' => $periodoEsperado,
                'dias' => $dias,
                'consumo' => $consumo,
                'total' => $totalActual,
                'aumento' => $aumento,
                'mensaje' => $mensaje
            ];
        }

        if (!$periodoFueraRegla && !$subioSinAjuste) {
            return null;
        }

        if ($periodoFueraRegla) {
            $seccion = 'ajustes';
            $situacion = 'AJUSTE POR FECHAS';
            $mensaje = 'La tarifa ' . (string) ($fila['tarifa_cfe'] ?? '') . ' debe ser ' . strtolower($periodoEsperado) . ', pero el recibo trae ' . (string) ($fila['desde'] ?? '') . ' al ' . (string) ($fila['hasta'] ?? '') . ' (' . (string) ($dias ?? 'sin dias') . ' dias).';
            if ($fechaHastaFuera) {
                $mensaje .= ' La fecha HASTA no corresponde al mes del reporte ' . sprintf('%04d-%02d', (int) ($fila['anio'] ?? 0), (int) ($fila['mes'] ?? 0)) . '.';
            }
        } elseif ($subioSinAjuste) {
            $seccion = 'aumentos';
            $situacion = 'SUBIO SIN AJUSTE';
            $mensaje = 'El periodo esta correcto, pero el pago subio contra el reporte anterior.';
        }
        if ($sinVinculo) {
            $mensaje .= ' No tiene escuela vinculada; presentarlo por RPU y validar el plantel.';
        }

        return [
            'seccion' => $seccion,
            'situacion' => $situacion,
            'escuela' => trim((string) ($fila['escuelas_vinculadas'] ?? '')) !== '' ? (string) $fila['escuelas_vinculadas'] : 'RPU SIN ESCUELA VINCULADA',
            'cct' => (string) ($fila['ccts_vinculados'] ?? ''),
            'nivel' => (string) ($fila['niveles_vinculados'] ?? ''),
            'rpu' => (string) ($fila['RPU'] ?? ''),
            'recibo' => (string) ($fila['nombre_cfe'] ?? ''),
            'poblacion' => (string) ($fila['poblacion_cfe'] ?? ''),
            'tarifa' => (string) ($fila['tarifa_cfe'] ?? ''),
            'periodo' => trim((string) ($fila['desde'] ?? '') . ' / ' . (string) ($fila['hasta'] ?? '')),
            'periodo_esperado' => $periodoEsperado,
            'dias' => $dias,
            'consumo' => (float) ($fila['consumo'] ?? 0),
            'total' => $totalActual,
            'aumento' => $aumento,
            'mensaje' => $mensaje
        ];
    }

    private function construirExcelDirectores(array $reportes, array $casos, string $titulo, string $modo = 'problemas'): string
    {
        $totales = ['ajustes' => 0, 'aumentos' => 0, 'bajo_consumo' => 0, 'sin_vinculo' => 0];
        foreach ($casos as $grupo) {
            foreach ($grupo as $caso) {
                $totales[$caso['seccion']]++;
                if (trim((string) $caso['cct']) === '') {
                    $totales['sin_vinculo']++;
                }
            }
        }
        $periodos = implode(', ', array_map(static fn (array $reporte): string => sprintf('%04d-%02d', (int) $reporte['anio'], (int) $reporte['mes']), $reportes));
        $html = '<!doctype html><html><head><meta charset="utf-8"><style>
            body{font-family:Arial,sans-serif}
            table{border-collapse:collapse;width:100%}
            td,th{border:1px solid #4aa8d8;font-size:10px;padding:5px;text-align:center;vertical-align:middle}
            .brand td{border:0;font-weight:bold}
            .brand-title{font-size:16px;text-align:center}
            .brand-sub{font-size:12px;text-align:center}
            .red-band td{background:#d60000;color:#fff;font-size:12px;font-weight:bold;text-align:center}
            .summary td{background:#f6f0df;border-color:#d8c894;font-weight:bold;font-size:11px}
            .director td{background:#fff2cc;border-color:#d6b656;font-size:12px;font-weight:bold;text-align:left}
            .report td{background:#6A1B29;color:#fff;font-size:12px;font-weight:bold;text-align:left}
            th{background:#92d050;color:#000;font-weight:bold}
            .section td{color:#fff!important;font-size:12px;font-weight:bold;text-align:left}
            .section-ajustes td{background:#9c0006!important}
            .section-aumentos td{background:#bf9000!important}
            .section-sin-vinculo td{background:#203864!important}
            .section-bajo-consumo td{background:#1f4e79!important}
            .even td{background:#d9f2ff}
            .odd td{background:#ffffff}
            .money{text-align:right;mso-number-format:"\0022$\0022#,##0.00"}
            .number{text-align:right}
            .total{font-weight:bold;background:#e2f0d9!important}
        </style></head><body><table>';
        $html .= '<tr class="brand"><td colspan="16" class="brand-title">SECRETARIA DE EDUCACION GUERRERO</td></tr>';
        $html .= '<tr class="brand"><td colspan="16" class="brand-sub">SUBSECRETARIA DE ADMINISTRACION Y FINANZAS - DIRECCION DE RECURSOS MATERIALES</td></tr>';
        $html .= '<tr class="red-band"><td colspan="16">' . $this->excelTexto($titulo) . '</td></tr>';
        if ($modo === 'bajo_consumo') {
            $html .= '<tr class="director"><td colspan="16">Lectura rapida: en los reportes ' . $this->excelTexto($periodos) . ' hay ' . number_format($totales['bajo_consumo']) . ' RPUs con consumo de 50 kWh o menos. Son casos para revisar si la escuela esta funcionando, tiene falta de servicio o solo paga el minimo.</td></tr>';
            $html .= '<tr class="summary"><td colspan="6">Reportes incluidos: ' . $this->excelTexto($periodos) . '</td><td colspan="5">Consumo muy bajo: ' . number_format($totales['bajo_consumo']) . '</td><td colspan="5">Sin escuela vinculada: ' . number_format($totales['sin_vinculo']) . '</td></tr>';
        } else {
            $html .= '<tr class="director"><td colspan="16">Lectura rapida: un ajuste es cuando las fechas DESDE/HASTA del recibo no coinciden con el calendario real del mes. Mensual se compara contra los dias reales del mes; bimestral contra la suma del mes y el mes anterior. En los reportes ' . $this->excelTexto($periodos) . ' hay ' . number_format($totales['ajustes']) . ' ajustes por fechas y ' . number_format($totales['aumentos']) . ' casos que subieron aunque el periodo si esta correcto.</td></tr>';
            $html .= '<tr class="summary"><td colspan="4">Reportes incluidos: ' . $this->excelTexto($periodos) . '</td><td colspan="4">Ajustes por fechas: ' . number_format($totales['ajustes']) . '</td><td colspan="4">Subieron sin ajuste: ' . number_format($totales['aumentos']) . '</td><td colspan="4">Sin escuela vinculada: ' . number_format($totales['sin_vinculo']) . '</td></tr>';
        }
        $html .= '<tr><th>N.P.</th><th>SITUACION</th><th>ESCUELA O RPU</th><th>CCT</th><th>NIVEL</th><th>RPU</th><th>RECIBO CFE</th><th>POBLACION CFE</th><th>TARIFA</th><th>PERIODO ESPERADO</th><th>PERIODO QUE TRAE CFE</th><th>DIAS</th><th>CONSUMO KWH</th><th>TOTAL ACTUAL</th><th>SUBIO VS ANTERIOR</th><th>EXPLICACION SIMPLE</th></tr>';

        foreach ($reportes as $reporte) {
            $reporteId = (int) $reporte['id'];
            $periodo = sprintf('%04d-%02d', (int) $reporte['anio'], (int) $reporte['mes']);
            $html .= '<tr class="report"><td colspan="16">REPORTE ' . $this->excelTexto($periodo) . ' - ' . $this->excelTexto((string) $reporte['archivo']) . '</td></tr>';
            $grupo = $casos[$reporteId] ?? [];
            if ($modo === 'bajo_consumo') {
                $html .= $this->excelSeccionDirectores($grupo, 'bajo_consumo', '1. CONSUMO MUY BAJO 0 A 50 KWH');
            } else {
                $html .= $this->excelSeccionDirectores($grupo, 'ajustes', '1. AJUSTES POR FECHAS QUE NO COINCIDEN');
                $html .= $this->excelSeccionDirectores($grupo, 'aumentos', '2. SUBIERON PERO SU PERIODO SI ESTA CORRECTO');
            }
        }

        return $html . '</table></body></html>';
    }

    private function construirCsvDirectores(array $reportes, array $casos, string $modo): string
    {
        $archivo = fopen('php://temp', 'r+');
        fputcsv($archivo, [
            'PERIODO_REPORTE', 'ARCHIVO_REPORTE', 'SECCION', 'SITUACION', 'ESCUELA_O_RPU', 'CCT', 'NIVEL', 'RPU',
            'RECIBO_CFE', 'POBLACION_CFE', 'TARIFA', 'PERIODO_ESPERADO', 'PERIODO_CFE', 'DIAS', 'CONSUMO_KWH',
            'TOTAL_ACTUAL', 'AUMENTO_VS_ANTERIOR', 'EXPLICACION'
        ], ',', '"');
        foreach ($reportes as $reporte) {
            $reporteId = (int) $reporte['id'];
            $periodo = sprintf('%04d-%02d', (int) $reporte['anio'], (int) $reporte['mes']);
            foreach ($casos[$reporteId] ?? [] as $caso) {
                if ($modo === 'bajo_consumo' && $caso['seccion'] !== 'bajo_consumo') {
                    continue;
                }
                if ($modo !== 'bajo_consumo' && !in_array($caso['seccion'], ['ajustes', 'aumentos'], true)) {
                    continue;
                }
                fputcsv($archivo, [
                    $periodo,
                    (string) $reporte['archivo'],
                    (string) $caso['seccion'],
                    (string) $caso['situacion'],
                    (string) $caso['escuela'],
                    (string) $caso['cct'],
                    (string) $caso['nivel'],
                    (string) $caso['rpu'],
                    (string) $caso['recibo'],
                    (string) $caso['poblacion'],
                    (string) $caso['tarifa'],
                    (string) ($caso['periodo_esperado'] ?? ''),
                    (string) $caso['periodo'],
                    $caso['dias'] ?? '',
                    number_format((float) $caso['consumo'], 2, '.', ''),
                    number_format((float) $caso['total'], 2, '.', ''),
                    number_format((float) $caso['aumento'], 2, '.', ''),
                    (string) $caso['mensaje']
                ], ',', '"');
            }
        }
        rewind($archivo);
        $csv = stream_get_contents($archivo) ?: '';
        fclose($archivo);
        return $csv;
    }

    private function excelSeccionDirectores(array $grupo, string $seccion, string $titulo): string
    {
        $items = array_values(array_filter($grupo, static fn (array $caso): bool => $caso['seccion'] === $seccion));
        $clase = str_replace('_', '-', $seccion);
        if (!$items) {
            return '<tr class="section section-' . $clase . '"><td colspan="16">' . $this->excelTexto($titulo) . ': SIN CASOS</td></tr>';
        }
        $html = '<tr class="section section-' . $clase . '"><td colspan="16">' . $this->excelTexto($titulo) . ' (' . number_format(count($items)) . ')</td></tr>';
        foreach ($items as $indice => $caso) {
            $html .= '<tr class="' . ($indice % 2 === 0 ? 'even' : 'odd') . '">';
            $html .= '<td>' . ($indice + 1) . '</td>';
            $html .= '<td>' . $this->excelTexto($caso['situacion']) . '</td>';
            $html .= '<td>' . $this->excelTexto($caso['escuela']) . '</td>';
            $html .= '<td>' . $this->excelTexto($caso['cct']) . '</td>';
            $html .= '<td>' . $this->excelTexto($caso['nivel']) . '</td>';
            $html .= '<td>' . $this->excelTexto($caso['rpu']) . '</td>';
            $html .= '<td>' . $this->excelTexto($caso['recibo']) . '</td>';
            $html .= '<td>' . $this->excelTexto($caso['poblacion']) . '</td>';
            $html .= '<td>' . $this->excelTexto($caso['tarifa']) . '</td>';
            $html .= '<td>' . $this->excelTexto($caso['periodo_esperado'] ?? '') . '</td>';
            $html .= '<td>' . $this->excelTexto($caso['periodo']) . '</td>';
            $html .= '<td>' . $this->excelTexto((string) ($caso['dias'] ?? '')) . '</td>';
            $html .= '<td class="number">' . number_format((float) $caso['consumo'], 2) . '</td>';
            $html .= '<td class="money total">$ ' . number_format((float) $caso['total'], 2) . '</td>';
            $html .= '<td class="money">$ ' . number_format((float) $caso['aumento'], 2) . '</td>';
            $html .= '<td>' . $this->excelTexto($caso['mensaje']) . '</td>';
            $html .= '</tr>';
        }
        return $html;
    }

    private function rangoPeriodoEsperado(string $tipoPeriodo, int $anio, int $mes): array
    {
        if ($anio < 1900 || $mes < 1 || $mes > 12) {
            return $tipoPeriodo === 'mensual'
                ? [25, 35, 'Mensual: rango general de 25 a 35 dias']
                : [50, 75, 'Bimestral: rango general de 50 a 75 dias'];
        }

        if ($tipoPeriodo === 'mensual') {
            $diasMes = $this->diasDelMes($anio, $mes);
            return [max(1, $diasMes - 2), $diasMes + 2, 'Mensual: mes de ' . $diasMes . ' dias, aceptado de ' . max(1, $diasMes - 2) . ' a ' . ($diasMes + 2) . ' dias'];
        }

        $mesAnterior = $mes === 1 ? 12 : $mes - 1;
        $anioAnterior = $mes === 1 ? $anio - 1 : $anio;
        $diasBimestre = $this->diasDelMes($anioAnterior, $mesAnterior) + $this->diasDelMes($anio, $mes);
        return [max(1, $diasBimestre - 3), $diasBimestre + 3, 'Bimestral: bimestre de ' . $diasBimestre . ' dias, aceptado de ' . max(1, $diasBimestre - 3) . ' a ' . ($diasBimestre + 3) . ' dias'];
    }

    private function diasDelMes(int $anio, int $mes): int
    {
        return (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $anio, $mes)))->format('t');
    }

    private function excelTexto(mixed $valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
    }

    private function textoONulo(mixed $valor): ?string
    {
        $texto = trim((string) $valor);
        return $texto !== '' ? $texto : null;
    }

    private function fechaONulo(mixed $valor): ?string
    {
        $texto = trim((string) $valor);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $texto) ? $texto : null;
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

$accion = $_POST['accion'] ?? '';
$controlador = new AjustesController();

if ($accion === 'analizar_ajustes_cfe') {
    $controlador->analizar();
}

if ($accion === 'consultar_reporte_guardado') {
    $controlador->consultarReporteGuardado();
}

if ($accion === 'importar_reportes_masivos') {
    $controlador->importarReportesMasivos();
}

if ($accion === 'exportar_excel_directores') {
    $controlador->exportarExcelDirectores();
}

http_response_code(400);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => false, 'error' => 'Accion no reconocida.'], JSON_UNESCAPED_UNICODE);
