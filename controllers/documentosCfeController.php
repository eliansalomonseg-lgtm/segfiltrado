<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/services/auth.php';
require_once dirname(__DIR__) . '/services/conexion.php';

final class DocumentosCfeController
{
    private const DIRECTORIO = 'storage/reportes_cfe_pdf';
    private const TAMANO_MAXIMO = 26214400;

    public function manejar(): never
    {
        $accion = (string) ($_REQUEST['accion'] ?? '');
        if ($accion === 'ver_pdf' || $accion === 'descargar_pdf') {
            segRequireLogin('../login.php');
            $this->entregarDocumento($accion === 'descargar_pdf');
        }

        segRequireAdmin('../views/dashboard.php');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->responder(['ok' => false, 'error' => 'Metodo no permitido.'], 405);
        }
        $this->validarToken();

        if ($accion === 'subir_pdf') {
            $this->subir();
        }
        if ($accion === 'eliminar_pdf') {
            $this->eliminar();
        }
        $this->responder(['ok' => false, 'error' => 'Accion no reconocida.'], 400);
    }

    private function subir(): never
    {
        $archivo = $_FILES['documento_pdf'] ?? null;
        $anio = (int) ($_POST['anio'] ?? 0);
        $mes = (int) ($_POST['mes'] ?? 0);
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));

        if (!is_array($archivo) || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->responder(['ok' => false, 'error' => 'Selecciona un archivo PDF.'], 422);
        }
        if ($anio < 2020 || $anio > 2100 || $mes < 1 || $mes > 12) {
            $this->responder(['ok' => false, 'error' => 'Selecciona un periodo valido.'], 422);
        }
        if ((int) ($archivo['size'] ?? 0) < 1 || (int) $archivo['size'] > self::TAMANO_MAXIMO) {
            $this->responder(['ok' => false, 'error' => 'El PDF debe pesar hasta 25 MB.'], 422);
        }

        $rutaTemporal = (string) $archivo['tmp_name'];
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($rutaTemporal);
        $extension = strtolower(pathinfo((string) $archivo['name'], PATHINFO_EXTENSION));
        if ($mime !== 'application/pdf' || $extension !== 'pdf') {
            $this->responder(['ok' => false, 'error' => 'El archivo seleccionado no es un PDF valido.'], 422);
        }

        $directorio = dirname(__DIR__) . DIRECTORY_SEPARATOR . self::DIRECTORIO;
        if (!is_dir($directorio) && !mkdir($directorio, 0750, true) && !is_dir($directorio)) {
            $this->responder(['ok' => false, 'error' => 'No fue posible preparar el almacenamiento de documentos.'], 500);
        }

        $nombreSeguro = sprintf('%04d-%02d_%s.pdf', $anio, $mes, bin2hex(random_bytes(12)));
        $rutaRelativa = self::DIRECTORIO . '/' . $nombreSeguro;
        $rutaFinal = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rutaRelativa);
        $rutaOriginal = $rutaFinal . '.origen';
        if (!move_uploaded_file($rutaTemporal, $rutaOriginal)) {
            $this->responder(['ok' => false, 'error' => 'No fue posible preparar el PDF.'], 500);
        }
        try {
            $compresion = $this->comprimirPdf($rutaOriginal, $rutaFinal);
        } catch (Throwable $e) {
            if (is_file($rutaOriginal)) {
                unlink($rutaOriginal);
            }
            $this->responder(['ok' => false, 'error' => 'No fue posible comprimir el PDF: ' . $e->getMessage()], 422);
        }

        try {
            $conexion = Conexion::conectar();
            $this->asegurarTabla($conexion);
            $consulta = $conexion->prepare(
                'INSERT INTO cfe_documentos_pdf (anio, mes, nombre_original, ruta_archivo, descripcion, tamano_bytes, usuario_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $consulta->execute([
                $anio,
                $mes,
                mb_substr((string) $archivo['name'], 0, 255),
                $rutaRelativa,
                $descripcion !== '' ? mb_substr($descripcion, 0, 255) : null,
                (int) $compresion['final'],
                (int) (segCurrentUser()['id'] ?? 0)
            ]);
        } catch (Throwable $e) {
            if (is_file($rutaFinal)) {
                unlink($rutaFinal);
            }
            $this->responder(['ok' => false, 'error' => 'No fue posible registrar el documento.'], 500);
        }
        $this->responder([
            'ok' => true,
            'mensaje' => $compresion['comprimido']
                ? 'Documento CFE publicado y comprimido correctamente.'
                : ($compresion['sin_compresor']
                    ? 'Documento CFE publicado sin compresion. Ghostscript no esta instalado en el servidor.'
                    : 'Documento CFE publicado. El archivo ya tenia un tamaño optimizado.'),
            'tamano_original' => $compresion['original'],
            'tamano_final' => $compresion['final']
        ]);
    }

    private function eliminar(): never
    {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id < 1) {
            $this->responder(['ok' => false, 'error' => 'Documento no valido.'], 422);
        }
        $conexion = Conexion::conectar();
        $this->asegurarTabla($conexion);
        $consulta = $conexion->prepare('SELECT ruta_archivo FROM cfe_documentos_pdf WHERE id = ? LIMIT 1');
        $consulta->execute([$id]);
        $documento = $consulta->fetch();
        if (!$documento) {
            $this->responder(['ok' => false, 'error' => 'El documento ya no existe.'], 404);
        }
        $conexion->prepare('DELETE FROM cfe_documentos_pdf WHERE id = ?')->execute([$id]);
        $ruta = $this->rutaSegura((string) $documento['ruta_archivo']);
        if ($ruta !== null && is_file($ruta)) {
            unlink($ruta);
        }
        $this->responder(['ok' => true, 'mensaje' => 'Documento eliminado.']);
    }

    private function entregarDocumento(bool $descargar): never
    {
        $id = (int) ($_GET['id'] ?? 0);
        if ($id < 1) {
            http_response_code(404);
            exit;
        }
        $conexion = Conexion::conectar();
        $this->asegurarTabla($conexion);
        $consulta = $conexion->prepare('SELECT nombre_original, ruta_archivo FROM cfe_documentos_pdf WHERE id = ? LIMIT 1');
        $consulta->execute([$id]);
        $documento = $consulta->fetch();
        $ruta = $documento ? $this->rutaSegura((string) $documento['ruta_archivo']) : null;
        if ($ruta === null || !is_file($ruta)) {
            http_response_code(404);
            exit;
        }
        $nombre = preg_replace('/[^A-Za-z0-9._ -]/', '_', (string) $documento['nombre_original']) ?: 'documento-cfe.pdf';
        header('Content-Type: application/pdf');
        header('Content-Length: ' . (string) filesize($ruta));
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: ' . ($descargar ? 'attachment' : 'inline') . '; filename="' . $nombre . '"');
        readfile($ruta);
        exit;
    }

    private function asegurarTabla(PDO $conexion): void
    {
        $conexion->exec(
            'CREATE TABLE IF NOT EXISTS cfe_documentos_pdf (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                anio SMALLINT UNSIGNED NOT NULL,
                mes TINYINT UNSIGNED NOT NULL,
                nombre_original VARCHAR(255) NOT NULL,
                ruta_archivo VARCHAR(255) NOT NULL,
                descripcion VARCHAR(255) NULL,
                tamano_bytes INT UNSIGNED NOT NULL DEFAULT 0,
                usuario_id INT UNSIGNED NULL,
                creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_cfe_documentos_periodo (anio, mes)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    private function comprimirPdf(string $origen, string $destino): array
    {
        $ghostscript = $this->localizarGhostscript();
        if ($ghostscript === null) {
            if (!rename($origen, $destino)) {
                throw new RuntimeException('No fue posible guardar el PDF.');
            }
            return [
                'original' => (int) filesize($destino),
                'final' => (int) filesize($destino),
                'comprimido' => false,
                'sin_compresor' => true
            ];
        }

        $temporal = $destino . '.comprimido';
        $comando = escapeshellarg($ghostscript)
            . ' -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/ebook'
            . ' -dNOPAUSE -dQUIET -dBATCH -sOutputFile=' . escapeshellarg($temporal)
            . ' ' . escapeshellarg($origen) . ' 2>&1';
        $salida = shell_exec($comando);
        if (!is_file($temporal) || filesize($temporal) < 1) {
            if (is_file($temporal)) {
                unlink($temporal);
            }
            throw new RuntimeException(trim(strip_tags((string) $salida)) ?: 'Ghostscript no genero un archivo valido.');
        }

        $tamanoOriginal = (int) filesize($origen);
        $tamanoComprimido = (int) filesize($temporal);
        $usarComprimido = $tamanoComprimido < $tamanoOriginal;
        $rutaElegida = $usarComprimido ? $temporal : $origen;
        if (!rename($rutaElegida, $destino)) {
            if (is_file($temporal)) {
                unlink($temporal);
            }
            if (is_file($origen)) {
                unlink($origen);
            }
            throw new RuntimeException('No fue posible finalizar el archivo comprimido.');
        }
        $rutaNoElegida = $usarComprimido ? $origen : $temporal;
        if (is_file($rutaNoElegida)) {
            unlink($rutaNoElegida);
        }

        return [
            'original' => $tamanoOriginal,
            'final' => (int) filesize($destino),
            'comprimido' => $usarComprimido,
            'sin_compresor' => false
        ];
    }

    private function localizarGhostscript(): ?string
    {
        $configurado = trim((string) getenv('GHOSTSCRIPT_BIN'));
        if ($configurado !== '' && is_file($configurado)) {
            return $configurado;
        }
        $instalaciones = glob('C:\\Program Files\\gs\\gs*\\bin\\gswin64c.exe') ?: [];
        rsort($instalaciones, SORT_NATURAL);
        foreach ($instalaciones as $instalacion) {
            if (is_file($instalacion)) {
                return $instalacion;
            }
        }
        foreach (['gswin64c.exe', 'gswin32c.exe'] as $ejecutable) {
            $ruta = trim((string) shell_exec('where ' . $ejecutable . ' 2>NUL'));
            if ($ruta !== '' && is_file(strtok($ruta, "\r\n"))) {
                return strtok($ruta, "\r\n");
            }
        }
        return null;
    }

    private function rutaSegura(string $rutaRelativa): ?string
    {
        if (!preg_match('#^storage/reportes_cfe_pdf/[A-Za-z0-9_-]+\.pdf$#', $rutaRelativa)) {
            return null;
        }
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rutaRelativa);
    }

    private function validarToken(): void
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf'] ?? '';
        if (!hash_equals($_SESSION['seg_csrf'] ?? '', (string) $token)) {
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

(new DocumentosCfeController())->manejar();
