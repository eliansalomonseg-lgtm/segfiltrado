<?php

declare(strict_types=1);

session_start();

require_once dirname(__DIR__) . '/services/conexion.php';

class RpuController
{
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
            'SELECT er.RPU, er.CCT, er.nombre_recibo_cfe, er.poblacion_cfe, er.tarifa_cfe, e.NOMBRECT, e.DOMICILIO, e.NOMBREMUN, e.NOMBRELOC, e.STATUS, e.SUBNIVEL
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
            $parametros['poblacion'] = '%' . $poblacion . '%';
            $condiciones[] = '(NOMBRELOC LIKE :poblacion OR NOMBREMUN LIKE :poblacion)';
        }
        foreach (array_slice(array_filter(preg_split('/\s+/', $nombre) ?: [], fn ($p): bool => strlen($p) >= 4), 0, 3) as $i => $palabra) {
            $parametros['p' . $i] = '%' . $palabra . '%';
            $condiciones[] = 'NOMBRECT LIKE :p' . $i;
        }
        if (!$condiciones) {
            return [];
        }
        $consulta = $conexion->prepare(
            'SELECT CCT, NOMBRECT, DOMICILIO, NOMBREMUN, NOMBRELOC, STATUS, SUBNIVEL
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
            'SELECT cc.CCT, e.NOMBRECT, e.DOMICILIO, e.NOMBREMUN, e.NOMBRELOC, e.STATUS, e.SUBNIVEL, COUNT(*) apariciones
             FROM cfe_consumos cc
             INNER JOIN escuelas e ON e.CCT = cc.CCT
             WHERE cc.RPU = ? AND cc.CCT IS NOT NULL
             GROUP BY cc.CCT, e.NOMBRECT, e.DOMICILIO, e.NOMBREMUN, e.NOMBRELOC, e.STATUS, e.SUBNIVEL
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
            'subnivel' => (string) ($fila['SUBNIVEL'] ?? ''),
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

if ($accion === 'vincular_rpu') {
    $controlador->vincular();
}

http_response_code(400);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => false, 'error' => 'Accion no reconocida.'], JSON_UNESCAPED_UNICODE);
