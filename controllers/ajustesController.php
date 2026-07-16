<?php

declare(strict_types=1);

session_start();

require_once dirname(__DIR__) . '/services/conexion.php';

class AjustesController
{
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
                nombre_cfe VARCHAR(255) NULL,
                poblacion_cfe VARCHAR(255) NULL,
                tarifa_cfe VARCHAR(10) NULL,
                tipo_periodo VARCHAR(20) NULL,
                desde DATE NULL,
                hasta DATE NULL,
                dias INT NULL,
                consumo DECIMAL(14,2) NOT NULL DEFAULT 0,
                energia DECIMAL(14,2) NOT NULL DEFAULT 0,
                dap DECIMAL(14,2) NOT NULL DEFAULT 0,
                cargos_depositos DECIMAL(14,2) NOT NULL DEFAULT 0,
                creditos_redondeos DECIMAL(14,2) NOT NULL DEFAULT 0,
                total DECIMAL(14,2) NOT NULL DEFAULT 0,
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
            $reportes = $conexion->query(
                'SELECT id, archivo, anio, mes, total_registros, con_alerta, severos, periodo_correcto, ajuste_muchos_dias, periodo_correcto_con_aumento, sin_alerta_con_aumento, importe_total
                 FROM cfe_reportes
                 ORDER BY anio DESC, mes DESC, id DESC
                 LIMIT 3'
            )->fetchAll();

            if (!$reportes) {
                $this->responder(['ok' => false, 'error' => 'Aun no hay reportes CFE guardados para exportar.'], 422);
            }

            $casos = $this->obtenerCasosExcelDirectores($conexion, array_map(static fn (array $reporte): int => (int) $reporte['id'], $reportes));
            $html = $this->construirExcelDirectores($reportes, $casos);

            http_response_code(200);
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="reporte_directores_cfe_ultimos_3_' . date('Ymd_His') . '.xls"');
            echo "\xEF\xBB\xBF" . $html;
            exit;
        } catch (Throwable $e) {
            $this->responder(['ok' => false, 'error' => 'Fallo al exportar Excel: ' . $e->getMessage()], 500);
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
                'INSERT INTO cfe_consumos (reporte_id, RPU, CCT, nombre_cfe, poblacion_cfe, tarifa_cfe, tipo_periodo, desde, hasta, dias, consumo, energia, dap, cargos_depositos, creditos_redondeos, total, diferencia, severidad, alertas)
                 VALUES (:reporte_id, :rpu, :cct, :nombre, :poblacion, :tarifa, :tipo_periodo, :desde, :hasta, :dias, :consumo, :energia, :dap, :cargos, :creditos, :total, :diferencia, :severidad, :alertas)'
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
                    'nombre' => $this->textoONulo($registro['nombre'] ?? null),
                    'poblacion' => $this->textoONulo($registro['poblacion'] ?? null),
                    'tarifa' => $this->textoONulo($registro['tarifa'] ?? null),
                    'tipo_periodo' => $this->textoONulo($registro['tipo_periodo'] ?? null),
                    'desde' => $this->fechaONulo($registro['desde'] ?? null),
                    'hasta' => $this->fechaONulo($registro['hasta'] ?? null),
                    'dias' => is_numeric($registro['dias'] ?? null) ? (int) $registro['dias'] : null,
                    'consumo' => (float) ($registro['consumo'] ?? 0),
                    'energia' => (float) ($registro['energia'] ?? 0),
                    'dap' => (float) ($registro['dap'] ?? 0),
                    'cargos' => (float) ($registro['cargos_depositos'] ?? 0),
                    'creditos' => (float) ($registro['creditos_redondeos'] ?? 0),
                    'total' => (float) ($registro['total'] ?? 0),
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

    private function obtenerCasosExcelDirectores(PDO $conexion, array $reportesIds): array
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
            $caso = $this->prepararCasoExcelDirectores($fila);
            if ($caso !== null) {
                $casos[(int) $fila['reporte_id']][] = $caso;
            }
        }
        return $casos;
    }

    private function prepararCasoExcelDirectores(array $fila): ?array
    {
        $dias = is_numeric($fila['dias'] ?? null) ? (int) $fila['dias'] : null;
        $tipoPeriodo = (string) ($fila['tipo_periodo'] ?? '');
        $maximo = $tipoPeriodo === 'mensual' ? 35 : 75;
        $minimo = $tipoPeriodo === 'mensual' ? 25 : 50;
        $periodoCorrecto = $dias !== null && $dias >= $minimo && $dias <= $maximo;
        $totalActual = (float) ($fila['total'] ?? 0);
        $totalAnterior = $fila['total_anterior'] !== null ? (float) $fila['total_anterior'] : null;
        $aumento = $totalAnterior !== null ? $totalActual - $totalAnterior : 0.0;
        $alertas = trim((string) ($fila['alertas'] ?? ''));
        $muchosDias = $dias !== null && $dias > $maximo;
        $subioSinAjuste = $periodoCorrecto && $aumento > 0;
        $sinVinculo = trim((string) ($fila['ccts_vinculados'] ?? '')) === '';

        if (!$muchosDias && $alertas === '' && !$subioSinAjuste) {
            return null;
        }

        if ($muchosDias || $alertas !== '') {
            $seccion = 'ajustes';
            $situacion = $muchosDias ? 'CON AJUSTE POR MUCHOS DIAS' : 'CON AJUSTE';
            $mensaje = $alertas !== '' ? $alertas : 'El recibo trae mas dias de los esperados para su tarifa.';
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
            'dias' => $dias,
            'consumo' => (float) ($fila['consumo'] ?? 0),
            'total' => $totalActual,
            'aumento' => $aumento,
            'mensaje' => $mensaje
        ];
    }

    private function construirExcelDirectores(array $reportes, array $casos): string
    {
        $totales = ['ajustes' => 0, 'aumentos' => 0, 'sin_vinculo' => 0];
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
            .even td{background:#d9f2ff}
            .odd td{background:#ffffff}
            .money{text-align:right;mso-number-format:"\0022$\0022#,##0.00"}
            .number{text-align:right}
            .total{font-weight:bold;background:#e2f0d9!important}
        </style></head><body><table>';
        $html .= '<tr class="brand"><td colspan="15" class="brand-title">SECRETARIA DE EDUCACION GUERRERO</td></tr>';
        $html .= '<tr class="brand"><td colspan="15" class="brand-sub">SUBSECRETARIA DE ADMINISTRACION Y FINANZAS - DIRECCION DE RECURSOS MATERIALES</td></tr>';
        $html .= '<tr class="red-band"><td colspan="15">REPORTE ENTENDIBLE DE PROBLEMAS CFE - ULTIMOS 3 REPORTES</td></tr>';
        $html .= '<tr class="director"><td colspan="15">Lectura rapida: en los reportes ' . $this->excelTexto($periodos) . ' hay ' . number_format($totales['ajustes']) . ' casos con ajuste o muchos dias, ' . number_format($totales['aumentos']) . ' casos que subieron sin ajuste y ' . number_format($totales['sin_vinculo']) . ' casos que se deben explicar por RPU porque no tienen escuela vinculada.</td></tr>';
        $html .= '<tr class="summary"><td colspan="4">Reportes incluidos: ' . $this->excelTexto($periodos) . '</td><td colspan="3">Con ajuste: ' . number_format($totales['ajustes']) . '</td><td colspan="3">Subieron sin ajuste: ' . number_format($totales['aumentos']) . '</td><td colspan="5">Sin escuela vinculada dentro de alertas: ' . number_format($totales['sin_vinculo']) . '</td></tr>';
        $html .= '<tr><th>N.P.</th><th>SITUACION</th><th>ESCUELA O RPU</th><th>CCT</th><th>NIVEL</th><th>RPU</th><th>RECIBO CFE</th><th>POBLACION CFE</th><th>TARIFA</th><th>PERIODO</th><th>DIAS</th><th>CONSUMO KWH</th><th>TOTAL ACTUAL</th><th>SUBIO VS ANTERIOR</th><th>QUE DECIR</th></tr>';

        foreach ($reportes as $reporte) {
            $reporteId = (int) $reporte['id'];
            $periodo = sprintf('%04d-%02d', (int) $reporte['anio'], (int) $reporte['mes']);
            $html .= '<tr class="report"><td colspan="15">REPORTE ' . $this->excelTexto($periodo) . ' - ' . $this->excelTexto((string) $reporte['archivo']) . '</td></tr>';
            $grupo = $casos[$reporteId] ?? [];
            $html .= $this->excelSeccionDirectores($grupo, 'ajustes', '1. CON AJUSTE O MUCHOS DIAS');
            $html .= $this->excelSeccionDirectores($grupo, 'aumentos', '2. SUBIERON SIN AJUSTE');
        }

        return $html . '</table></body></html>';
    }

    private function excelSeccionDirectores(array $grupo, string $seccion, string $titulo): string
    {
        $items = array_values(array_filter($grupo, static fn (array $caso): bool => $caso['seccion'] === $seccion));
        $clase = str_replace('_', '-', $seccion);
        if (!$items) {
            return '<tr class="section section-' . $clase . '"><td colspan="15">' . $this->excelTexto($titulo) . ': SIN CASOS</td></tr>';
        }
        $html = '<tr class="section section-' . $clase . '"><td colspan="15">' . $this->excelTexto($titulo) . ' (' . number_format(count($items)) . ')</td></tr>';
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

if ($accion === 'exportar_excel_directores') {
    $controlador->exportarExcelDirectores();
}

http_response_code(400);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => false, 'error' => 'Accion no reconocida.'], JSON_UNESCAPED_UNICODE);
