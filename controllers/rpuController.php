<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/services/auth.php';
segRequireLogin();

require_once dirname(__DIR__) . '/services/conexion.php';

session_write_close();

class RpuController
{
    public function sugerirMalos(): void
    {
        $this->validarToken();
        try {
            $conexion = Conexion::conectar();
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
            $historial = $this->historial($conexion, $rpu);
            $vinculos = $this->vinculos($conexion, $rpu);
            $ultimoPeriodoSistema = $conexion->query('SELECT anio, mes FROM cfe_reportes ORDER BY anio DESC, mes DESC, id DESC LIMIT 1')->fetch();
            $ultimo = $historial[0] ?? null;
            $ubicacionPlano = $this->ubicacionPlano($conexion, $rpu);
            if ($ultimo !== null && $ubicacionPlano !== []) {
                $ultimo['plano'] = $ubicacionPlano;
            }
            $cctsVinculados = array_flip(array_map(static fn (array $vinculo): string => (string) $vinculo['cct'], $vinculos));
            $incluirSugerencias = (string) ($_POST['incluir_sugerencias'] ?? '1') !== '0';
            $sugerencias = $incluirSugerencias
                ? array_values(array_filter(
                    $this->sugerencias($conexion, $rpu, $ultimo),
                    static fn (array $escuela): bool => !isset($cctsVinculados[(string) $escuela['cct']])
                ))
                : [];
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
                    'periodo' => $ultimo ? sprintf('%04d-%02d', (int) $ultimo['anio'], (int) $ultimo['mes']) : '',
                    'plano' => $ubicacionPlano
                ],
                'vinculos' => $vinculos,
                'sugerencias' => $sugerencias,
                'historial' => array_reverse($historial),
                'mapa' => $mapa,
                'resumen' => $this->resumen($historial),
                'ultimo_periodo_sistema' => $ultimoPeriodoSistema
                    ? sprintf('%04d-%02d', (int) $ultimoPeriodoSistema['anio'], (int) $ultimoPeriodoSistema['mes'])
                    : ''
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
            $this->asegurarIndice($conexion, 'cfe_consumos', 'idx_cfe_consumos_rpu_id', 'RPU, id');
            $this->asegurarIndice($conexion, 'escuelas', 'idx_escuelas_localidad_municipio', 'NOMBRELOC, NOMBREMUN');
            $this->asegurarIndice($conexion, 'escuelas', 'idx_escuelas_municipio_localidad', 'NOMBREMUN, NOMBRELOC');
            $pagina = max(1, (int) ($_POST['pagina'] ?? 1));
            $porPagina = 10;
            $offset = ($pagina - 1) * $porPagina;
            $consultaTotal = $conexion->query(
                'SELECT COUNT(*)
                 FROM (
                    SELECT MAX(id) AS consumo_id
                    FROM cfe_consumos FORCE INDEX (idx_cfe_consumos_rpu_id)
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
                'SELECT cc.RPU, cc.division_cfe, cc.nombre_cfe, cc.direccion_cfe, cc.poblacion_cfe, cc.tarifa_cfe, cc.desde, cc.hasta, cc.total, cc.consumo,
                        pd.direccion_plano, pd.poblacion_plano, pd.municipio_plano, pd.estado_plano, pd.colonia_plano, pd.calle_1, pd.calle_2
                 FROM (
                    SELECT MAX(id) AS consumo_id
                    FROM cfe_consumos FORCE INDEX (idx_cfe_consumos_rpu_id)
                    GROUP BY RPU
                 ) ultimos
                 INNER JOIN cfe_consumos cc ON cc.id = ultimos.consumo_id
                 LEFT JOIN cfe_plano_detalles pd ON pd.id = (
                    SELECT detalle.id
                    FROM cfe_plano_detalles detalle
                    WHERE detalle.consumo_id = cc.id
                    ORDER BY detalle.actualizado_en DESC, detalle.id DESC
                    LIMIT 1
                 )
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
                $referencia = $this->referenciaGeograficaCfe((string) ($fila['poblacion_cfe'] ?? ''));
                $coincidencias[] = [
                    'rpu' => (string) $fila['RPU'],
                    'cfe' => [
                        'division' => (string) ($fila['division_cfe'] ?? ''),
                        'nombre' => (string) ($fila['nombre_cfe'] ?? ''),
                        'direccion' => (string) ($fila['direccion_cfe'] ?? ''),
                        'poblacion' => (string) ($fila['poblacion_cfe'] ?? ''),
                        'tarifa' => (string) ($fila['tarifa_cfe'] ?? ''),
                        'plano' => [
                            'direccion' => (string) ($fila['direccion_plano'] ?? ''),
                            'poblacion' => (string) ($fila['poblacion_plano'] ?? ''),
                            'municipio' => (string) ($fila['municipio_plano'] ?? ''),
                            'estado' => (string) ($fila['estado_plano'] ?? ''),
                            'colonia' => (string) ($fila['colonia_plano'] ?? ''),
                            'calle_1' => (string) ($fila['calle_1'] ?? ''),
                            'calle_2' => (string) ($fila['calle_2'] ?? '')
                        ],
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

    public function buscarRpuPorNombre(): void
    {
        $this->validarToken();
        try {
            $nombre = trim((string) ($_POST['nombre'] ?? ''));
            if (mb_strlen($nombre) < 3) {
                $this->responder(['ok' => true, 'resultados' => []]);
            }
            $conexion = Conexion::conectar();
            $consulta = $conexion->prepare(
                'SELECT cc.RPU,
                        MAX(cc.nombre_cfe) AS nombre_cfe,
                        MAX(cc.direccion_cfe) AS direccion_cfe,
                        MAX(cc.poblacion_cfe) AS poblacion_cfe,
                        MAX(cc.tarifa_cfe) AS tarifa_cfe,
                        COUNT(DISTINCT cc.reporte_id) AS total_reportes,
                        MAX(CONCAT(cr.anio, "-", LPAD(cr.mes, 2, "0"))) AS ultimo_periodo,
                        GROUP_CONCAT(DISTINCT CONCAT(cr.anio, "-", LPAD(cr.mes, 2, "0")) ORDER BY cr.anio DESC, cr.mes DESC SEPARATOR " | ") AS periodos
                 FROM cfe_consumos cc
                 INNER JOIN cfe_reportes cr ON cr.id = cc.reporte_id
                 WHERE cc.nombre_cfe LIKE :nombre
                 GROUP BY cc.RPU
                 ORDER BY ultimo_periodo DESC, nombre_cfe, cc.RPU
                 LIMIT 100'
            );
            $consulta->execute(['nombre' => '%' . $nombre . '%']);
            $this->responder(['ok' => true, 'resultados' => $consulta->fetchAll()]);
        } catch (Throwable $e) {
            $this->responder(['ok' => false, 'error' => 'No fue posible buscar servicios por nombre: ' . $e->getMessage()], 500);
        }
    }

    public function opcionesCatalogoCfe(): void
    {
        $this->validarToken();
        try {
            $conexion = Conexion::conectar();
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
            $cct = strtoupper(trim((string) ($_POST['cct'] ?? '')));
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

            $this->responder(['ok' => true, 'mensaje' => 'RPU vinculado correctamente con el CCT ' . $cct . '.']);
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

    public function autoVincularSugerencias(): void
    {
        $this->validarToken();
        set_time_limit(0);
        try {
            $conexion = Conexion::conectar();
            $this->prepararTablas($conexion);
            $calculo = $this->obtenerAutoVinculos($conexion);
            $consultaGuardar = $conexion->prepare(
                'INSERT INTO escuelas_rpu (CCT, escuela_id, RPU, nombre_recibo_cfe, poblacion_cfe, tarifa_cfe)
                 VALUES (:cct, :escuela_id, :rpu, :nombre, :poblacion, :tarifa)
                 ON DUPLICATE KEY UPDATE escuela_id = VALUES(escuela_id), nombre_recibo_cfe = VALUES(nombre_recibo_cfe), poblacion_cfe = VALUES(poblacion_cfe), tarifa_cfe = VALUES(tarifa_cfe)'
            );
            $consultaActualizar = $conexion->prepare('UPDATE cfe_consumos SET CCT = :cct, escuela_id = :escuela_id WHERE RPU = :rpu AND CCT IS NULL');
            $conexion->beginTransaction();
            $guardados = 0;
            foreach ($calculo['vinculos'] as $vinculo) {
                $consultaGuardar->execute([
                    'cct' => $vinculo['cct'],
                    'escuela_id' => $vinculo['escuela_id'],
                    'rpu' => $vinculo['rpu'],
                    'nombre' => $this->nulo($vinculo['nombre_cfe']),
                    'poblacion' => $this->nulo($vinculo['poblacion_cfe']),
                    'tarifa' => $this->nulo($vinculo['tarifa_cfe'])
                ]);
                $consultaActualizar->execute([
                    'cct' => $vinculo['cct'],
                    'escuela_id' => $vinculo['escuela_id'],
                    'rpu' => $vinculo['rpu']
                ]);
                $guardados++;
            }
            $conexion->commit();
            $this->responder(['ok' => true, 'total' => $guardados, 'pendientes' => $calculo['pendientes'], 'mensaje' => $guardados . ' vínculos guardados automáticamente.']);
        } catch (Throwable $e) {
            if (isset($conexion) && $conexion->inTransaction()) {
                $conexion->rollBack();
            }
            $this->responder(['ok' => false, 'error' => 'No fue posible auto-vincular: ' . $e->getMessage()], 500);
        }
    }

    public function previsualizarAutoVinculos(): void
    {
        $this->validarToken();
        set_time_limit(0);
        try {
            $conexion = Conexion::conectar();
            $this->prepararTablas($conexion);
            $calculo = $this->obtenerAutoVinculos($conexion);
            $this->responder(['ok' => true, 'total' => count($calculo['vinculos']), 'pendientes' => $calculo['pendientes']]);
        } catch (Throwable $e) {
            $this->responder(['ok' => false, 'error' => 'No fue posible calcular la auto-vinculación: ' . $e->getMessage()], 500);
        }
    }

    private function obtenerAutoVinculos(PDO $conexion): array
    {
        $pendientes = $conexion->query(
            'SELECT cc.RPU, cc.nombre_cfe, cc.direccion_cfe, cc.poblacion_cfe, cc.tarifa_cfe
             FROM (
                SELECT MAX(id) AS consumo_id
                FROM cfe_consumos
                GROUP BY RPU
             ) ultimos
             INNER JOIN cfe_consumos cc ON cc.id = ultimos.consumo_id
             LEFT JOIN (SELECT DISTINCT RPU FROM escuelas_rpu) er ON er.RPU = cc.RPU
             WHERE er.RPU IS NULL'
        )->fetchAll();
        if (!$pendientes) {
            return ['pendientes' => 0, 'vinculos' => []];
        }

        $escuelas = $conexion->query(
            'SELECT id, CCT, NOMBRECT, DOMICILIO, NOMBREMUN, NOMBRELOC, STATUS, SUBNIVEL, NIVEL, HOMO, TURNO, ZONA, SECTOR, ORIGEN, CLASIFICACION, TIPOCT
             FROM escuelas'
        )->fetchAll();
        $porLocalidad = [];
        $porMunicipio = [];
        $porLocalidadMunicipio = [];
        $paresUbicacion = [];
        $paresPorPrefijoLocalidad = [];
        foreach ($escuelas as $escuela) {
            $localidad = $this->normalizar((string) ($escuela['NOMBRELOC'] ?? ''));
            $municipio = $this->normalizar((string) ($escuela['NOMBREMUN'] ?? ''));
            if ($localidad !== '') {
                $porLocalidad[$localidad][] = $escuela;
            }
            if ($municipio !== '') {
                $porMunicipio[$municipio][] = $escuela;
            }
            if ($localidad !== '') {
                $llave = $localidad . '|' . $municipio;
                $porLocalidadMunicipio[$llave][] = $escuela;
                $paresUbicacion[$llave] = ['localidad' => $localidad, 'municipio' => $municipio];
                $paresPorPrefijoLocalidad[substr($localidad, 0, 8)][$llave] = true;
            }
        }

        $vinculos = [];
        foreach ($pendientes as $rpuCfe) {
            $referencia = $this->referenciaGeograficaCfe((string) ($rpuCfe['poblacion_cfe'] ?? ''));
            $localidad = $this->normalizar($referencia['localidad']);
            $municipio = $this->normalizar($referencia['municipio']);
            if ($localidad === '' && $municipio === '') {
                continue;
            }
            if ($localidad !== '' && $municipio !== '') {
                $filas = $porLocalidadMunicipio[$localidad . '|' . $municipio] ?? [];
            } elseif ($localidad !== '') {
                $filas = $porLocalidad[$localidad] ?? [];
            } else {
                $filas = $porMunicipio[$municipio] ?? [];
            }
            if (!$filas) {
                $filas = $this->filasPorUbicacionCompatible(
                    $porLocalidadMunicipio,
                    $paresUbicacion,
                    $paresPorPrefijoLocalidad,
                    $localidad,
                    $municipio
                );
            }
            $sugerida = $this->evaluarSugerencias(
                $filas,
                (string) ($rpuCfe['nombre_cfe'] ?? ''),
                $referencia,
                (string) ($rpuCfe['direccion_cfe'] ?? '')
            )[0] ?? null;
            if (!$sugerida || (float) ($sugerida['confianza'] ?? 0) < 70 || empty($sugerida['ubicacion_confirmada']) || (int) $sugerida['escuela_id'] <= 0) {
                continue;
            }
            $vinculos[] = [
                'rpu' => (string) $rpuCfe['RPU'],
                'cct' => (string) $sugerida['cct'],
                'escuela_id' => (int) $sugerida['escuela_id'],
                'nombre_cfe' => (string) ($rpuCfe['nombre_cfe'] ?? ''),
                'poblacion_cfe' => (string) ($rpuCfe['poblacion_cfe'] ?? ''),
                'tarifa_cfe' => (string) ($rpuCfe['tarifa_cfe'] ?? '')
            ];
        }
        return ['pendientes' => count($pendientes), 'vinculos' => $vinculos];
    }

    private function filasPorUbicacionCompatible(array $porUbicacion, array $ubicaciones, array $paresPorPrefijoLocalidad, string $localidad, string $municipio): array
    {
        $filas = [];
        $llaves = $localidad !== '' ? array_keys($paresPorPrefijoLocalidad[substr($localidad, 0, 8)] ?? []) : array_keys($ubicaciones);
        foreach ($llaves as $llave) {
            $ubicacion = $ubicaciones[$llave];
            if ($localidad !== '' && !$this->coincideTextoUbicacion($ubicacion['localidad'], $localidad)) {
                continue;
            }
            if ($municipio !== '' && !$this->coincideTextoUbicacion($ubicacion['municipio'], $municipio)) {
                continue;
            }
            foreach ($porUbicacion[$llave] as $fila) {
                $filas[] = $fila;
            }
        }
        return $filas;
    }

    private function coincideTextoUbicacion(string $catalogo, string $referencia): bool
    {
        if (str_contains($catalogo, $referencia) || str_contains($referencia, $catalogo)) {
            return true;
        }
        $longitud = min(strlen($catalogo), strlen($referencia));
        if ($longitud < 8) {
            return false;
        }
        return levenshtein($catalogo, $referencia) <= max(1, (int) floor($longitud * 0.12));
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
        $this->asegurarIndice($conexion, 'cfe_consumos', 'idx_cfe_consumos_rpu_id', 'RPU, id');
        $this->asegurarIndice($conexion, 'escuelas', 'idx_escuelas_localidad_municipio', 'NOMBRELOC, NOMBREMUN');
        $this->asegurarIndice($conexion, 'escuelas', 'idx_escuelas_municipio_localidad', 'NOMBREMUN, NOMBRELOC');
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

    private function asegurarIndice(PDO $conexion, string $tabla, string $indice, string $columnas): void
    {
        $consulta = $conexion->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
        );
        $consulta->execute([$tabla, $indice]);
        if ((int) $consulta->fetchColumn() === 0) {
            $conexion->exec('ALTER TABLE `' . $tabla . '` ADD INDEX `' . $indice . '` (' . $columnas . ')');
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

    public function previsualizarExportacionAnual(): void
    {
        $this->validarToken();
        try {
            $rpus = $this->rpusExportacion();
            $datos = $this->datosExportacionAnual(Conexion::conectar(), $rpus, $this->rangoExportacionAnual());
            $this->responder([
                'ok' => true,
                'rpus' => array_map(static fn (array $fila): array => [
                    'rpu' => $fila['rpu'],
                    'nombre' => $fila['nombre'],
                    'encontrado' => $fila['encontrado'],
                    'ultimo_reporte' => $fila['ultimo_reporte']
                ], $datos['filas'])
            ]);
        } catch (Throwable $e) {
            $this->responder(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function exportarRpusAnual(): void
    {
        $this->validarToken();
        try {
            $datos = $this->datosExportacionAnual(Conexion::conectar(), $this->rpusExportacion(), $this->rangoExportacionAnual());
            $datos = $this->filtrarExportacionAnual($datos, (string) ($_POST['filtro_encontrados'] ?? 'all'));
            if (!$datos['filas']) {
                throw new RuntimeException('No hay RPUs para el filtro seleccionado.');
            }
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="reporte_anual_rpus.xls"');
            header('Cache-Control: max-age=0');
            echo $this->excelExportacionAnual($datos);
            exit;
        } catch (Throwable $e) {
            $this->responder(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    private function filtrarExportacionAnual(array $datos, string $filtro): array
    {
        if (!in_array($filtro, ['all', 'found', 'missing'], true)) {
            $filtro = 'all';
        }
        $filas = array_values(array_filter($datos['filas'], static fn (array $fila): bool => $filtro === 'all' || ($filtro === 'found' ? !empty($fila['encontrado']) : empty($fila['encontrado']))));
        foreach ($filas as $indice => &$fila) {
            $fila['no'] = $indice + 1;
        }
        unset($fila);
        $datos['filas'] = $filas;
        return $datos;
    }

    private function rangoExportacionAnual(): array
    {
        $inicio = [(int) ($_POST['anio_inicio'] ?? 2021), (int) ($_POST['mes_inicio'] ?? 1)];
        $fin = [(int) ($_POST['anio_fin'] ?? date('Y')), (int) ($_POST['mes_fin'] ?? date('n'))];
        foreach ([$inicio, $fin] as [$anio, $mes]) {
            if ($anio < 2021 || $anio > 2100 || $mes < 1 || $mes > 12) {
                throw new RuntimeException('Selecciona un rango de meses valido.');
            }
        }
        if (($inicio[0] * 100 + $inicio[1]) > ($fin[0] * 100 + $fin[1])) {
            throw new RuntimeException('El periodo inicial debe ser anterior al periodo final.');
        }
        return ['inicio_anio' => $inicio[0], 'inicio_mes' => $inicio[1], 'fin_anio' => $fin[0], 'fin_mes' => $fin[1]];
    }

    private function rpusExportacion(): array
    {
        $texto = strtoupper(trim((string) ($_POST['rpus'] ?? '')));
        $rpus = preg_split('/[\s,;]+/', $texto) ?: [];
        $rpus = array_values(array_unique(array_filter($rpus, static fn (string $rpu): bool => preg_match('/^[A-Z0-9]{4,20}$/', $rpu) === 1)));
        if (!$rpus) {
            throw new RuntimeException('Ingresa al menos un RPU válido.');
        }
        if (count($rpus) > 1000) {
            throw new RuntimeException('La exportación admite hasta 1,000 RPUs por archivo.');
        }
        return $rpus;
    }

    private function datosExportacionAnual(PDO $conexion, array $rpus, array $rango): array
    {
        $marcadores = implode(', ', array_fill(0, count($rpus), '?'));
        $parametrosRango = [$rango['inicio_anio'], $rango['inicio_anio'], $rango['inicio_mes'], $rango['fin_anio'], $rango['fin_anio'], $rango['fin_mes']];
        $condicionPeriodo = '((cr.anio > ?) OR (cr.anio = ? AND cr.mes >= ?)) AND ((cr.anio < ?) OR (cr.anio = ? AND cr.mes <= ?))';
        $consultaAnios = $conexion->prepare('SELECT DISTINCT anio FROM cfe_reportes cr WHERE ' . $condicionPeriodo . ' ORDER BY anio');
        $consultaAnios->execute($parametrosRango);
        $anios = array_map(static fn (mixed $anio): int => (int) $anio, $consultaAnios->fetchAll(PDO::FETCH_COLUMN));

        $consultaDatos = $conexion->prepare(
            'SELECT cc.RPU, cc.nombre_cfe, cc.direccion_cfe, cc.poblacion_cfe, cr.anio, cr.mes, cc.id
             FROM cfe_consumos cc
             INNER JOIN cfe_reportes cr ON cr.id = cc.reporte_id
              WHERE cc.RPU IN (' . $marcadores . ') AND ' . $condicionPeriodo . '
              ORDER BY cc.RPU, cr.anio DESC, cr.mes DESC, cc.id DESC'
        );
        $consultaDatos->execute(array_merge($rpus, $parametrosRango));
        $servicios = [];
        foreach ($consultaDatos->fetchAll() as $fila) {
            $rpu = (string) $fila['RPU'];
            if (!isset($servicios[$rpu])) {
                $servicios[$rpu] = [
                    'nombre' => trim((string) ($fila['nombre_cfe'] ?? '')),
                    'direccion' => trim((string) ($fila['direccion_cfe'] ?? '')),
                    'poblacion' => trim((string) ($fila['poblacion_cfe'] ?? '')),
                    'ultimo_reporte' => sprintf('%04d-%02d', (int) $fila['anio'], (int) $fila['mes'])
                ];
            }
        }

        $consultaTotales = $conexion->prepare(
            'SELECT cc.RPU, cr.anio, SUM(cc.total) total
             FROM cfe_consumos cc
             INNER JOIN cfe_reportes cr ON cr.id = cc.reporte_id
              WHERE cc.RPU IN (' . $marcadores . ') AND ' . $condicionPeriodo . '
              GROUP BY cc.RPU, cr.anio'
        );
        $consultaTotales->execute(array_merge($rpus, $parametrosRango));
        $totales = [];
        foreach ($consultaTotales->fetchAll() as $fila) {
            $totales[(string) $fila['RPU']][(int) $fila['anio']] = (float) $fila['total'];
        }

        $filas = [];
        foreach ($rpus as $indice => $rpu) {
            $anuales = [];
            foreach ($anios as $anio) {
                $anuales[$anio] = (float) ($totales[$rpu][$anio] ?? 0);
            }
            $filas[] = [
                'no' => $indice + 1,
                'rpu' => $rpu,
                'nombre' => $servicios[$rpu]['nombre'] ?? 'No localizado en reportes CFE',
                'direccion' => $servicios[$rpu]['direccion'] ?? '',
                'poblacion' => $servicios[$rpu]['poblacion'] ?? '',
                'encontrado' => isset($servicios[$rpu]),
                'ultimo_reporte' => $servicios[$rpu]['ultimo_reporte'] ?? '',
                'anuales' => $anuales,
                'total' => array_sum($anuales)
            ];
        }
        return ['anios' => $anios, 'filas' => $filas, 'rango' => $rango];
    }

    private function excelExportacionAnual(array $datos): string
    {
        $anios = $datos['anios'];
        $encabezados = array_merge(['No.', 'RPU', 'Nombre del servicio CFE', 'Dirección CFE', 'Población CFE'], array_map(static fn (int $anio): string => (string) $anio, $anios), ['Total']);
        $columnas = '<Column ss:Width="42"/><Column ss:Width="115"/><Column ss:Width="250"/><Column ss:Width="250"/><Column ss:Width="160"/>' . str_repeat('<Column ss:Width="95"/>', count($anios) + 1);
        $totalAnual = array_fill_keys($anios, 0.0);
        $filas = '';
        foreach ($datos['filas'] as $fila) {
            $celdas = $this->celdaExcel($fila['no'], 'Number', 'Integer') . $this->celdaExcel($fila['rpu']) . $this->celdaExcel($fila['nombre']) . $this->celdaExcel($fila['direccion']) . $this->celdaExcel($fila['poblacion']);
            foreach ($anios as $anio) {
                $valor = (float) $fila['anuales'][$anio];
                $totalAnual[$anio] += $valor;
                $celdas .= $this->celdaExcel($valor, 'Number', 'Currency');
            }
            $filas .= '<Row>' . $celdas . $this->celdaExcel((float) $fila['total'], 'Number', 'Currency') . '</Row>';
        }
        $totales = $this->celdaExcel('') . $this->celdaExcel('') . '<Cell ss:MergeAcross="2" ss:StyleID="TotalLabel"><Data ss:Type="String">TOTAL GENERAL</Data></Cell>';
        foreach ($anios as $anio) {
            $totales .= $this->celdaExcel($totalAnual[$anio], 'Number', 'TotalCurrency');
        }
        $totales .= $this->celdaExcel(array_sum($totalAnual), 'Number', 'TotalCurrency');
        $titulo = 'CONCENTRADO DE RPUs CFE ' . sprintf('%02d/%d a %02d/%d', (int) $datos['rango']['inicio_mes'], (int) $datos['rango']['inicio_anio'], (int) $datos['rango']['fin_mes'], (int) $datos['rango']['fin_anio']);
        return '<?xml version="1.0" encoding="UTF-8"?>' .
            '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Styles>' .
            '<Style ss:ID="Title"><Font ss:Bold="1" ss:Color="#6A1B29" ss:Size="14"/></Style>' .
            '<Style ss:ID="Header"><Font ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#6A1B29" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/></Style>' .
            '<Style ss:ID="Currency"><NumberFormat ss:Format="\$#,##0.00"/></Style>' .
            '<Style ss:ID="Integer"><NumberFormat ss:Format="0"/></Style>' .
            '<Style ss:ID="TotalLabel"><Font ss:Bold="1"/><Interior ss:Color="#F5E9D8" ss:Pattern="Solid"/></Style>' .
            '<Style ss:ID="TotalCurrency"><Font ss:Bold="1"/><Interior ss:Color="#F5E9D8" ss:Pattern="Solid"/><NumberFormat ss:Format="\$#,##0.00"/></Style>' .
            '</Styles><Worksheet ss:Name="Concentrado anual"><Table>' . $columnas .
            '<Row><Cell ss:MergeAcross="' . (count($encabezados) - 1) . '" ss:StyleID="Title"><Data ss:Type="String">SECRETARÍA DE EDUCACIÓN GUERRERO</Data></Cell></Row>' .
            '<Row><Cell ss:MergeAcross="' . (count($encabezados) - 1) . '" ss:StyleID="Title"><Data ss:Type="String">' . $this->xml((string) $titulo) . '</Data></Cell></Row><Row/>' .
            '<Row>' . implode('', array_map(fn (string $encabezado): string => $this->celdaExcel($encabezado, 'String', 'Header'), $encabezados)) . '</Row>' .
            $filas . '<Row>' . $totales . '</Row></Table></Worksheet></Workbook>';
    }

    public function exportarResumenAnualCfe(): void
    {
        $this->validarToken();
        try {
            $anio = (int) ($_POST['anio'] ?? 0);
            if ($anio < 2021 || $anio > 2100) {
                throw new RuntimeException('Selecciona un año válido para exportar.');
            }
            $datos = $this->datosResumenAnualCfe(Conexion::conectar(), $anio);
            if (!$datos['filas']) {
                throw new RuntimeException('No hay consumos CFE cargados para el año seleccionado.');
            }
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="concentrado_cfe_' . $anio . '.xls"');
            header('Cache-Control: max-age=0');
            echo $this->excelResumenAnualCfe($datos);
            exit;
        } catch (Throwable $e) {
            $this->responder(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    private function datosResumenAnualCfe(PDO $conexion, int $anio): array
    {
        $consultaTotales = $conexion->prepare(
            'SELECT cc.RPU, COUNT(DISTINCT cc.reporte_id) reportes, SUM(cc.consumo) consumo, SUM(cc.energia) energia, SUM(cc.total) total
             FROM cfe_consumos cc
             INNER JOIN cfe_reportes cr ON cr.id = cc.reporte_id
             WHERE cr.anio = ?
             GROUP BY cc.RPU
             ORDER BY cc.RPU'
        );
        $consultaTotales->execute([$anio]);
        $acumulados = $consultaTotales->fetchAll();
        if (!$acumulados) {
            return ['anio' => $anio, 'filas' => []];
        }

        $consultaServicios = $conexion->prepare(
            'SELECT cc.RPU, cc.nombre_cfe, cc.poblacion_cfe, cc.direccion_cfe, cc.division_cfe, cc.tarifa_cfe, cr.mes, cc.id
             FROM cfe_consumos cc
             INNER JOIN cfe_reportes cr ON cr.id = cc.reporte_id
             WHERE cr.anio = ?
             ORDER BY cc.RPU, cr.mes DESC, cc.id DESC'
        );
        $consultaServicios->execute([$anio]);
        $servicios = [];
        foreach ($consultaServicios->fetchAll() as $servicio) {
            $rpu = (string) $servicio['RPU'];
            if (!isset($servicios[$rpu])) {
                $servicios[$rpu] = $servicio;
            }
        }

        $filas = [];
        foreach ($acumulados as $indice => $acumulado) {
            $rpu = (string) $acumulado['RPU'];
            $servicio = $servicios[$rpu] ?? [];
            $filas[] = [
                'no' => $indice + 1,
                'rpu' => $rpu,
                'nombre' => trim((string) ($servicio['nombre_cfe'] ?? '')),
                'poblacion' => trim((string) ($servicio['poblacion_cfe'] ?? '')),
                'direccion' => trim((string) ($servicio['direccion_cfe'] ?? '')),
                'division' => trim((string) ($servicio['division_cfe'] ?? '')),
                'tarifa' => trim((string) ($servicio['tarifa_cfe'] ?? '')),
                'reportes' => (int) $acumulado['reportes'],
                'consumo' => (float) $acumulado['consumo'],
                'energia' => (float) $acumulado['energia'],
                'total' => (float) $acumulado['total']
            ];
        }
        return ['anio' => $anio, 'filas' => $filas];
    }

    private function excelResumenAnualCfe(array $datos): string
    {
        $encabezados = ['No.', 'RPU', 'Nombre del servicio CFE', 'Población CFE', 'Domicilio CFE', 'División', 'Tarifa', 'Reportes', 'Consumo total kWh', 'Energía MXN', 'Costo total MXN'];
        $columnas = '<Column ss:Width="42"/><Column ss:Width="115"/><Column ss:Width="250"/><Column ss:Width="160"/><Column ss:Width="250"/><Column ss:Width="125"/><Column ss:Width="65"/><Column ss:Width="70"/><Column ss:Width="115"/><Column ss:Width="115"/><Column ss:Width="125"/>';
        $totales = ['reportes' => 0, 'consumo' => 0.0, 'energia' => 0.0, 'total' => 0.0];
        $filas = '';
        foreach ($datos['filas'] as $fila) {
            $totales['reportes'] += $fila['reportes'];
            $totales['consumo'] += $fila['consumo'];
            $totales['energia'] += $fila['energia'];
            $totales['total'] += $fila['total'];
            $filas .= '<Row>' .
                $this->celdaExcel($fila['no'], 'Number', 'Integer') .
                $this->celdaExcel($fila['rpu']) .
                $this->celdaExcel($fila['nombre']) .
                $this->celdaExcel($fila['poblacion']) .
                $this->celdaExcel($fila['direccion']) .
                $this->celdaExcel($fila['division']) .
                $this->celdaExcel($fila['tarifa']) .
                $this->celdaExcel($fila['reportes'], 'Number', 'Integer') .
                $this->celdaExcel($fila['consumo'], 'Number', 'Decimal') .
                $this->celdaExcel($fila['energia'], 'Number', 'Currency') .
                $this->celdaExcel($fila['total'], 'Number', 'Currency') .
                '</Row>';
        }
        $filaTotal = $this->celdaExcel('') . $this->celdaExcel('') . '<Cell ss:MergeAcross="4" ss:StyleID="TotalLabel"><Data ss:Type="String">TOTAL GENERAL</Data></Cell>' .
            $this->celdaExcel($totales['reportes'], 'Number', 'TotalInteger') .
            $this->celdaExcel($totales['consumo'], 'Number', 'TotalDecimal') .
            $this->celdaExcel($totales['energia'], 'Number', 'TotalCurrency') .
            $this->celdaExcel($totales['total'], 'Number', 'TotalCurrency');
        return '<?xml version="1.0" encoding="UTF-8"?>' .
            '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"><Styles>' .
            '<Style ss:ID="Title"><Font ss:Bold="1" ss:Color="#6A1B29" ss:Size="14"/></Style>' .
            '<Style ss:ID="Header"><Font ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#6A1B29" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/></Style>' .
            '<Style ss:ID="Currency"><NumberFormat ss:Format="\$#,##0.00"/></Style>' .
            '<Style ss:ID="Decimal"><NumberFormat ss:Format="#\,##0.00"/></Style>' .
            '<Style ss:ID="Integer"><NumberFormat ss:Format="0"/></Style>' .
            '<Style ss:ID="TotalLabel"><Font ss:Bold="1"/><Interior ss:Color="#F5E9D8" ss:Pattern="Solid"/></Style>' .
            '<Style ss:ID="TotalCurrency"><Font ss:Bold="1"/><Interior ss:Color="#F5E9D8" ss:Pattern="Solid"/><NumberFormat ss:Format="\$#,##0.00"/></Style>' .
            '<Style ss:ID="TotalDecimal"><Font ss:Bold="1"/><Interior ss:Color="#F5E9D8" ss:Pattern="Solid"/><NumberFormat ss:Format="#\,##0.00"/></Style>' .
            '<Style ss:ID="TotalInteger"><Font ss:Bold="1"/><Interior ss:Color="#F5E9D8" ss:Pattern="Solid"/><NumberFormat ss:Format="0"/></Style>' .
            '</Styles><Worksheet ss:Name="Resumen ' . $datos['anio'] . '"><Table>' . $columnas .
            '<Row><Cell ss:MergeAcross="10" ss:StyleID="Title"><Data ss:Type="String">SECRETARÍA DE EDUCACIÓN GUERRERO</Data></Cell></Row>' .
            '<Row><Cell ss:MergeAcross="10" ss:StyleID="Title"><Data ss:Type="String">CONCENTRADO ANUAL DE CONSUMOS CFE ' . $datos['anio'] . '</Data></Cell></Row><Row/>' .
            '<Row>' . implode('', array_map(fn (string $encabezado): string => $this->celdaExcel($encabezado, 'String', 'Header'), $encabezados)) . '</Row>' .
            $filas . '<Row>' . $filaTotal . '</Row></Table></Worksheet></Workbook>';
    }

    private function celdaExcel(mixed $valor, string $tipo = 'String', string $estilo = ''): string
    {
        $atributo = $estilo !== '' ? ' ss:StyleID="' . $estilo . '"' : '';
        $dato = $tipo === 'String' ? $this->xml((string) $valor) : (string) $valor;
        return '<Cell' . $atributo . '><Data ss:Type="' . $tipo . '">' . $dato . '</Data></Cell>';
    }

    private function xml(string $texto): string
    {
        return htmlspecialchars($texto, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function historial(PDO $conexion, string $rpu): array
    {
        $consulta = $conexion->prepare(
            'SELECT cc.id, cc.RPU, cc.division_cfe, cc.nombre_cfe, cc.direccion_cfe, cc.poblacion_cfe, cc.tarifa_cfe,
                    cc.tipo_periodo, cc.tipo_movimiento, cc.enriquecido_plano, cc.desde, cc.hasta, cc.dias, cc.consumo, cc.total,
                    COALESCE(cc.medidor, JSON_UNQUOTE(JSON_EXTRACT(cp.datos_json, \'$.numero\'))) AS medidor,
                    COALESCE(cc.lectura_anterior, CAST(JSON_UNQUOTE(JSON_EXTRACT(cp.datos_json, \'$.lecturaanterior\')) AS DECIMAL(18,4))) AS lectura_anterior,
                    COALESCE(cc.lectura_actual, CAST(JSON_UNQUOTE(JSON_EXTRACT(cp.datos_json, \'$.lecturaactual\')) AS DECIMAL(18,4))) AS lectura_actual,
                    COALESCE(cc.multiplicador, CAST(JSON_UNQUOTE(JSON_EXTRACT(cp.datos_json, \'$.multiplicador\')) AS DECIMAL(18,4))) AS multiplicador,
                    cc.severidad, cc.alertas, cr.anio, cr.mes
             FROM cfe_consumos cc FORCE INDEX (idx_cfe_consumos_rpu_id)
             INNER JOIN cfe_reportes cr ON cr.id = cc.reporte_id
             LEFT JOIN cfe_plano_conciliaciones cp ON cp.consumo_id = cc.id AND cp.estado = \'CONCILIADO\'
             WHERE cc.RPU = ?
             ORDER BY cr.anio DESC, cr.mes DESC, cc.hasta DESC, cc.id DESC'
        );
        $consulta->execute([$rpu]);
        $historial = $consulta->fetchAll();
        if (!$historial) {
            return [];
        }
        $lecturas = $conexion->prepare(
            'SELECT consumo_id, numero_medidor, tipo_medidor, posicion, ley_medidor, lectura_anterior, lectura_actual, diferencia_lectura, multiplicador
             FROM cfe_lecturas_medidores
             WHERE RPU = ? AND consumo_id IS NOT NULL
             ORDER BY consumo_id, tipo_medidor, posicion'
        );
        $lecturas->execute([$rpu]);
        $porConsumo = [];
        foreach ($lecturas->fetchAll() as $lectura) {
            $porConsumo[(int) $lectura['consumo_id']][] = $lectura;
        }
        foreach ($historial as &$fila) {
            $fila['medidores'] = $porConsumo[(int) $fila['id']] ?? [];
            if (!$fila['medidores'] && !empty($fila['medidor'])) {
                $fila['medidores'][] = [
                    'numero_medidor' => $fila['medidor'],
                    'tipo_medidor' => 'INSTALADO',
                    'posicion' => 1,
                    'lectura_anterior' => $fila['lectura_anterior'],
                    'lectura_actual' => $fila['lectura_actual'],
                    'diferencia_lectura' => null,
                    'multiplicador' => $fila['multiplicador']
                ];
            }
        }
        unset($fila);
        return $historial;
    }

    private function vinculos(PDO $conexion, string $rpu): array
    {
        $consulta = $conexion->prepare(
            'SELECT er.RPU, er.CCT, er.nombre_recibo_cfe, er.poblacion_cfe, er.tarifa_cfe, e.NOMBRECT, e.DOMICILIO, e.NOMBREMUN, e.NOMBRELOC, e.STATUS, e.SUBNIVEL, e.NIVEL, e.HOMO, e.TURNO, e.ZONA, e.SECTOR, e.ORIGEN, e.LATITUD, e.LONGITUD
             FROM escuelas_rpu er
             LEFT JOIN escuelas e ON e.CCT = er.CCT
             WHERE er.RPU = ?
             ORDER BY er.id'
        );
        $consulta->execute([$rpu]);
        return array_map(fn (array $fila): array => $this->escuelaDesdeFila($fila, 100, 'Vinculo confirmado'), $consulta->fetchAll());
    }

    private function ubicacionPlano(PDO $conexion, string $rpu): array
    {
        $consulta = $conexion->prepare(
            'SELECT pd.direccion_plano, pd.poblacion_plano, pd.municipio_plano, pd.estado_plano, pd.colonia_plano, pd.calle_1, pd.calle_2
             FROM cfe_plano_detalles pd
             INNER JOIN cfe_consumos cc ON cc.id = pd.consumo_id
             INNER JOIN cfe_reportes cr ON cr.id = cc.reporte_id
             WHERE pd.RPU = ?
               AND (pd.direccion_plano IS NOT NULL OR pd.poblacion_plano IS NOT NULL OR pd.municipio_plano IS NOT NULL OR pd.colonia_plano IS NOT NULL)
             ORDER BY cr.anio DESC, cr.mes DESC, pd.id DESC
             LIMIT 1'
        );
        $consulta->execute([$rpu]);
        $fila = $consulta->fetch();
        if (!$fila) {
            return [];
        }
        return [
            'direccion' => trim((string) ($fila['direccion_plano'] ?? '')),
            'poblacion' => trim((string) ($fila['poblacion_plano'] ?? '')),
            'municipio' => trim((string) ($fila['municipio_plano'] ?? '')),
            'estado' => trim((string) ($fila['estado_plano'] ?? '')),
            'colonia' => trim((string) ($fila['colonia_plano'] ?? '')),
            'calle_1' => trim((string) ($fila['calle_1'] ?? '')),
            'calle_2' => trim((string) ($fila['calle_2'] ?? ''))
        ];
    }

    private function sugerencias(PDO $conexion, string $rpu, ?array $ultimo, bool $busquedaAmplia = true): array
    {
        if (!$ultimo) {
            return [];
        }
        $poblacion = trim((string) ($ultimo['poblacion_cfe'] ?? ''));
        $nombre = trim((string) ($ultimo['nombre_cfe'] ?? ''));
        $direccion = trim((string) ($ultimo['direccion_cfe'] ?? ''));
        $plano = is_array($ultimo['plano'] ?? null) ? $ultimo['plano'] : [
            'direccion' => (string) ($ultimo['direccion_plano'] ?? ''),
            'poblacion' => (string) ($ultimo['poblacion_plano'] ?? ''),
            'municipio' => (string) ($ultimo['municipio_plano'] ?? ''),
            'estado' => (string) ($ultimo['estado_plano'] ?? ''),
            'colonia' => (string) ($ultimo['colonia_plano'] ?? ''),
            'calle_1' => (string) ($ultimo['calle_1'] ?? ''),
            'calle_2' => (string) ($ultimo['calle_2'] ?? '')
        ];
        $referenciaCfe = $this->referenciaGeograficaCfe($poblacion);
        $referenciaPlano = $this->referenciaGeograficaCfe((string) ($plano['poblacion'] ?? ''));
        $municipioPlano = trim((string) ($plano['municipio'] ?? ''));
        $referencia = [
            'localidad' => $referenciaPlano['localidad'] !== '' ? $referenciaPlano['localidad'] : $referenciaCfe['localidad'],
            'municipio' => $municipioPlano !== '' ? $municipioPlano : ($referenciaPlano['municipio'] !== '' ? $referenciaPlano['municipio'] : $referenciaCfe['municipio']),
            'texto' => implode(' ', array_filter([$referenciaCfe['texto'], $referenciaPlano['texto'], $plano['direccion'] ?? '', $plano['colonia'] ?? '', $plano['calle_1'] ?? '', $plano['calle_2'] ?? ''])),
            'localidad_cfe' => $referenciaCfe['localidad'],
            'municipio_cfe' => $referenciaCfe['municipio'],
            'localidad_plano' => $referenciaPlano['localidad'],
            'municipio_plano' => $municipioPlano !== '' ? $municipioPlano : $referenciaPlano['municipio'],
            'direccion_plano' => trim(implode(' ', array_filter([$plano['direccion'] ?? '', $plano['colonia'] ?? '', $plano['calle_1'] ?? '', $plano['calle_2'] ?? ''])))
        ];
        $direccionBusqueda = trim(implode(' ', array_filter([$direccion, $referencia['direccion_plano']])));
        if ($referencia['localidad'] === '' && $referencia['municipio'] === '') {
            return [];
        }
        $filtrosGeograficos = [];
        if ($referencia['localidad'] !== '' && $referencia['municipio'] !== '') {
            $filtrosGeograficos[] = [
                'NOMBRELOC LIKE :localidad AND NOMBREMUN LIKE :municipio',
                ['localidad' => $referencia['localidad'] . '%', 'municipio' => $referencia['municipio'] . '%']
            ];
            $filtrosGeograficos[] = [
                'NOMBRELOC LIKE :localidad AND NOMBREMUN LIKE :municipio',
                ['localidad' => '%' . $referencia['localidad'] . '%', 'municipio' => '%' . $referencia['municipio'] . '%']
            ];
        } elseif ($referencia['localidad'] !== '') {
            $filtrosGeograficos[] = ['NOMBRELOC LIKE :localidad', ['localidad' => $referencia['localidad'] . '%']];
            $filtrosGeograficos[] = ['NOMBRELOC LIKE :localidad', ['localidad' => '%' . $referencia['localidad'] . '%']];
        } elseif ($referencia['municipio'] !== '') {
            $filtrosGeograficos[] = ['NOMBREMUN LIKE :municipio', ['municipio' => $referencia['municipio'] . '%']];
            $filtrosGeograficos[] = ['NOMBREMUN LIKE :municipio', ['municipio' => '%' . $referencia['municipio'] . '%']];
        }
        $filas = [];
        foreach ($filtrosGeograficos as [$filtro, $parametros]) {
            $consulta = $conexion->prepare(
                'SELECT id, CCT, NOMBRECT, DOMICILIO, NOMBREMUN, NOMBRELOC, STATUS, SUBNIVEL, NIVEL, HOMO, TURNO, ZONA, SECTOR, ORIGEN, CLASIFICACION, TIPOCT
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
            if (!$busquedaAmplia) {
                break;
            }
        }
        if ($busquedaAmplia && !$filas) {
            $indice = $this->indiceEscuelasPorUbicacion($conexion);
            $candidatosPrecisos = $this->candidatosRapidosPorUbicacion($indice, $referencia, $nombre, $direccionBusqueda);
            if ($candidatosPrecisos) {
                $filas = $candidatosPrecisos;
            }
        }
        return $this->evaluarSugerencias($filas, $nombre, $referencia, $direccion);
    }

    private function indiceEscuelasPorUbicacion(PDO $conexion): array
    {
        $filas = $conexion->query(
            'SELECT id, CCT, NOMBRECT, DOMICILIO, NOMBREMUN, NOMBRELOC, STATUS, SUBNIVEL, NIVEL, HOMO, TURNO, ZONA, SECTOR, ORIGEN, CLASIFICACION, TIPOCT
             FROM escuelas
             WHERE NOMBRELOC IS NOT NULL AND NOMBRELOC <> ""'
        )->fetchAll();
        $porLocalidad = [];
        $porMunicipio = [];
        $porUbicacion = [];
        $porTexto = [];
        foreach ($filas as $fila) {
            $localidad = $this->normalizar((string) ($fila['NOMBRELOC'] ?? ''));
            $municipio = $this->normalizar((string) ($fila['NOMBREMUN'] ?? ''));
            if ($localidad !== '') {
                $porLocalidad[$localidad][] = $fila;
            }
            if ($municipio !== '') {
                $porMunicipio[$municipio][] = $fila;
            }
            if ($localidad !== '' && $municipio !== '') {
                $porUbicacion[$localidad . '|' . $municipio][] = $fila;
            }
            $texto = $this->normalizar(implode(' ', [
                (string) ($fila['NOMBRECT'] ?? ''),
                (string) ($fila['DOMICILIO'] ?? ''),
                (string) ($fila['NOMBRELOC'] ?? ''),
                (string) ($fila['NIVEL'] ?? ''),
                (string) ($fila['SUBNIVEL'] ?? ''),
                (string) ($fila['HOMO'] ?? ''),
                (string) ($fila['TIPOCT'] ?? '')
            ]));
            foreach (array_unique(explode(' ', $texto)) as $palabra) {
                if (strlen($palabra) >= 4) {
                    $porTexto[$palabra][] = $fila;
                }
            }
        }
        return ['localidad' => $porLocalidad, 'municipio' => $porMunicipio, 'ubicacion' => $porUbicacion, 'texto' => $porTexto];
    }

    private function candidatosRapidosPorUbicacion(array $indice, array $referencia, string $nombre = '', string $direccion = ''): array
    {
        $localidad = $this->normalizar((string) ($referencia['localidad'] ?? ''));
        $municipio = $this->normalizar((string) ($referencia['municipio'] ?? ''));
        if ($localidad !== '' && $municipio !== '') {
            $exactos = $indice['ubicacion'][$localidad . '|' . $municipio] ?? [];
            if ($exactos) {
                return $exactos;
            }
        }
        if ($localidad !== '') {
            $exactos = $indice['localidad'][$localidad] ?? [];
            if ($exactos) {
                return $exactos;
            }
        }
        if ($municipio !== '' && !empty($indice['municipio'][$municipio])) {
            return $indice['municipio'][$municipio];
        }
        $contextoTerritorial = implode(' ', [(string) ($referencia['texto'] ?? ''), $direccion]);
        $candidatosTerritoriales = [];
        foreach (($indice['municipio'] ?? []) as $municipioCatalogo => $filas) {
            if (!$this->coincideTerritorioEnTexto($municipioCatalogo, $contextoTerritorial)) {
                continue;
            }
            foreach ($filas as $fila) {
                $candidatosTerritoriales[(string) ($fila['id'] ?? $fila['CCT'])] = $fila;
            }
        }
        if ($candidatosTerritoriales) {
            return array_values($candidatosTerritoriales);
        }

        $candidatos = [];
        foreach (['localidad' => $localidad, 'municipio' => $municipio] as $tipo => $referenciaTexto) {
            if ($referenciaTexto === '') {
                continue;
            }
            foreach (($indice[$tipo] ?? []) as $ubicacion => $filas) {
                similar_text($referenciaTexto, $ubicacion, $similitudUbicacion);
                if (str_contains($ubicacion, $referenciaTexto) || str_contains($referenciaTexto, $ubicacion) || (strlen($referenciaTexto) >= 5 && $similitudUbicacion >= 72)) {
                    foreach ($filas as $fila) {
                        $candidatos[(string) ($fila['id'] ?? $fila['CCT'])] = $fila;
                    }
                }
            }
        }
        if ($candidatos) {
            return array_values($candidatos);
        }

        $busqueda = $this->terminosBusquedaCfe($localidad . ' ' . $direccion . ' ' . $nombre);
        $palabras = array_merge($busqueda['especificas'], $busqueda['apoyos']);
        $coincidencias = [];
        $coincidenciasEspecificas = [];
        foreach ($palabras as $palabra) {
            foreach (($indice['texto'][$palabra] ?? []) as $fila) {
                $clave = (string) ($fila['id'] ?? $fila['CCT']);
                $candidatos[$clave] = $fila;
                $coincidencias[$clave] = ($coincidencias[$clave] ?? 0) + 1;
                if (in_array($palabra, $busqueda['especificas'], true)) {
                    $coincidenciasEspecificas[$clave] = ($coincidenciasEspecificas[$clave] ?? 0) + 1;
                }
            }
        }
        return array_values(array_filter($candidatos, static function (array $fila) use ($coincidencias, $coincidenciasEspecificas): bool {
            $clave = (string) ($fila['id'] ?? $fila['CCT']);
            $total = $coincidencias[$clave] ?? 0;
            $especificas = $coincidenciasEspecificas[$clave] ?? 0;
            return $especificas >= 2 || ($especificas >= 1 && $total >= 2);
        }));
    }

    private function evaluarSugerencias(array $filas, string $nombre, array $referencia, string $direccion): array
    {
        $perfilServicio = $this->perfilServicioCfe($nombre);
        $nivelCfe = $this->identificarNivelCfe($nombre);
        $requiereIndigena = $this->requiereSubnivelIndigena($nombre);
        $sugerencias = [];
        foreach ($filas as $fila) {
            $evaluacion = $this->puntaje($nombre, $referencia, $direccion, $nivelCfe, $perfilServicio, $fila);
            if ($evaluacion['score'] >= 35) {
                $sugerencias[] = $this->escuelaDesdeFila($fila, $evaluacion['score'], 'Sugerencia por padrón maestro', $evaluacion);
            }
        }
        $fisicas = array_values(array_filter(
            $sugerencias,
            static fn (array $escuela): bool => $perfilServicio === 'ADMINISTRATIVO'
                ? (bool) ($escuela['administrativa'] ?? false)
                : !(bool) ($escuela['administrativa'] ?? false)
        ));
        if ($requiereIndigena) {
            $fisicas = array_values(array_filter($fisicas, fn (array $escuela): bool => str_contains($this->normalizar((string) ($escuela['subnivel'] ?? '')), 'INDIGENA')));
        }
        if (!$fisicas) {
            return [];
        }
        $porNivel = array_values(array_filter($fisicas, fn (array $escuela): bool => $escuela['nivel_coincide'] ?? false));
        if ($perfilServicio !== 'ADMINISTRATIVO' && $nivelCfe !== null) {
            $sugerencias = $porNivel;
        } else {
            $sugerencias = $fisicas;
        }
        usort($sugerencias, fn (array $a, array $b): int => [$b['score'], $b['confianza'], $b['activa']] <=> [$a['score'], $a['confianza'], $a['activa']]);
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
            'escuela_id' => (int) ($fila['id'] ?? 0),
            'cct' => (string) ($fila['CCT'] ?? ''),
            'nombre' => (string) ($fila['NOMBRECT'] ?? ''),
            'domicilio' => (string) ($fila['DOMICILIO'] ?? ''),
            'municipio' => (string) ($fila['NOMBREMUN'] ?? ''),
            'localidad' => (string) ($fila['NOMBRELOC'] ?? ''),
            'latitud' => (string) ($fila['LATITUD'] ?? ''),
            'longitud' => (string) ($fila['LONGITUD'] ?? ''),
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
            'confianza' => (int) ($evaluacion['confianza'] ?? $score),
            'nivel_coincide' => (bool) ($evaluacion['nivel_coincide'] ?? false),
            'evidencia_tipo' => (string) ($evaluacion['evidencia_tipo'] ?? ''),
            'evidencias' => $evaluacion['evidencias'] ?? [],
            'comparacion' => $evaluacion['comparacion'] ?? [],
            'ubicacion' => (string) ($evaluacion['ubicacion'] ?? ''),
            'ubicacion_confirmada' => (bool) ($evaluacion['ubicacion_confirmada'] ?? false),
            'administrativa' => (bool) ($evaluacion['administrativa'] ?? false),
            'grupo' => (string) ($evaluacion['grupo'] ?? ($this->esAdministrativa($fila) ? 'INMUEBLE SEG' : 'ESCUELA')),
            'activa' => (bool) ($evaluacion['activa'] ?? false),
            'origen' => $origen
        ];
    }

    private function puntaje(string $nombreCfe, array $referencia, string $direccionCfe, ?string $nivelCfe, string $perfilServicio, array $escuela): array
    {
        $nombreBase = $this->nombreComparable($nombreCfe);
        $nombreEscuela = $this->nombreComparable((string) ($escuela['NOMBRECT'] ?? ''));
        similar_text($nombreBase, $nombreEscuela, $similitud);
        $localidad = $this->normalizar((string) ($escuela['NOMBRELOC'] ?? ''));
        $municipio = $this->normalizar((string) ($escuela['NOMBREMUN'] ?? ''));
        $referenciaLocalidad = $this->normalizar((string) ($referencia['localidad'] ?? ''));
        $referenciaMunicipio = $this->normalizar((string) ($referencia['municipio'] ?? ''));
        $direccionPlano = trim((string) ($referencia['direccion_plano'] ?? ''));
        $contextoCfe = implode(' ', [(string) ($referencia['texto'] ?? ''), $direccionCfe, $direccionPlano, $nombreCfe]);
        $coincideLocalidad = $localidad !== '' && ($this->coincideTerritorioEnTexto($localidad, $referenciaLocalidad) || $this->coincideTerritorioEnTexto($localidad, $contextoCfe));
        $coincideMunicipio = $municipio !== '' && ($this->coincideTerritorioEnTexto($municipio, $referenciaMunicipio) || $this->coincideTerritorioEnTexto($municipio, $contextoCfe));
        $ubicacion = $coincideLocalidad ? 'Misma localidad/población' : ($coincideMunicipio ? 'Municipio coincidente' : 'Nombre o domicilio cercano');
        $nivelCoincide = $this->coincideNivelCfe($nivelCfe, $escuela);
        $etiquetasCoincidentes = array_values(array_intersect(
            $this->etiquetasServicioCfe($nombreCfe),
            $this->etiquetasEscuela($escuela)
        ));
        $activa = $this->estaActiva((string) ($escuela['STATUS'] ?? ''));
        $administrativa = $this->esAdministrativa($escuela);
        $direccion = $this->normalizar(trim($direccionCfe . ' ' . $direccionPlano));
        $domicilio = $this->normalizar((string) ($escuela['DOMICILIO'] ?? ''));
        $coincidenciasDireccion = 0;
        if ($direccion !== '' && $domicilio !== '') {
            $palabrasCfe = array_unique(array_filter(explode(' ', $direccion), fn (string $palabra): bool => strlen($palabra) >= 4));
            $palabrasEscuela = array_unique(array_filter(explode(' ', $domicilio), fn (string $palabra): bool => strlen($palabra) >= 4));
            $coincidenciasDireccion = count(array_intersect($palabrasCfe, $palabrasEscuela));
        }
        $ubicacionConfirmada = ($coincideLocalidad && $coincideMunicipio) || ($coincideMunicipio && $coincidenciasDireccion >= 1);
        $ubicacion = $coincideLocalidad ? 'Localidad confirmada' : ($ubicacionConfirmada ? 'Municipio y dirección confirmados' : ($coincideMunicipio ? 'Municipio coincidente' : ($coincidenciasDireccion >= 2 ? 'Dirección cercana' : 'Ubicación por revisar')));
        $confianza = ($similitud * 0.45)
            + ($coincideLocalidad ? 28 : 0)
            + ($coincideMunicipio ? 25 : 0)
            + min(12, $coincidenciasDireccion * 4)
            + ($nivelCoincide ? 8 : 0)
            + ($activa ? 4 : 0);
        if (!$ubicacionConfirmada) {
            $confianza = min($confianza, 49);
        }
        $tipoCompatible = $perfilServicio === 'ADMINISTRATIVO' ? $administrativa : !$administrativa;
        if (!$tipoCompatible) {
            $confianza = 0;
        }
        $evidencias = [];
        if ($coincideLocalidad) {
            $evidencias[] = 'Localidad coincide';
        }
        if ($coincideMunicipio) {
            $evidencias[] = 'Municipio coincide';
        }
        if ($coincidenciasDireccion > 0) {
            $evidencias[] = 'Domicilio con ' . $coincidenciasDireccion . ' coincidencia' . ($coincidenciasDireccion === 1 ? '' : 's');
        }
        if ($nivelCoincide) {
            $evidencias[] = 'Nivel coincide';
        }
        if ($similitud >= 45) {
            $evidencias[] = 'Nombre ' . (int) round($similitud) . '%';
        }
        $comparacion = [
            [
                'campo' => 'Población / localidad',
                'cfe' => (string) ($referencia['localidad_cfe'] ?? $referencia['localidad'] ?? ''),
                'plano' => (string) ($referencia['localidad_plano'] ?? ''),
                'catalogo' => (string) ($escuela['NOMBRELOC'] ?? ''),
                'coincide' => $coincideLocalidad
            ],
            [
                'campo' => 'Municipio',
                'cfe' => (string) ($referencia['municipio_cfe'] ?? $referencia['municipio'] ?? ''),
                'plano' => (string) ($referencia['municipio_plano'] ?? ''),
                'catalogo' => (string) ($escuela['NOMBREMUN'] ?? ''),
                'coincide' => $coincideMunicipio
            ],
            [
                'campo' => 'Domicilio',
                'cfe' => $direccionCfe,
                'plano' => $direccionPlano,
                'catalogo' => (string) ($escuela['DOMICILIO'] ?? ''),
                'coincide' => $coincidenciasDireccion > 0
            ],
            [
                'campo' => 'Nivel',
                'cfe' => $nivelCfe ?? '',
                'plano' => '',
                'catalogo' => (string) ($escuela['NIVEL'] ?? '') !== '' ? (string) $escuela['NIVEL'] : (string) ($escuela['SUBNIVEL'] ?? ''),
                'coincide' => $nivelCoincide
            ]
        ];
        return [
            'score' => max(0, min(100, (int) round($confianza))),
            'similitud' => round($similitud, 2),
            'confianza' => max(0, min(100, (int) round($confianza))),
            'nivel_coincide' => $nivelCoincide,
            'evidencia_tipo' => implode(' / ', $etiquetasCoincidentes),
            'evidencias' => $evidencias,
            'comparacion' => $comparacion,
            'ubicacion' => $ubicacion,
            'ubicacion_confirmada' => $ubicacionConfirmada,
            'administrativa' => $administrativa,
            'grupo' => $administrativa ? 'INMUEBLE SEG' : 'ESCUELA',
            'activa' => $activa
        ];
    }

    private function palabrasClave(string $texto): array
    {
        $ignorar = ['ESCUELA', 'JARDIN', 'NINOS', 'PRIMARIA', 'SECUNDARIA', 'PREESCOLAR', 'TELESECUNDARIA', 'GENERAL', 'SERVICIO', 'CALLE', 'CARRETERA', 'DOMICILIO', 'CENTRO', 'FEDERAL'];
        return array_values(array_unique(array_filter(
            explode(' ', $this->normalizar($texto)),
            static fn (string $palabra): bool => strlen($palabra) >= 4 && !in_array($palabra, $ignorar, true)
        )));
    }

    private function terminosBusquedaCfe(string $texto): array
    {
        $normalizado = $this->normalizar($texto);
        $apoyos = [];
        if (preg_match('/(^| )ESC( |$)/', $normalizado) || str_contains($normalizado, 'ESCUELA')) {
            $apoyos[] = 'ESCUELA';
        }
        if (str_contains($normalizado, 'PRIM')) {
            $apoyos[] = 'PRIMARIA';
        }
        if (str_contains($normalizado, 'SEC')) {
            $apoyos[] = 'SECUNDARIA';
        }
        if (str_contains($normalizado, 'JARDIN') || str_contains($normalizado, 'KINDER') || preg_match('/(^| )JN( |$)/', $normalizado)) {
            $apoyos[] = 'PREESCOLAR';
        }
        if (str_contains($normalizado, 'TELE')) {
            $apoyos[] = 'TELESECUNDARIA';
        }
        if (str_contains($normalizado, 'INDIGENA')) {
            $apoyos[] = 'INDIGENA';
        }
        if (str_contains($normalizado, 'FEDERAL')) {
            $apoyos[] = 'FEDERAL';
        }
        return [
            'especificas' => $this->palabrasClave($texto),
            'apoyos' => array_values(array_unique($apoyos))
        ];
    }

    private function etiquetasServicioCfe(string $nombre): array
    {
        $texto = $this->normalizar($nombre);
        $etiquetas = [];
        if (preg_match('/(^| )ESC( |$)/', $texto) || str_contains($texto, 'ESCUELA')) {
            $etiquetas[] = 'ESCUELA';
        }
        if (str_contains($texto, 'FEDERAL')) {
            $etiquetas[] = 'FEDERAL';
        }
        if (str_contains($texto, 'INDIGENA')) {
            $etiquetas[] = 'INDIGENA';
        }
        $nivel = $this->identificarNivelCfe($nombre);
        if ($nivel !== null) {
            $etiquetas[] = $nivel;
        }
        return array_values(array_unique($etiquetas));
    }

    private function etiquetasEscuela(array $escuela): array
    {
        $texto = $this->normalizar(implode(' ', [
            (string) ($escuela['NOMBRECT'] ?? ''),
            (string) ($escuela['NIVEL'] ?? ''),
            (string) ($escuela['SUBNIVEL'] ?? ''),
            (string) ($escuela['HOMO'] ?? ''),
            (string) ($escuela['TIPOCT'] ?? '')
        ]));
        $etiquetas = [];
        if (!$this->esAdministrativa($escuela)) {
            $etiquetas[] = 'ESCUELA';
        }
        if (str_contains($texto, 'FEDERAL') || str_starts_with($this->normalizar((string) ($escuela['HOMO'] ?? '')), 'D')) {
            $etiquetas[] = 'FEDERAL';
        }
        if (str_contains($texto, 'INDIGENA')) {
            $etiquetas[] = 'INDIGENA';
        }
        foreach (['PREESCOLAR', 'PRIMARIA', 'SECUNDARIA', 'TELESECUNDARIA'] as $nivel) {
            if (str_contains($texto, $nivel)) {
                $etiquetas[] = $nivel;
            }
        }
        return array_values(array_unique($etiquetas));
    }

    private function nombreComparable(string $texto): string
    {
        return implode(' ', $this->palabrasClave($texto));
    }

    private function referenciaGeograficaCfe(string $poblacion): array
    {
        $texto = trim(preg_replace('/\s+/', ' ', $poblacion) ?? '');
        $texto = trim((string) preg_replace('/,?\s*(GRO\.?|GUERRERO)(?:\s+[A-Z])?\.?$/iu', '', $texto));
        $localidad = $texto;
        $municipio = '';
        if (preg_match('/^(.*?)\s*\(([^)]+)\)\s*$/u', $texto, $coincidencia)) {
            $localidad = trim($coincidencia[1]);
            $municipio = trim($coincidencia[2]);
        }
        return [
            'localidad' => $localidad,
            'municipio' => $municipio,
            'texto' => $texto
        ];
    }

    private function coincideTerritorioEnTexto(string $territorio, string $texto): bool
    {
        $territorio = $this->normalizar($territorio);
        $texto = $this->normalizar($texto);
        if ($territorio === '' || $texto === '') {
            return false;
        }
        if (str_contains($texto, $territorio) || str_contains($territorio, $texto)) {
            return true;
        }
        if (strlen($territorio) < 7) {
            return false;
        }
        foreach (array_unique(explode(' ', $texto)) as $palabra) {
            if (strlen($palabra) < 7) {
                continue;
            }
            $longitud = min(strlen($territorio), strlen($palabra));
            if ($longitud >= 7 && levenshtein($territorio, $palabra) <= max(1, (int) floor($longitud * 0.12))) {
                return true;
            }
        }
        return false;
    }

    private function perfilServicioCfe(string $nombre): string
    {
        $texto = $this->normalizar($nombre);
        foreach (['SUPERVISION', 'SUPERV', 'JEFATURA', 'JEFAT', 'SECTOR', 'ZONA ESCOLAR', 'COORDINACION', 'DIRECCION', 'ADMINISTRAT', 'ALMACEN', 'CENTRO DE MAESTROS', 'SERVICIOS EDUCATIVOS'] as $termino) {
            if (str_contains($texto, $termino)) {
                return 'ADMINISTRATIVO';
            }
        }
        return 'ESCUELA';
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
            return ['registros' => 0, 'total_actual' => 0, 'consumo_actual' => 0, 'total_acumulado' => 0, 'consumo_acumulado' => 0, 'diferencia_total' => null, 'estado' => 'Sin historial'];
        }
        $actual = $historial[0];
        $anterior = $historial[1] ?? null;
        $diferencia = $anterior ? (float) $actual['total'] - (float) $anterior['total'] : null;
        return [
            'registros' => count($historial),
            'total_actual' => (float) $actual['total'],
            'consumo_actual' => (float) $actual['consumo'],
            'total_acumulado' => array_sum(array_map(static fn (array $fila): float => (float) ($fila['total'] ?? 0), $historial)),
            'consumo_acumulado' => array_sum(array_map(static fn (array $fila): float => (float) ($fila['consumo'] ?? 0), $historial)),
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

if (in_array($accion, ['sugerir_vinculos_paginados', 'buscar_rpu_por_nombre', 'vincular_rpu', 'vincular_rpus_masivo', 'auto_vincular_sugerencias', 'previsualizar_auto_vinculos', 'desvincular_rpu', 'previsualizar_exportacion_anual', 'exportar_rpus_anual'], true)) {
    segRequireAdmin();
}

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

if ($accion === 'buscar_rpu_por_nombre') {
    $controlador->buscarRpuPorNombre();
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

if ($accion === 'auto_vincular_sugerencias') {
    $controlador->autoVincularSugerencias();
}

if ($accion === 'previsualizar_auto_vinculos') {
    $controlador->previsualizarAutoVinculos();
}

if ($accion === 'desvincular_rpu') {
    $controlador->desvincular();
}

if ($accion === 'previsualizar_exportacion_anual') {
    $controlador->previsualizarExportacionAnual();
}

if ($accion === 'exportar_rpus_anual') {
    $controlador->exportarRpusAnual();
}

if ($accion === 'exportar_resumen_anual_cfe') {
    $controlador->exportarResumenAnualCfe();
}

http_response_code(400);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => false, 'error' => 'Accion no reconocida.'], JSON_UNESCAPED_UNICODE);
