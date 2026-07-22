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
                'SELECT DISTINCT anio, mes FROM cfe_reportes ORDER BY anio DESC, mes DESC LIMIT 6'
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
                $totalAnterior = $anterior ? (float) $anterior['total'] : 0;
                $incrementoPorcentaje = $totalAnterior > 0 ? ($subioTotal / $totalAnterior) * 100 : 0;
                $periodosBajoConsumo = count(array_filter($filas, fn (array $fila): bool => (float) $fila['consumo'] <= 50));
                $periodosPagoMinimo = count(array_filter($filas, fn (array $fila): bool => (float) $fila['total'] <= 100));
                $consumoPromedio = count($filas) > 0 ? array_sum(array_column($filas, 'consumo')) / count($filas) : 0;
                $consumoActualBajo = (float) $ultima['consumo'] <= 50;
                $pagoMinimoActual = (float) $ultima['total'] <= 100;
                $consumoCeroActual = (float) $ultima['consumo'] <= 0 || (float) $ultima['total'] <= 0;
                $riesgoIncremento = $incrementoPorcentaje >= 50;
                $riesgoBajoConsumo = $consumoActualBajo && ($periodosBajoConsumo >= 2 || $consumoCeroActual);
                $riesgoPagoMinimo = $pagoMinimoActual && ($periodosPagoMinimo >= 2 || $consumoCeroActual);
                if (!$riesgoIncremento && !$riesgoBajoConsumo && !$riesgoPagoMinimo) {
                    continue;
                }
                $score = $riesgoIncremento ? 50 + (int) min(45, round($incrementoPorcentaje / 2)) : 58;
                if ($riesgoBajoConsumo) {
                    $score += $consumoCeroActual ? 22 : 12;
                    if ($periodosBajoConsumo >= 3) {
                        $score += 8;
                    }
                }
                if ($riesgoPagoMinimo) {
                    $score += 18;
                    if ($periodosPagoMinimo >= 3) {
                        $score += 8;
                    }
                }
                if (!$grupo['cct']) {
                    $score += 8;
                } elseif ((int) $ultima['severidad'] >= 4 || $alertas >= 2) {
                    $score += 6;
                }
                if ((float) $ultima['total'] >= 20000) {
                    $score += 5;
                }
                if ($incrementoPorcentaje >= 70) {
                    $score += 10;
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
                    'periodo_anterior' => $anterior['periodo'] ?? '',
                    'total' => (float) $ultima['total'],
                    'total_anterior' => $totalAnterior,
                    'consumo' => (float) $ultima['consumo'],
                    'alertas' => $alertas,
                    'max_severidad' => $maxSeveridad,
                    'diferencia_total' => $subioTotal,
                    'incremento_porcentaje' => round($incrementoPorcentaje, 1),
                    'periodos_bajo_consumo' => $periodosBajoConsumo,
                    'periodos_pago_minimo' => $periodosPagoMinimo,
                    'consumo_promedio' => round($consumoPromedio, 2),
                    'historial_periodos' => array_map(
                        fn (array $fila): array => [
                            'periodo' => $fila['periodo'],
                            'total' => (float) $fila['total'],
                            'consumo' => (float) $fila['consumo']
                        ],
                        $filas
                    ),
                    'riesgo_tipo' => $this->tipoRiesgo($riesgoIncremento, $riesgoBajoConsumo, $riesgoPagoMinimo, $consumoCeroActual),
                    'score' => min(100, $score),
                    'motivo' => $this->motivoRiesgo($grupo['cct'] !== null, $alertas, $maxSeveridad, $subioTotal, (float) $ultima['total'], $incrementoPorcentaje, $riesgoBajoConsumo, $periodosBajoConsumo, (float) $ultima['consumo'], $riesgoPagoMinimo, $periodosPagoMinimo)
                ];
            }
            usort($rpus, fn (array $a, array $b): int => $b['score'] <=> $a['score']);
            $this->responder([
                'ok' => true,
                'periodos' => array_map(fn (array $periodo): string => sprintf('%04d-%02d', (int) $periodo['anio'], (int) $periodo['mes']), $periodos),
                'rpus' => array_slice($rpus, 0, 300)
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
            $cctsVinculados = array_flip(array_map(static fn (array $vinculo): string => (string) $vinculo['cct'], $vinculos));
            $sugerencias = array_values(array_filter(
                $this->sugerencias($conexion, $rpu, $ultimo),
                static fn (array $escuela): bool => !isset($cctsVinculados[(string) $escuela['cct']])
            ));
            $mapa = $this->mapa($vinculos[0] ?? $sugerencias[0] ?? null);

            $this->responder([
                'ok' => true,
                'rpu' => $rpu,
                'encontrado' => $historial !== [] || $vinculos !== [],
                'ultimo' => $ultimo,
                'cfe' => [
                    'rpu' => $rpu,
                    'division' => $ultimo['division_cfe'] ?? '',
                    'nombre' => $ultimo['nombre_cfe'] ?? ($vinculos[0]['nombre_recibo_cfe'] ?? ''),
                    'direccion' => $ultimo['direccion_cfe'] ?? '',
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

    public function buscarCatalogoCfe(): void
    {
        $this->validarToken();
        try {
            $conexion = Conexion::conectar();
            $this->prepararTablas($conexion);
            $q = trim((string) ($_POST['q'] ?? ''));
            $nombre = trim((string) ($_POST['nombre'] ?? ''));
            $direccion = trim((string) ($_POST['direccion'] ?? ''));
            $poblacion = trim((string) ($_POST['poblacion'] ?? ''));
            $tarifa = trim((string) ($_POST['tarifa'] ?? ''));
            $division = trim((string) ($_POST['division'] ?? ''));
            $soloSinVinculo = (string) ($_POST['sin_vinculo'] ?? '') === '1';
            $pagina = max(1, (int) ($_POST['pagina'] ?? 1));
            $porPagina = 25;
            $offset = ($pagina - 1) * $porPagina;
            $condiciones = [];
            $parametros = [];

            if ($q !== '') {
                $valor = '%' . $q . '%';
                $columnas = ['u.RPU', 'u.division_cfe', 'u.nombre_cfe', 'u.direccion_cfe', 'u.poblacion_cfe', 'u.tarifa_cfe'];
                $partes = [];
                foreach ($columnas as $indice => $columna) {
                    $clave = 'q_' . $indice;
                    $partes[] = $columna . ' LIKE :' . $clave;
                    $parametros[$clave] = $valor;
                }
                $condiciones[] = '(' . implode(' OR ', $partes) . ')';
            }

            if ($nombre !== '') {
                $condiciones[] = 'u.nombre_cfe LIKE :nombre';
                $parametros['nombre'] = '%' . $nombre . '%';
            }

            if ($direccion !== '') {
                $condiciones[] = 'u.direccion_cfe LIKE :direccion';
                $parametros['direccion'] = '%' . $direccion . '%';
            }

            if ($poblacion !== '') {
                $condiciones[] = 'u.poblacion_cfe LIKE :poblacion';
                $parametros['poblacion'] = '%' . $poblacion . '%';
            }

            if ($tarifa !== '') {
                $condiciones[] = 'u.tarifa_cfe = :tarifa';
                $parametros['tarifa'] = $tarifa;
            }

            if ($division !== '') {
                $condiciones[] = 'u.division_cfe LIKE :division';
                $parametros['division'] = '%' . $division . '%';
            }

            if ($soloSinVinculo) {
                $condiciones[] = 'v.RPU IS NULL';
            }

            $where = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';
            $base = "FROM cfe_consumos u
                INNER JOIN (
                    SELECT RPU, MAX(id) ultimo_id
                    FROM cfe_consumos
                    GROUP BY RPU
                ) ult ON ult.ultimo_id = u.id
                INNER JOIN cfe_reportes cr ON cr.id = u.reporte_id
                LEFT JOIN (
                    SELECT RPU, GROUP_CONCAT(DISTINCT CCT ORDER BY CCT SEPARATOR ' / ') ccts
                    FROM escuelas_rpu
                    GROUP BY RPU
                ) v ON v.RPU = u.RPU
                $where";
            $conteo = $conexion->prepare('SELECT COUNT(*) ' . $base);
            $conteo->execute($parametros);
            $total = (int) $conteo->fetchColumn();
            $consulta = $conexion->prepare(
                "SELECT u.RPU, u.division_cfe, u.nombre_cfe, u.direccion_cfe, u.poblacion_cfe, u.tarifa_cfe, u.total, u.consumo, cr.anio, cr.mes, v.ccts
                 $base
                 ORDER BY cr.anio DESC, cr.mes DESC, u.nombre_cfe, u.RPU
                 LIMIT :limite OFFSET :offset"
            );
            foreach ($parametros as $clave => $valor) {
                $consulta->bindValue(':' . $clave, $valor, PDO::PARAM_STR);
            }
            $consulta->bindValue(':limite', $porPagina, PDO::PARAM_INT);
            $consulta->bindValue(':offset', $offset, PDO::PARAM_INT);
            $consulta->execute();
            $this->responder([
                'ok' => true,
                'total' => $total,
                'pagina' => $pagina,
                'paginas' => max(1, (int) ceil($total / $porPagina)),
                'disponibles' => $this->disponiblesCatalogoCfe($conexion),
                'rpus' => $consulta->fetchAll()
            ]);
        } catch (Throwable $e) {
            $this->responder(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function sugerirVinculosPaginados(): void
    {
        $this->validarToken();
        try {
            $conexion = Conexion::conectar();
            $this->prepararTablas($conexion);
            $pagina = max(1, (int) ($_POST['pagina'] ?? 1));
            $porPagina = 10;
            $offset = ($pagina - 1) * $porPagina;
            $consultaTotal = $conexion->query(
                'SELECT COUNT(*)
                 FROM (
                    SELECT MAX(id) AS consumo_id
                    FROM cfe_consumos
                    GROUP BY RPU
                 ) ultimos
                 INNER JOIN cfe_consumos cc ON cc.id = ultimos.consumo_id
                 LEFT JOIN (SELECT DISTINCT RPU FROM escuelas_rpu) er ON er.RPU = cc.RPU
                 WHERE er.RPU IS NULL'
            );
            $total = (int) $consultaTotal->fetchColumn();
            $paginas = max(1, (int) ceil($total / $porPagina));
            $pagina = min($pagina, $paginas);
            $offset = ($pagina - 1) * $porPagina;
            $consulta = $conexion->prepare(
                'SELECT cc.RPU, cc.division_cfe, cc.nombre_cfe, cc.direccion_cfe, cc.poblacion_cfe, cc.tarifa_cfe, cc.desde, cc.hasta, cc.total, cc.consumo
                 FROM (
                    SELECT MAX(id) AS consumo_id
                    FROM cfe_consumos
                    GROUP BY RPU
                 ) ultimos
                 INNER JOIN cfe_consumos cc ON cc.id = ultimos.consumo_id
                 LEFT JOIN (SELECT DISTINCT RPU FROM escuelas_rpu) er ON er.RPU = cc.RPU
                 WHERE er.RPU IS NULL
                 ORDER BY cc.id DESC
                 LIMIT :limite OFFSET :offset'
            );
            $consulta->bindValue(':limite', $porPagina, PDO::PARAM_INT);
            $consulta->bindValue(':offset', $offset, PDO::PARAM_INT);
            $consulta->execute();
            $coincidencias = [];
            foreach ($consulta->fetchAll() as $fila) {
                $coincidencias[] = [
                    'rpu' => (string) $fila['RPU'],
                    'cfe' => [
                        'division' => (string) ($fila['division_cfe'] ?? ''),
                        'nombre' => (string) ($fila['nombre_cfe'] ?? ''),
                        'direccion' => (string) ($fila['direccion_cfe'] ?? ''),
                        'poblacion' => (string) ($fila['poblacion_cfe'] ?? ''),
                        'tarifa' => (string) ($fila['tarifa_cfe'] ?? ''),
                        'periodo' => trim((string) ($fila['desde'] ?? '') . ' / ' . (string) ($fila['hasta'] ?? '')),
                        'total' => (float) $fila['total'],
                        'consumo' => (float) $fila['consumo']
                    ],
                    'sugerencias' => $this->sugerencias($conexion, (string) $fila['RPU'], $fila)
                ];
            }
            $this->responder([
                'ok' => true,
                'total' => $total,
                'pagina' => $pagina,
                'paginas' => $paginas,
                'coincidencias' => $coincidencias
            ]);
        } catch (Throwable $e) {
            $this->responder(['ok' => false, 'error' => 'No fue posible generar coincidencias: ' . $e->getMessage()], 500);
        }
    }

    public function opcionesCatalogoCfe(): void
    {
        $this->validarToken();
        try {
            $conexion = Conexion::conectar();
            $this->prepararTablas($conexion);
            $campo = (string) ($_POST['campo'] ?? '');
            $termino = trim((string) ($_POST['termino'] ?? ''));
            $columnas = [
                'nombre' => 'nombre_cfe',
                'direccion' => 'direccion_cfe',
                'poblacion' => 'poblacion_cfe',
                'tarifa' => 'tarifa_cfe',
                'division' => 'division_cfe'
            ];
            if (!isset($columnas[$campo])) {
                $this->responder(['ok' => true, 'opciones' => []]);
            }

            $columna = $columnas[$campo];
            $condiciones = ["u.$columna IS NOT NULL", "u.$columna <> ''"];
            $parametros = $this->filtrosCatalogoCfe($_POST, $campo, 'u', $condiciones);
            if ($termino !== '') {
                $condiciones[] = "u.$columna LIKE :termino";
                $parametros['termino'] = '%' . $termino . '%';
            }
            $where = 'WHERE ' . implode(' AND ', $condiciones);

            $consulta = $conexion->prepare(
                "SELECT DISTINCT u.$columna valor
                 FROM cfe_consumos u
                 LEFT JOIN (
                    SELECT RPU, GROUP_CONCAT(DISTINCT CCT ORDER BY CCT SEPARATOR ' / ') ccts
                    FROM escuelas_rpu
                    GROUP BY RPU
                 ) v ON v.RPU = u.RPU
                 $where
                 ORDER BY u.$columna
                 LIMIT 80"
            );
            $consulta->execute($parametros);
            $this->responder([
                'ok' => true,
                'disponibles' => $this->disponiblesCatalogoCfe($conexion),
                'opciones' => $consulta->fetchAll(PDO::FETCH_COLUMN)
            ]);
        } catch (Throwable $e) {
            $this->responder(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function disponiblesCatalogoCfe(PDO $conexion): array
    {
        return [
            'division' => (int) $conexion->query("SELECT COUNT(*) FROM cfe_consumos WHERE division_cfe IS NOT NULL AND division_cfe <> ''")->fetchColumn(),
            'direccion' => (int) $conexion->query("SELECT COUNT(*) FROM cfe_consumos WHERE direccion_cfe IS NOT NULL AND direccion_cfe <> ''")->fetchColumn(),
            'poblacion' => (int) $conexion->query("SELECT COUNT(*) FROM cfe_consumos WHERE poblacion_cfe IS NOT NULL AND poblacion_cfe <> ''")->fetchColumn(),
            'nombre' => (int) $conexion->query("SELECT COUNT(*) FROM cfe_consumos WHERE nombre_cfe IS NOT NULL AND nombre_cfe <> ''")->fetchColumn(),
            'tarifa' => (int) $conexion->query("SELECT COUNT(*) FROM cfe_consumos WHERE tarifa_cfe IS NOT NULL AND tarifa_cfe <> ''")->fetchColumn()
        ];
    }

    private function filtrosCatalogoCfe(array $datos, string $excluirCampo, string $alias, array &$condiciones): array
    {
        $mapa = [
            'nombre' => 'nombre_cfe',
            'direccion' => 'direccion_cfe',
            'poblacion' => 'poblacion_cfe',
            'tarifa' => 'tarifa_cfe',
            'division' => 'division_cfe'
        ];
        $parametros = [];
        foreach ($mapa as $campo => $columna) {
            if ($campo === $excluirCampo) {
                continue;
            }
            $valor = trim((string) ($datos[$campo] ?? ''));
            if ($valor === '') {
                continue;
            }
            $clave = 'filtro_' . $campo;
            if ($campo === 'tarifa') {
                $condiciones[] = "$alias.$columna = :$clave";
                $parametros[$clave] = $valor;
            } else {
                $condiciones[] = "$alias.$columna LIKE :$clave";
                $parametros[$clave] = '%' . $valor . '%';
            }
        }
        if ((string) ($datos['sin_vinculo'] ?? '') === '1') {
            $condiciones[] = 'v.RPU IS NULL';
        }
        return $parametros;
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
            $consultaEscuela = $conexion->prepare('SELECT id, CCT FROM escuelas WHERE CCT = ? LIMIT 1');
            $consultaEscuela->execute([$cct]);
            $escuela = $consultaEscuela->fetch();
            if (!$escuela) {
                throw new RuntimeException('La escuela seleccionada ya no existe en el padrón maestro.');
            }
            $consulta = $conexion->prepare(
                'INSERT INTO escuelas_rpu (CCT, escuela_id, RPU, nombre_recibo_cfe, poblacion_cfe, tarifa_cfe)
                 VALUES (:cct, :escuela_id, :rpu, :nombre, :poblacion, :tarifa)
                 ON DUPLICATE KEY UPDATE escuela_id = VALUES(escuela_id), nombre_recibo_cfe = VALUES(nombre_recibo_cfe), poblacion_cfe = VALUES(poblacion_cfe), tarifa_cfe = VALUES(tarifa_cfe)'
            );
            $consulta->execute([
                'cct' => $cct,
                'escuela_id' => (int) $escuela['id'],
                'rpu' => $rpu,
                'nombre' => $this->nulo($ultimo['nombre_cfe'] ?? null),
                'poblacion' => $this->nulo($ultimo['poblacion_cfe'] ?? null),
                'tarifa' => $this->nulo($ultimo['tarifa_cfe'] ?? null)
            ]);
            $conexion->prepare('UPDATE cfe_consumos SET CCT = :cct, escuela_id = :escuela_id WHERE RPU = :rpu AND CCT IS NULL')->execute([
                'cct' => $cct,
                'escuela_id' => (int) $escuela['id'],
                'rpu' => $rpu
            ]);

            $this->responder(['ok' => true, 'mensaje' => 'RPU vinculado correctamente.']);
        } catch (Throwable $e) {
            $this->responder(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function vincularMasivo(): void
    {
        $this->validarToken();
        $vinculos = json_decode((string) ($_POST['vinculos'] ?? '[]'), true);
        if (!is_array($vinculos) || !$vinculos) {
            $this->responder(['ok' => false, 'error' => 'No hay vínculos para guardar.'], 422);
        }

        try {
            $conexion = Conexion::conectar();
            $this->prepararTablas($conexion);
            $consultaEscuela = $conexion->prepare('SELECT id, CCT FROM escuelas WHERE CCT = ? LIMIT 1');
            $consultaCfe = $conexion->prepare('SELECT nombre_cfe, poblacion_cfe, tarifa_cfe FROM cfe_consumos WHERE RPU = ? ORDER BY id DESC LIMIT 1');
            $consultaGuardar = $conexion->prepare(
                'INSERT INTO escuelas_rpu (CCT, escuela_id, RPU, nombre_recibo_cfe, poblacion_cfe, tarifa_cfe)
                 VALUES (:cct, :escuela_id, :rpu, :nombre, :poblacion, :tarifa)
                 ON DUPLICATE KEY UPDATE escuela_id = VALUES(escuela_id), nombre_recibo_cfe = VALUES(nombre_recibo_cfe), poblacion_cfe = VALUES(poblacion_cfe), tarifa_cfe = VALUES(tarifa_cfe)'
            );
            $consultaActualizar = $conexion->prepare('UPDATE cfe_consumos SET CCT = :cct, escuela_id = :escuela_id WHERE RPU = :rpu AND CCT IS NULL');
            $conexion->beginTransaction();
            $guardados = 0;
            foreach ($vinculos as $vinculo) {
                $rpu = trim((string) ($vinculo['rpu'] ?? ''));
                $cct = trim((string) ($vinculo['cct'] ?? ''));
                if ($rpu === '' || $cct === '') {
                    continue;
                }
                $consultaEscuela->execute([$cct]);
                $escuela = $consultaEscuela->fetch();
                if (!$escuela) {
                    continue;
                }
                $consultaCfe->execute([$rpu]);
                $cfe = $consultaCfe->fetch() ?: [];
                $consultaGuardar->execute([
                    'cct' => $cct,
                    'escuela_id' => (int) $escuela['id'],
                    'rpu' => $rpu,
                    'nombre' => $this->nulo($cfe['nombre_cfe'] ?? null),
                    'poblacion' => $this->nulo($cfe['poblacion_cfe'] ?? null),
                    'tarifa' => $this->nulo($cfe['tarifa_cfe'] ?? null)
                ]);
                $consultaActualizar->execute(['cct' => $cct, 'escuela_id' => (int) $escuela['id'], 'rpu' => $rpu]);
                $guardados++;
            }
            $conexion->commit();
            $this->responder(['ok' => true, 'total' => $guardados, 'mensaje' => $guardados . ' vínculos guardados.']);
        } catch (Throwable $e) {
            if (isset($conexion) && $conexion->inTransaction()) {
                $conexion->rollBack();
            }
            $this->responder(['ok' => false, 'error' => 'No fue posible guardar los vínculos: ' . $e->getMessage()], 500);
        }
    }

    public function desvincular(): void
    {
        $this->validarToken();
        $rpu = trim((string) ($_POST['rpu'] ?? ''));
        $cct = trim((string) ($_POST['cct'] ?? ''));
        if ($rpu === '' || $cct === '') {
            $this->responder(['ok' => false, 'error' => 'Faltan RPU o CCT para desvincular.'], 422);
        }

        try {
            $conexion = Conexion::conectar();
            $this->prepararTablas($conexion);
            $conexion->beginTransaction();
            $consulta = $conexion->prepare('DELETE FROM escuelas_rpu WHERE RPU = ? AND CCT = ?');
            $consulta->execute([$rpu, $cct]);
            if ($consulta->rowCount() === 0) {
                throw new RuntimeException('El vínculo ya no existe.');
            }
            $siguiente = $conexion->prepare('SELECT CCT, escuela_id FROM escuelas_rpu WHERE RPU = ? ORDER BY id LIMIT 1');
            $siguiente->execute([$rpu]);
            $restante = $siguiente->fetch();
            if ($restante) {
                $conexion->prepare('UPDATE cfe_consumos SET CCT = :cct, escuela_id = :escuela_id WHERE RPU = :rpu AND CCT = :cct_anterior')->execute([
                    'cct' => $restante['CCT'],
                    'escuela_id' => $restante['escuela_id'],
                    'rpu' => $rpu,
                    'cct_anterior' => $cct
                ]);
            } else {
                $conexion->prepare('UPDATE cfe_consumos SET CCT = NULL, escuela_id = NULL WHERE RPU = :rpu AND CCT = :cct')->execute(['rpu' => $rpu, 'cct' => $cct]);
            }
            $conexion->commit();
            $this->responder(['ok' => true, 'mensaje' => 'Vínculo eliminado.']);
        } catch (Throwable $e) {
            if (isset($conexion) && $conexion->inTransaction()) {
                $conexion->rollBack();
            }
            $this->responder(['ok' => false, 'error' => 'No fue posible desvincular: ' . $e->getMessage()], 500);
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
                INDEX idx_cfe_consumos_reporte (reporte_id)
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
        $this->asegurarColumna($conexion, 'cfe_consumos', 'escuela_id', 'BIGINT UNSIGNED NULL');
        $this->asegurarColumna($conexion, 'escuelas_rpu', 'escuela_id', 'BIGINT UNSIGNED NULL');
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
        if (!$ultimo) {
            return [];
        }
        $poblacion = trim((string) ($ultimo['poblacion_cfe'] ?? ''));
        $nombre = trim((string) ($ultimo['nombre_cfe'] ?? ''));
        $direccion = trim((string) ($ultimo['direccion_cfe'] ?? ''));
        $referencia = $this->referenciaGeograficaCfe($poblacion);
        if ($referencia['localidad'] === '' && $referencia['municipio'] === '') {
            return [];
        }
        $filtrosGeograficos = [];
        if ($referencia['localidad'] !== '' && $referencia['municipio'] !== '') {
            $filtrosGeograficos[] = [
                'NOMBRELOC LIKE :localidad AND NOMBREMUN LIKE :municipio',
                ['localidad' => '%' . $referencia['localidad'] . '%', 'municipio' => '%' . $referencia['municipio'] . '%']
            ];
        } elseif ($referencia['localidad'] !== '') {
            $filtrosGeograficos[] = ['NOMBRELOC LIKE :localidad', ['localidad' => '%' . $referencia['localidad'] . '%']];
        } elseif ($referencia['municipio'] !== '') {
            $filtrosGeograficos[] = ['NOMBREMUN LIKE :municipio', ['municipio' => '%' . $referencia['municipio'] . '%']];
        }
        $filas = [];
        foreach ($filtrosGeograficos as [$filtro, $parametros]) {
            $consulta = $conexion->prepare(
                'SELECT CCT, NOMBRECT, DOMICILIO, NOMBREMUN, NOMBRELOC, STATUS, SUBNIVEL, NIVEL, HOMO, TURNO, ZONA, SECTOR, ORIGEN, CLASIFICACION, TIPOCT
                 FROM escuelas
                 WHERE ' . $filtro . '
                 ORDER BY CASE WHEN CLASIFICACION = \'ESCUELA BASICA OFICIALIZADA (ACTIVA)\' THEN 0 WHEN CLASIFICACION LIKE \'ESCUELA%\' THEN 1 ELSE 2 END, STATUS DESC
                 LIMIT 300'
            );
            $consulta->execute($parametros);
            $filas = $consulta->fetchAll();
            if ($filas) {
                break;
            }
        }
        $nivelCfe = $this->identificarNivelCfe($nombre);
        $requiereIndigena = $this->requiereSubnivelIndigena($nombre);
        $sugerencias = [];
        foreach ($filas as $fila) {
            $evaluacion = $this->puntaje($nombre, $referencia['localidad'], $referencia['municipio'], $direccion, $nivelCfe, $fila);
            if ($evaluacion['score'] >= 25) {
                $sugerencias[] = $this->escuelaDesdeFila($fila, $evaluacion['score'], 'Sugerencia por padrón maestro', $evaluacion);
            }
        }
        $fisicas = array_values(array_filter($sugerencias, fn (array $escuela): bool => !($escuela['administrativa'] ?? false)));
        if ($requiereIndigena) {
            $fisicas = array_values(array_filter($fisicas, fn (array $escuela): bool => str_contains($this->normalizar((string) ($escuela['subnivel'] ?? '')), 'INDIGENA')));
        }
        if (!$fisicas) {
            return [];
        }
        $porNivel = array_values(array_filter($fisicas, fn (array $escuela): bool => $escuela['nivel_coincide'] ?? false));
        if ($nivelCfe !== null && $porNivel) {
            $sugerencias = $porNivel;
        } else {
            $sugerencias = $fisicas;
        }
        usort($sugerencias, fn (array $a, array $b): int => [$b['score'], $b['similitud'], $b['activa']] <=> [$a['score'], $a['similitud'], $a['activa']]);
        return array_slice($sugerencias, 0, 3);
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

    private function escuelaDesdeFila(array $fila, int $score, string $origen, array $evaluacion = []): array
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
            'clasificacion' => (string) ($fila['CLASIFICACION'] ?? ''),
            'fuente' => (string) ($fila['ORIGEN'] ?? 'Catalogo local SEG/Oficializacion'),
            'score' => $score,
            'similitud' => (float) ($evaluacion['similitud'] ?? $score),
            'nivel_coincide' => (bool) ($evaluacion['nivel_coincide'] ?? false),
            'ubicacion' => (string) ($evaluacion['ubicacion'] ?? ''),
            'administrativa' => (bool) ($evaluacion['administrativa'] ?? false),
            'activa' => (bool) ($evaluacion['activa'] ?? false),
            'origen' => $origen
        ];
    }

    private function puntaje(string $nombreCfe, string $localidadCfe, string $municipioCfe, string $direccionCfe, ?string $nivelCfe, array $escuela): array
    {
        $nombreBase = $this->normalizar($nombreCfe);
        $nombreEscuela = $this->normalizar((string) ($escuela['NOMBRECT'] ?? ''));
        similar_text($nombreBase, $nombreEscuela, $similitud);
        $localidad = $this->normalizar((string) ($escuela['NOMBRELOC'] ?? ''));
        $municipio = $this->normalizar((string) ($escuela['NOMBREMUN'] ?? ''));
        $referenciaLocalidad = $this->normalizar($localidadCfe);
        $referenciaMunicipio = $this->normalizar($municipioCfe);
        $coincideLocalidad = $referenciaLocalidad !== '' && $localidad !== '' && ($localidad === $referenciaLocalidad || str_contains($referenciaLocalidad, $localidad) || str_contains($localidad, $referenciaLocalidad));
        $coincideMunicipio = $referenciaMunicipio !== '' && $municipio !== '' && ($municipio === $referenciaMunicipio || str_contains($referenciaMunicipio, $municipio) || str_contains($municipio, $referenciaMunicipio));
        $ubicacion = $coincideLocalidad ? 'Misma localidad/población' : ($coincideMunicipio ? 'Municipio coincidente' : 'Nombre o domicilio cercano');
        $nivelCoincide = $this->coincideNivelCfe($nivelCfe, $escuela);
        $activa = $this->estaActiva((string) ($escuela['STATUS'] ?? ''));
        $administrativa = $this->esAdministrativa($escuela);
        $score = $similitud + ($nivelCoincide ? 60 : 0) + ($activa ? 10 : 0) + ($coincideLocalidad ? 35 : ($coincideMunicipio ? 18 : 0));
        if ($administrativa) {
            $score -= 1000;
        }
        $direccion = $this->normalizar($direccionCfe);
        $domicilio = $this->normalizar((string) ($escuela['DOMICILIO'] ?? ''));
        if ($direccion !== '' && $domicilio !== '') {
            $palabrasCfe = array_unique(array_filter(explode(' ', $direccion), fn (string $palabra): bool => strlen($palabra) >= 4));
            $palabrasEscuela = array_unique(array_filter(explode(' ', $domicilio), fn (string $palabra): bool => strlen($palabra) >= 4));
            $coincidencias = count(array_intersect($palabrasCfe, $palabrasEscuela));
            $score += min(15, $coincidencias * 5);
        }
        if ($this->normalizar((string) ($escuela['CLASIFICACION'] ?? '')) === 'ESCUELA BASICA OFICIALIZADA ACTIVA') {
            $score += 8;
        }
        return [
            'score' => max(0, min(100, (int) round($score))),
            'similitud' => round($similitud, 2),
            'nivel_coincide' => $nivelCoincide,
            'ubicacion' => $ubicacion,
            'administrativa' => $administrativa,
            'activa' => $activa
        ];
    }

    private function referenciaGeograficaCfe(string $poblacion): array
    {
        $texto = trim($poblacion);
        $localidad = $texto;
        $municipio = '';
        if (preg_match('/^(.*?)\s*\(([^)]+)\)\s*$/u', $texto, $coincidencia)) {
            $localidad = trim($coincidencia[1]);
            $municipio = trim($coincidencia[2]);
        }
        return [
            'localidad' => $localidad,
            'municipio' => $municipio
        ];
    }

    private function identificarNivelCfe(string $nombre): ?string
    {
        $texto = $this->normalizar($nombre);
        if (str_contains($texto, 'TELE') || preg_match('/(^| )TV( |$)/', $texto) || preg_match('/(^| )TS( |$)/', $texto)) {
            return 'TELESECUNDARIA';
        }
        if (preg_match('/(^| )JN( |$)/', $texto) || str_contains($texto, 'JARDIN') || str_contains($texto, 'KINDER') || str_contains($texto, 'PREES')) {
            return 'PREESCOLAR';
        }
        if (str_contains($texto, 'PRIM') || str_contains($texto, 'FED REG')) {
            return 'PRIMARIA';
        }
        if (str_contains($texto, 'SEC') || str_contains($texto, 'TEC') || str_contains($texto, 'GRAL')) {
            return 'SECUNDARIA';
        }
        return null;
    }

    private function requiereSubnivelIndigena(string $nombre): bool
    {
        return str_contains($this->normalizar($nombre), 'INDIGENA');
    }

    private function coincideNivelCfe(?string $nivelCfe, array $escuela): bool
    {
        if ($nivelCfe === null) {
            return false;
        }
        $nivel = $this->normalizar((string) ($escuela['NIVEL'] ?? ''));
        $subnivel = $this->normalizar((string) ($escuela['SUBNIVEL'] ?? ''));
        if ($nivelCfe === 'TELESECUNDARIA') {
            return $nivel === 'TELESECUNDARIA' || $subnivel === 'TELESECUNDARIA';
        }
        return $nivel === $nivelCfe || str_contains($subnivel, $nivelCfe);
    }

    private function estaActiva(string $status): bool
    {
        return in_array($this->normalizar($status), ['1', 'ACTIVO', 'ACTIVA'], true);
    }

    private function esAdministrativa(array $escuela): bool
    {
        $homo = $this->normalizar((string) ($escuela['HOMO'] ?? ''));
        $tipo = $this->normalizar((string) ($escuela['TIPOCT'] ?? ''));
        $clasificacion = $this->normalizar((string) ($escuela['CLASIFICACION'] ?? ''));
        return str_starts_with($homo, 'F') || ($tipo !== '' && $tipo !== 'ESCUELA') || str_contains($clasificacion, 'EDIFICIO ADMINISTRATIVO');
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

    private function tipoRiesgo(bool $riesgoIncremento, bool $riesgoBajoConsumo, bool $riesgoPagoMinimo, bool $consumoCeroActual): string
    {
        if ($consumoCeroActual) {
            return 'sin_consumo';
        }
        if ($riesgoIncremento && ($riesgoBajoConsumo || $riesgoPagoMinimo)) {
            return 'mixto';
        }
        if ($riesgoPagoMinimo) {
            return 'pago_minimo';
        }
        if ($riesgoBajoConsumo) {
            return 'consumo_bajo';
        }
        return 'incremento';
    }

    private function motivoRiesgo(bool $vinculado, int $alertas, int $maxSeveridad, float $subioTotal, float $total, float $incrementoPorcentaje, bool $riesgoBajoConsumo, int $periodosBajoConsumo, float $consumoActual, bool $riesgoPagoMinimo, int $periodosPagoMinimo): string
    {
        $motivos = [];
        if ($incrementoPorcentaje >= 70) {
            $motivos[] = 'incremento critico ' . round($incrementoPorcentaje, 1) . '%';
        } elseif ($incrementoPorcentaje >= 50) {
            $motivos[] = 'incremento alto ' . round($incrementoPorcentaje, 1) . '%';
        }
        if ($riesgoBajoConsumo) {
            $motivos[] = $consumoActual <= 0 ? 'sin consumo actual' : 'consumo muy bajo';
            if ($periodosBajoConsumo >= 2) {
                $motivos[] = $periodosBajoConsumo . ' periodos bajos';
            }
        }
        if ($riesgoPagoMinimo) {
            $motivos[] = 'pago minimo';
            if ($periodosPagoMinimo >= 2) {
                $motivos[] = $periodosPagoMinimo . ' periodos con minimo';
            }
        }
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
            $motivos[] = 'subio ' . number_format($subioTotal, 2, '.', ',');
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

if ($accion === 'buscar_catalogo_cfe') {
    $controlador->buscarCatalogoCfe();
}

if ($accion === 'sugerir_vinculos_paginados') {
    $controlador->sugerirVinculosPaginados();
}

if ($accion === 'opciones_catalogo_cfe') {
    $controlador->opcionesCatalogoCfe();
}

if ($accion === 'vincular_rpu') {
    $controlador->vincular();
}

if ($accion === 'vincular_rpus_masivo') {
    $controlador->vincularMasivo();
}

if ($accion === 'desvincular_rpu') {
    $controlador->desvincular();
}

http_response_code(400);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => false, 'error' => 'Accion no reconocida.'], JSON_UNESCAPED_UNICODE);
