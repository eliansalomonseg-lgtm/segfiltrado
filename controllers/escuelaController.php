<?php

declare(strict_types=1);

session_start();

require_once dirname(__DIR__) . '/models/escuelaModel.php';

class EscuelaController
{
    public function procesarPeriodos(): void
    {
        $this->validarToken();
        foreach (['archivo_mes_uno', 'archivo_mes_dos'] as $campo) {
            if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] !== UPLOAD_ERR_OK) {
                $this->responder(['ok' => false, 'error' => 'Carga los dos archivos Excel de CFE.'], 422);
            }
            if (!in_array(strtolower(pathinfo($_FILES[$campo]['name'], PATHINFO_EXTENSION)), ['xlsx', 'xls'], true)) {
                $this->responder(['ok' => false, 'error' => 'Los dos archivos deben ser Excel.'], 422);
            }
        }
        $modelo = new EscuelaModel();
        $archivoEscuelas = tempnam(sys_get_temp_dir(), 'seg_');
        if ($archivoEscuelas === false) {
            $this->responder(['ok' => false, 'error' => 'No fue posible preparar el catálogo SEG.'], 500);
        }
        try {
            file_put_contents(
                $archivoEscuelas,
                json_encode($modelo->obtenerEscuelas(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
            $python = getenv('PYTHON_BIN') ?: 'python';
            $script = dirname(__DIR__) . '/services/procesar_archivos.py';
            $comando = escapeshellcmd($python)
                . ' ' . escapeshellarg($script)
                . ' ' . escapeshellarg($_FILES['archivo_mes_uno']['tmp_name'])
                . ' ' . escapeshellarg($_FILES['archivo_mes_dos']['tmp_name'])
                . ' ' . escapeshellarg($archivoEscuelas)
                . ' 2>&1';
            $salida = shell_exec($comando);
            $resultado = is_string($salida) ? json_decode(trim($salida), true) : null;
            if (!is_array($resultado)) {
                $this->responder(['ok' => false, 'error' => 'El motor Python no devolvió una respuesta válida.'], 500);
            }
            if (empty($resultado['ok'])) {
                $this->responder($resultado, 422);
            }
            $modelo->reemplazarPrecarga($resultado['precarga'] ?? []);
            unset($resultado['precarga']);
            $this->responder($resultado);
        } catch (Throwable $e) {
            $this->responder(['ok' => false, 'error' => 'No fue posible procesar los periodos CFE.'], 500);
        } finally {
            if (is_file($archivoEscuelas)) {
                unlink($archivoEscuelas);
            }
        }
    }

    public function confirmarVinculo(): void
    {
        $this->validarToken();
        $cct = trim((string) ($_POST['cct'] ?? ''));
        $rpu = trim((string) ($_POST['rpu'] ?? ''));
        $nombre = trim((string) ($_POST['nombre_recibo_cfe'] ?? ''));
        if ($cct === '' || $rpu === '' || mb_strlen($cct) > 50 || mb_strlen($rpu) > 20 || mb_strlen($nombre) > 255) {
            $this->responder(['ok' => false, 'error' => 'El vínculo seleccionado no es válido.'], 422);
        }
        try {
            $modelo = new EscuelaModel();
            if ($modelo->rpuRegistrado($rpu)) {
                $this->responder(['ok' => false, 'error' => 'Este RPU ya está vinculado.'], 409);
            }
            $modelo->insertarVinculo($cct, $rpu, $nombre !== '' ? $nombre : null);
            $this->responder(['ok' => true, 'mensaje' => 'Vínculo confirmado correctamente.']);
        } catch (Throwable $e) {
            $this->responder(['ok' => false, 'error' => 'No fue posible guardar el vínculo.'], 500);
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

if ($accion === 'procesar_periodos') {
    $controlador->procesarPeriodos();
}

if ($accion === 'confirmar_vinculo') {
    $controlador->confirmarVinculo();
}

http_response_code(400);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => false, 'error' => 'Acción no reconocida.'], JSON_UNESCAPED_UNICODE);
