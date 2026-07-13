<?php

declare(strict_types=1);

session_start();

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
