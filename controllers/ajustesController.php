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

http_response_code(400);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => false, 'error' => 'Accion no reconocida.'], JSON_UNESCAPED_UNICODE);
