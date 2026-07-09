<?php

declare(strict_types=1);

session_start();

require_once dirname(__DIR__) . '/models/escuelaModel.php';

class EscuelaController
{
    public function procesarArchivos(): void
    {
        $this->validarToken();
        foreach (['archivo_seg', 'archivo_cfe'] as $campo) {
            if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] !== UPLOAD_ERR_OK) {
                $this->responder(['ok' => false, 'error' => 'Carga el catálogo SEG y el reporte CFE.'], 422);
            }
            $extension = strtolower(pathinfo($_FILES[$campo]['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, ['xlsx', 'xls'], true)) {
                $this->responder(['ok' => false, 'error' => 'Solo se admiten archivos Excel XLSX o XLS.'], 422);
            }
        }
        $rutas = [];
        try {
            foreach (['archivo_seg', 'archivo_cfe'] as $campo) {
                $extension = strtolower(pathinfo($_FILES[$campo]['name'], PATHINFO_EXTENSION));
                $ruta = sys_get_temp_dir() . DIRECTORY_SEPARATOR . bin2hex(random_bytes(16)) . '.' . $extension;
                if (!move_uploaded_file($_FILES[$campo]['tmp_name'], $ruta)) {
                    throw new RuntimeException('No fue posible almacenar temporalmente los archivos.');
                }
                $rutas[$campo] = $ruta;
            }
            $python = getenv('PYTHON_BIN') ?: 'python';
            $script = dirname(__DIR__) . '/services/procesar_archivos.py';
            $comando = escapeshellcmd($python)
                . ' ' . escapeshellarg($script)
                . ' ' . escapeshellarg($rutas['archivo_seg'])
                . ' ' . escapeshellarg($rutas['archivo_cfe'])
                . ' 2>&1';
            $salida = shell_exec($comando);
            $resultado = is_string($salida) ? json_decode(trim($salida), true) : null;
            if (!is_array($resultado)) {
                $this->responder(['ok' => false, 'error' => 'El motor predictivo no devolvió un JSON válido.'], 500);
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

    public function confirmarVinculo(): void
    {
        $this->validarToken();
        $cct = trim((string) ($_POST['cct'] ?? ''));
        $rpu = trim((string) ($_POST['rpu'] ?? ''));
        $nombre = trim((string) ($_POST['nombre_recibo_cfe'] ?? ''));
        $poblacion = trim((string) ($_POST['poblacion_cfe'] ?? ''));
        $tarifa = trim((string) ($_POST['tarifa_cfe'] ?? ''));
        if ($cct === '' || $rpu === '' || mb_strlen($cct) > 50 || mb_strlen($rpu) > 20) {
            $this->responder(['ok' => false, 'error' => 'Los datos del vínculo no son válidos.'], 422);
        }
        try {
            if (EscuelaModel::rpuEnlazado($rpu)) {
                $this->responder(['ok' => false, 'error' => 'El RPU ya se encuentra enlazado.'], 409);
            }
            EscuelaModel::guardarVinculacion(
                $cct,
                $rpu,
                $nombre !== '' ? $nombre : null,
                $poblacion !== '' ? $poblacion : null,
                $tarifa !== '' ? $tarifa : null
            );
            $this->responder(['ok' => true, 'mensaje' => 'Vinculación guardada correctamente.']);
        } catch (Throwable $e) {
            $this->responder(['ok' => false, 'error' => 'No fue posible guardar la vinculación.'], 500);
        }
    }

    private function validarToken(): void
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf'] ?? '';
        if (!hash_equals($_SESSION['seg_csrf'] ?? '', $token)) {
            $this->responder(['ok' => false, 'error' => 'La sesión de seguridad no es válida.'], 419);
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

if ($accion === 'confirmar_vinculo') {
    $controlador->confirmarVinculo();
}

http_response_code(400);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => false, 'error' => 'Acción no reconocida.'], JSON_UNESCAPED_UNICODE);
