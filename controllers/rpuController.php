<?php

declare(strict_types=1);

session_start();

require_once dirname(__DIR__) . '/services/conexion.php';

class RpuController
{
    public function sugerirMalos(): void
    {
        $this->validarToken();
        try {
            $conexion = Conexion::conectar();
            $this->prepararTablas($conexion);
            $periodos = $conexion->query(
                'SELECT DISTINCT anio, mes FROM cfe_reportes ORDER BY anio DESC, mes DESC LIMIT 4'
            )->fetchAll();
            if (!$periodos) {
                $this->responder(['ok' => true, 'periodos' => [], 'rpus' => []]);
            }

            $condiciones = [];
            $parametros = [];
            foreach ($periodos as $periodo) {
                $condiciones[] = '(cr.anio = ? AND cr.mes = ?)';
                $parametros[] = (int) $periodo['anio'];
                $parametros[] = (int) $periodo['mes'];
            }

            $consulta = $conexion->prepare(
                'SELECT cc.RPU, cc.nombre_cfe, cc.poblacion_cfe, cc.tarifa_cfe, cc.total, cc.consumo, cc.severidad, cc.alertas, cr.anio, cr.mes, er.CCT, e.NOMBRECT, e.NIVEL, e.SUBNIVEL, e.NOMBRELOC, e.NOMBREMUN
                 FROM cfe_consumos cc
                 INNER JOIN cfe_reportes cr ON cr.id = cc.reporte_id
                 LEFT JOIN escuelas_rpu er ON er.RPU = cc.RPU
                 LEFT JOIN escuelas e ON e.CCT = er.CCT
                 WHERE ' . implode(' OR ', $condiciones) . '
                 ORDER BY cc.RPU, cr.anio DESC, cr.mes DESC, cc.id DESC'
            );
            $consulta->execute($parametros);
            $agrupados = [];
            foreach ($consulta->fetchAll() as $fila) {
                $rpu = (string) $fila['RPU'];
                if (!isset($agrupados[$rpu])) {
                    $agrupados[$rpu] = [
                        'rpu' => $rpu,
                        'nombre' => (string) ($fila['nombre_cfe'] ?? ''),
                        'poblacion' => (string) ($fila['poblacion_cfe'] ?? ''),
                        'tarifa' => (string) ($fila['tarifa_cfe'] ?? ''),
                        'cct' => $fila['CCT'] ?? null,
                        'escuela' => $fila['NOMBRECT'] ?? null,
                        'nivel' => $fila['NIVEL'] ?? null,
                        'subnivel' => $fila['SUBNIVEL'] ?? null,
                        'localidad' => $fila['NOMBRELOC'] ?? null,
                        'municipio' => $fila['NOMBREMUN'] ?? null,
                        'filas' => []
                    ];
                }
                $agrupados[$rpu]['filas'][] = [
                    'periodo' => sprintf('%04d-%02d', (int) $fila['anio'], (int) $fila['mes']),
                    'total' => (float) $fila['total'],
                    'consumo' => (float) $fila['consumo'],
                    'severidad' => (int) $fila['severidad'],
                    'alertas' => trim((string) ($fila['alertas'] ?? ''))
                ];
                if (!$agrupados[$rpu]['cct'] && $fila['CCT']) {
                    $agrupados[$rpu]['cct'] = $fila['CCT'];
                    $agrupados[$rpu]['escuela'] = $fila['NOMBRECT'] ?? null;
                    $agrupados[$rpu]['nivel'] = $fila['NIVEL'] ?? null;
                    $agrupados[$rpu]['subnivel'] = $fila['SUBNIVEL'] ?? null;
                    $agrupados[$rpu]['localidad'] = $fila['NOMBRELOC'] ?? null;
                    $agrupados[$rpu]['municipio'] = $fila['NOMBREMUN'] ?? null;
                }
            }

            $rpus = [];
            foreach ($agrupados as $grupo) {
                $filas = $grupo['filas'];
                $ultima = $filas[0] ?? ['total' => 0, 'consumo' => 0, 'severidad' => 0, 'alertas' => '', 'periodo' => ''];
                $anterior = $filas[1] ?? null;
                $alertas = count(array_filter($filas, fn (array $fila): bool => $fila['severidad'] >= 4 || $fila['alertas'] !== ''));
                $maxSeveridad = max(array_column($filas, 'severidad') ?: [0]);
                $subioTotal = $anterior ? (float) $ultima['total'] - (float) $anterior['total'] : 0;
                $score = ($maxSeveridad * 10) + ($alertas * 8);
                if (!$grupo['cct']) {
                    $score += 8;
                } elseif ((int) $ultima['severidad'] >= 4 || $alertas >= 2) {
                    $score += 14;
                }
                if ($subioTotal > 0) {
                    $score += 15;
                }
                if ((float) $ultima['total'] >= 20000) {
                    $score += 12;
                }
                if ((int) $ultima['severidad'] >= 4) {
                    $score += 10;
                }
                if ($score < 25) {
                    continue;
                }
                $rpus[] = [
                    'rpu' => $grupo['rpu'],
                    'nombre' => $grupo['nombre'],
                    'poblacion' => $grupo['poblacion'],
                    'tarifa' => $grupo['tarifa'],
                    'cct' => $grupo['cct'],
                    'escuela' => $grupo['escuela'],
                    'nivel' => $grupo['nivel'],
                    'subnivel' => $grupo['subnivel'],
                    'localidad' => $grupo['localidad'],
                    'municipio' => $grupo['municipio'],
                    'periodo' => $ultima['periodo'],
                    'total' => (float) $ultima['total'],
                    'consumo' => (float) $ultima['consumo'],
                    'alertas' => $alertas,
                    'max_severidad' => $maxSeveridad,
                    'diferencia_total' => $subioTotal,
                    'score' => min(100, $score),
                    'motivo' => $this->motivoRiesgo($grupo['cct'] !== null, $alertas, $maxSeveridad, $subioTotal, (float) $ultima['total'])
                ];
            }
            usort($rpus, fn (array $a, array $b): int => $b['score'] <=> $a['score']);
            $this->responder([
                'ok' => true,
                'periodos' => array_map(fn (array $periodo): string => sprintf('%04d-%02d', (int) $periodo['anio'], (int) $periodo['mes']), $periodos),
                'rpus' => array_slice($rpus, 0, 30)
            ]);
        } catch (Throwable $e) {
            $this->responder(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function buscar(): void
    {
        $this->validarToken();
        try {
            $rpu = trim((string) ($_POST['rpu'] ?? ''));
            if ($rpu === '') {
                throw new RuntimeException('Captura un RPU para consultar.');
            }

            $conexion = Conexion::conectar();
            $this->prepararTablas($conexion);
            $historial = $this->historial($conexion, $rpu);
            $vinculos = $this->vinculos($conexion, $rpu);
            $ultimo = $historial[0] ?? null;
            $sugerencias = $vinculos ? [] : $this->sugerencias($conexion, $rpu, $ultimo);
            $mapa = $this->mapa($vinculos[0] ?? $sugerencias[0] ?? null);

            $this->responder([
                'ok' => true,
                'rpu' => $rpu,
                'encontrado' => $historial !== [] || $vinculos !== [],
                'ultimo' => $ultimo,
                'cfe' => [
                    'rpu' => $rpu,
                    'nombre' => $ultimo['nombre_cfe'] ?? ($vinculos[0]['nombre_recibo_cfe'] ?? ''),
                    'poblacion' => $ultimo['poblacion_cfe'] ?? ($vinculos[0]['poblacion_cfe'] ?? ''),
                    'tarifa' => $ultimo['tarifa_cfe'] ?? ($vinculos[0]['tarifa_cfe'] ?? ''),
                    'periodo' => $ultimo ? sprintf('%04d-%02d', (int) $ultimo['anio'], (int) $ultimo['mes']) : ''
                ],
                'vinculos' => $vinculos,
                'sugerencias' => $sugerencias,
                'historial' => array_reverse($historial),
                'mapa' => $mapa,
                'resumen' => $this->resumen($historial)
            ]);
        } catch (Throwable $e) {
            $this->responder(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function vincular(): void
    {
        $this->validarToken();
        try {
            $rpu = trim((string) ($_POST['rpu'] ?? ''));
            $cct = trim((string) ($_POST['cct'] ?? ''));
            if ($rpu === '' || $cct === '') {
                throw new RuntimeException('Faltan RPU o CCT para vincular.');
            }

            $conexion = Conexion::conectar();
            $this->prepararTablas($conexion);
            $ultimo = $this->historial($conexion, $rpu)[0] ?? [];
            $consulta = $conexion->prepare(
                'INSERT INTO escuelas_rpu (CCT, RPU, nombre_recibo_cfe, poblacion_cfe, tarifa_cfe)
                 VALUES (:cct, :rpu, :nombre, :poblacion, :tarifa)
                 ON DUPLICATE KEY UPDATE nombre_recibo_cfe = VALUES(nombre_recibo_cfe), poblacion_cfe = VALUES(poblacion_cfe), tarifa_cfe = VALUES(tarifa_cfe)'
            );
            $consulta->execute([
                'cct' => $cct,
                'rpu' => $rpu,
                'nombre' => $this->nulo($ultimo['nombre_cfe'] ?? null),
                'poblacion' => $this->nulo($ultimo['poblacion_cfe'] ?? null),
                'tarifa' => $this->nulo($ultimo['tarifa_cfe'] ?? null)
            ]);
            $conexion->prepare('UPDATE cfe_consumos SET CCT = :cct WHERE RPU = :rpu AND CCT IS NULL')->execute([
                'cct' => $cct,
                'rpu' => $rpu
            ]);

            $this->responder(['ok' => true, 'mensaje' => 'RPU vinculado correctamente.']);
        } catch (Throwable $e) {
            $this->responder(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function prepararTablas(PDO $conexion): void
    {
        $this->prepararTablaEscuelas($conexion);
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
                INDEX idx_cfe_consumos_reporte (reporte_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    private function prepararTablaEscuelas(PDO $conexion): void
    {
        $columnas = [
            'NIVEL' => 'VARCHAR(100) NULL',
            'HOMO' => 'VARCHAR(30) NULL',
            'TURNO' => 'VARCHAR(100) NULL',
            'ZONA' => 'VARCHAR(50) NULL',
            'SECTOR' => 'VARCHAR(50) NULL',
            'ORIGEN' => 'VARCHAR(80) NULL'
        ];
        $existentes = [];
        $consulta = $conexion->query('SHOW COLUMNS FROM escuelas');
        foreach ($consulta->fetchAll() as $fila) {
            $existentes[strtoupper((string) $fila['Field'])] = true;
        }
        foreach ($columnas as $columna => $definicion) {
            if (!isset($existentes[$columna])) {
                $conexion->exec("ALTER TABLE escuelas ADD COLUMN {$columna} {$definicion}");
            }
        }
    }

    private function historial(PDO $conexion, string $rpu): array
    {
        $consulta = $conexion->prepare(
            'SELECT cc.*, cr.anio, cr.mes, cr.archivo
             FROM cfe_consumos cc
             INNER JOIN cfe_reportes cr ON cr.id = cc.reporte_id
             WHERE cc.RPU = ?
             ORDER BY cr.anio DESC, cr.mes DESC, cc.hasta DESC, cc.id DESC'
        );
        $consulta->execute([$rpu]);
        return $consulta->fetchAll();
    }

    private function vinculos(PDO $conexion, string $rpu): array
    {
        $consulta = $conexion->prepare(
            'SELECT er.RPU, er.CCT, er.nombre_recibo_cfe, er.poblacion_cfe, er.tarifa_cfe, e.NOMBRECT, e.DOMICILIO, e.NOMBREMUN, e.NOMBRELOC, e.STATUS, e.SUBNIVEL, e.NIVEL, e.HOMO, e.TURNO, e.ZONA, e.SECTOR, e.ORIGEN
             FROM escuelas_rpu er
             LEFT JOIN escuelas e ON e.CCT = er.CCT
             WHERE er.RPU = ?
             ORDER BY er.id'
        );
        $consulta->execute([$rpu]);
        return array_map(fn (array $fila): array => $this->escuelaDesdeFila($fila, 100, 'Vinculo confirmado'), $consulta->fetchAll());
    }

    private function sugerencias(PDO $conexion, string $rpu, ?array $ultimo): array
    {
        $historicas = $this->sugerenciasHistoricas($conexion, $rpu);
        if ($historicas) {
            return $historicas;
        }
        if (!$ultimo) {
            return [];
        }
        $poblacion = trim((string) ($ultimo['poblacion_cfe'] ?? ''));
        $nombre = trim((string) ($ultimo['nombre_cfe'] ?? ''));
        $parametros = [];
        $condiciones = [];
        if ($poblacion !== '') {
            $parametros['poblacion_loc'] = '%' . $poblacion . '%';
            $parametros['poblacion_mun'] = '%' . $poblacion . '%';
            $condiciones[] = '(NOMBRELOC LIKE :poblacion_loc OR NOMBREMUN LIKE :poblacion_mun)';
        }
        foreach (array_slice(array_filter(preg_split('/\s+/', $nombre) ?: [], fn ($p): bool => strlen($p) >= 4), 0, 3) as $i => $palabra) {
            $parametros['p' . $i] = '%' . $palabra . '%';
            $condiciones[] = 'NOMBRECT LIKE :p' . $i;
        }
        if (!$condiciones) {
            return [];
        }
        $consulta = $conexion->prepare(
            'SELECT CCT, NOMBRECT, DOMICILIO, NOMBREMUN, NOMBRELOC, STATUS, SUBNIVEL, NIVEL, HOMO, TURNO, ZONA, SECTOR, ORIGEN
             FROM escuelas
             WHERE ' . implode(' OR ', $condiciones) . '
             LIMIT 250'
        );
        $consulta->execute($parametros);
        $sugerencias = [];
        foreach ($consulta->fetchAll() as $fila) {
            $score = $this->puntaje($nombre, $poblacion, $fila);
            if ($score >= 25) {
                $sugerencias[] = $this->escuelaDesdeFila($fila, $score, 'Sugerencia por nombre/localidad');
            }
        }
        usort($sugerencias, fn (array $a, array $b): int => $b['score'] <=> $a['score']);
        return array_slice($sugerencias, 0, 6);
    }

    private function sugerenciasHistoricas(PDO $conexion, string $rpu): array
    {
        $consulta = $conexion->prepare(
            'SELECT cc.CCT, e.NOMBRECT, e.DOMICILIO, e.NOMBREMUN, e.NOMBRELOC, e.STATUS, e.SUBNIVEL, e.NIVEL, e.HOMO, e.TURNO, e.ZONA, e.SECTOR, e.ORIGEN, COUNT(*) apariciones
             FROM cfe_consumos cc
             INNER JOIN escuelas e ON e.CCT = cc.CCT
             WHERE cc.RPU = ? AND cc.CCT IS NOT NULL
             GROUP BY cc.CCT, e.NOMBRECT, e.DOMICILIO, e.NOMBREMUN, e.NOMBRELOC, e.STATUS, e.SUBNIVEL, e.NIVEL, e.HOMO, e.TURNO, e.ZONA, e.SECTOR, e.ORIGEN
             ORDER BY apariciones DESC
             LIMIT 6'
        );
        $consulta->execute([$rpu]);
        return array_map(
            fn (array $fila): array => $this->escuelaDesdeFila($fila, 85, 'Sugerencia por historial'),
            $consulta->fetchAll()
        );
    }

    private function escuelaDesdeFila(array $fila, int $score, string $origen): array
    {
        return [
            'cct' => (string) ($fila['CCT'] ?? ''),
            'nombre' => (string) ($fila['NOMBRECT'] ?? ''),
            'domicilio' => (string) ($fila['DOMICILIO'] ?? ''),
            'municipio' => (string) ($fila['NOMBREMUN'] ?? ''),
            'localidad' => (string) ($fila['NOMBRELOC'] ?? ''),
            'status' => (string) ($fila['STATUS'] ?? ''),
            'nivel' => (string) ($fila['NIVEL'] ?? '') !== '' ? (string) $fila['NIVEL'] : $this->nivelEducativo((string) ($fila['SUBNIVEL'] ?? '')),
            'subnivel' => (string) ($fila['SUBNIVEL'] ?? ''),
            'homo' => (string) ($fila['HOMO'] ?? ''),
            'turno' => (string) ($fila['TURNO'] ?? ''),
            'zona' => (string) ($fila['ZONA'] ?? ''),
            'sector' => (string) ($fila['SECTOR'] ?? ''),
            'fuente' => (string) ($fila['ORIGEN'] ?? 'Catalogo local SEG/Oficializacion'),
            'score' => $score,
            'origen' => $origen
        ];
    }

    private function puntaje(string $nombreCfe, string $poblacionCfe, array $escuela): int
    {
        $nombreBase = $this->normalizar($nombreCfe);
        $nombreEscuela = $this->normalizar((string) ($escuela['NOMBRECT'] ?? ''));
        similar_text($nombreBase, $nombreEscuela, $similitud);
        $localidad = $this->normalizar((string) ($escuela['NOMBRELOC'] ?? ''));
        $municipio = $this->normalizar((string) ($escuela['NOMBREMUN'] ?? ''));
        $poblacion = $this->normalizar($poblacionCfe);
        $score = (int) round($similitud);
        if ($poblacion !== '' && (($localidad !== '' && ($localidad === $poblacion || str_contains($poblacion, $localidad))) || ($municipio !== '' && $municipio === $poblacion))) {
            $score += 35;
        }
        if ($this->normalizar((string) ($escuela['STATUS'] ?? '')) === '1' || $this->normalizar((string) ($escuela['STATUS'] ?? '')) === 'ACTIVO') {
            $score += 5;
        }
        return min(100, $score);
    }

    private function resumen(array $historial): array
    {
        if (!$historial) {
            return ['registros' => 0, 'total_actual' => 0, 'consumo_actual' => 0, 'diferencia_total' => null, 'estado' => 'Sin historial'];
        }
        $actual = $historial[0];
        $anterior = $historial[1] ?? null;
        $diferencia = $anterior ? (float) $actual['total'] - (float) $anterior['total'] : null;
        return [
            'registros' => count($historial),
            'total_actual' => (float) $actual['total'],
            'consumo_actual' => (float) $actual['consumo'],
            'diferencia_total' => $diferencia,
            'estado' => $diferencia === null ? 'Primer registro' : ($diferencia <= 0 ? 'Mejorando' : 'Subiendo')
        ];
    }

    private function motivoRiesgo(bool $vinculado, int $alertas, int $maxSeveridad, float $subioTotal, float $total): string
    {
        $motivos = [];
        if (!$vinculado) {
            $motivos[] = 'sin vinculo';
        } elseif ($maxSeveridad >= 4 || $alertas >= 2) {
            $motivos[] = 'vinculado con alerta';
        }
        if ($maxSeveridad >= 7) {
            $motivos[] = 'severidad alta';
        } elseif ($maxSeveridad >= 4) {
            $motivos[] = 'alerta recurrente';
        }
        if ($alertas >= 2) {
            $motivos[] = $alertas . ' meses con alerta';
        }
        if ($subioTotal > 0) {
            $motivos[] = 'subio el pago';
        }
        if ($total >= 20000) {
            $motivos[] = 'importe alto';
        }
        return $motivos ? implode(', ', $motivos) : 'revision recomendada';
    }


    private function mapa(?array $escuela): array
    {
        if (!$escuela) {
            return ['query' => 'Guerrero Mexico', 'url' => 'https://www.google.com/maps?q=Guerrero%20Mexico&output=embed'];
        }
        $query = trim(implode(' ', array_filter([
            $escuela['domicilio'] ?? '',
            $escuela['localidad'] ?? '',
            $escuela['municipio'] ?? '',
            'Guerrero Mexico'
        ])));
        return ['query' => $query, 'url' => 'https://www.google.com/maps?q=' . rawurlencode($query) . '&output=embed'];
    }

    private function normalizar(string $texto): string
    {
        $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto) ?: $texto;
        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^A-Z0-9 ]/', ' ', strtoupper($texto))) ?? '');
    }

    private function nivelEducativo(string $subnivel): string
    {
        $texto = $this->normalizar($subnivel);
        if (str_contains($texto, 'PREESCOLAR')) {
            return 'Preescolar';
        }
        if (str_contains($texto, 'PRIMARIA')) {
            return 'Primaria';
        }
        if (str_contains($texto, 'TELESECUNDARIA')) {
            return 'Telesecundaria';
        }
        if (str_contains($texto, 'SECUNDARIA')) {
            return 'Secundaria';
        }
        return $subnivel !== '' ? $subnivel : 'Sin nivel';
    }

    private function nulo(mixed $valor): ?string
    {
        $texto = trim((string) $valor);
        return $texto !== '' ? $texto : null;
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

$controlador = new RpuController();
$accion = $_POST['accion'] ?? '';

if ($accion === 'buscar_rpu') {
    $controlador->buscar();
}

if ($accion === 'sugerir_rpus_malos') {
    $controlador->sugerirMalos();
}

if ($accion === 'vincular_rpu') {
    $controlador->vincular();
}

http_response_code(400);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => false, 'error' => 'Accion no reconocida.'], JSON_UNESCAPED_UNICODE);
