<?php

declare(strict_types=1);

final class LectorPlanoCfe
{
    public function registros(string $archivo): iterable
    {
        $extension = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
        if ($extension === 'csv') {
            yield from $this->registrosCsv($archivo);
            return;
        }
        if ($extension !== 'xlsx') {
            throw new RuntimeException('El archivo plano debe estar en formato XLSX o CSV.');
        }

        $directorio = $this->extraerXlsx($archivo);
        try {
            $cadenas = $this->cargarCadenasCompartidas($directorio . DIRECTORY_SEPARATOR . 'xl' . DIRECTORY_SEPARATOR . 'sharedStrings.xml');
            $hojas = glob($directorio . DIRECTORY_SEPARATOR . 'xl' . DIRECTORY_SEPARATOR . 'worksheets' . DIRECTORY_SEPARATOR . 'sheet*.xml') ?: [];
            foreach ($hojas as $hoja) {
                $cabeceras = null;
                foreach ($this->filasXlsx($hoja, $cadenas) as [$numeroFila, $valores]) {
                    if ($cabeceras === null) {
                        $candidatas = $this->normalizarCabeceras($valores);
                        if ($this->sonCabecerasPlano($candidatas)) {
                            $cabeceras = $candidatas;
                        }
                        continue;
                    }
                    $registro = $this->asociarFila($cabeceras, $valores);
                    if ($this->valor($registro, ['rpu', 'clrpu']) === '') {
                        continue;
                    }
                    yield [$numeroFila, $registro];
                }
                if ($cabeceras !== null) {
                    return;
                }
            }
            throw new RuntimeException('No se encontró una hoja con las cabeceras RPU y TipoMov.');
        } finally {
            $this->eliminarDirectorio($directorio);
        }
    }

    public function valor(array $registro, array $claves): string
    {
        foreach ($claves as $clave) {
            $directa = trim($clave);
            if (array_key_exists($directa, $registro) && trim((string) $registro[$directa]) !== '') {
                return trim((string) $registro[$directa]);
            }
            $normalizada = $this->normalizarTexto($clave);
            if (array_key_exists($normalizada, $registro) && trim((string) $registro[$normalizada]) !== '') {
                return trim((string) $registro[$normalizada]);
            }
        }
        return '';
    }

    public function fecha(string $valor): ?string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }
        if (is_numeric($valor) && (float) $valor > 20000 && (float) $valor < 80000) {
            return (new DateTimeImmutable('1899-12-30'))->modify('+' . (int) floor((float) $valor) . ' days')->format('Y-m-d');
        }
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y', 'Y/m/d'] as $formato) {
            $fecha = DateTimeImmutable::createFromFormat('!' . $formato, $valor);
            if ($fecha instanceof DateTimeImmutable && $fecha->format($formato) === $valor) {
                return $fecha->format('Y-m-d');
            }
        }
        return null;
    }

    public function numero(string $valor): ?float
    {
        $valor = trim(str_replace(['$', ' ', "\xc2\xa0"], '', $valor));
        if ($valor === '' || $valor === '-' || strtoupper($valor) === 'N/A') {
            return null;
        }
        if (str_contains($valor, ',') && str_contains($valor, '.')) {
            $valor = str_replace(',', '', $valor);
        } elseif (str_contains($valor, ',')) {
            $valor = preg_match('/,\d{1,4}$/', $valor) ? str_replace(',', '.', $valor) : str_replace(',', '', $valor);
        }
        return is_numeric($valor) ? (float) $valor : null;
    }

    private function registrosCsv(string $archivo): iterable
    {
        $manejador = fopen($archivo, 'rb');
        if ($manejador === false) {
            throw new RuntimeException('No fue posible leer el archivo CSV.');
        }
        try {
            $cabeceras = null;
            $fila = 0;
            while (($valores = fgetcsv($manejador)) !== false) {
                $fila++;
                if ($cabeceras === null) {
                    $candidatas = $this->normalizarCabeceras($valores);
                    if ($this->sonCabecerasPlano($candidatas)) {
                        $cabeceras = $candidatas;
                    }
                    continue;
                }
                $registro = $this->asociarFila($cabeceras, $valores);
                if ($this->valor($registro, ['rpu', 'clrpu']) !== '') {
                    yield [$fila, $registro];
                }
            }
            if ($cabeceras === null) {
                throw new RuntimeException('No se encontraron las cabeceras RPU y TipoMov en el CSV.');
            }
        } finally {
            fclose($manejador);
        }
    }

    private function extraerXlsx(string $archivo): string
    {
        $directorio = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'plano_cfe_' . bin2hex(random_bytes(12));
        if (!mkdir($directorio, 0700, true) && !is_dir($directorio)) {
            throw new RuntimeException('No fue posible preparar el archivo plano.');
        }
        if (class_exists('ZipArchive')) {
            $zip = new ZipArchive();
            if ($zip->open($archivo) === true && $zip->extractTo($directorio)) {
                $zip->close();
                return $directorio;
            }
            $zip->close();
        } else {
            $comando = 'tar -xf ' . escapeshellarg($archivo) . ' -C ' . escapeshellarg($directorio) . ' 2>&1';
            shell_exec($comando);
            if (is_file($directorio . DIRECTORY_SEPARATOR . 'xl' . DIRECTORY_SEPARATOR . 'workbook.xml')) {
                return $directorio;
            }
        }
        $this->eliminarDirectorio($directorio);
        throw new RuntimeException('No fue posible abrir el XLSX. Habilita la extensión PHP zip o valida el archivo.');
    }

    private function cargarCadenasCompartidas(string $archivo): array
    {
        if (!is_file($archivo)) {
            return [];
        }
        $lector = new XMLReader();
        if (!$lector->open($archivo, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new RuntimeException('No fue posible leer las cadenas del XLSX.');
        }
        $cadenas = [];
        while ($lector->read()) {
            if ($lector->nodeType !== XMLReader::ELEMENT || $lector->localName !== 'si') {
                continue;
            }
            $nodo = simplexml_load_string($lector->readOuterXML());
            $textos = $nodo ? $nodo->xpath('.//*[local-name()="t"]') : [];
            $cadenas[] = implode('', array_map(static fn (SimpleXMLElement $texto): string => (string) $texto, $textos ?: []));
        }
        $lector->close();
        return $cadenas;
    }

    private function filasXlsx(string $archivo, array $cadenas): iterable
    {
        $lector = new XMLReader();
        if (!$lector->open($archivo, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new RuntimeException('No fue posible leer una hoja del XLSX.');
        }
        while ($lector->read()) {
            if ($lector->nodeType !== XMLReader::ELEMENT || $lector->localName !== 'row') {
                continue;
            }
            $numeroFila = (int) $lector->getAttribute('r');
            $nodo = simplexml_load_string($lector->readOuterXML());
            $valores = [];
            foreach ($nodo?->xpath('./*[local-name()="c"]') ?: [] as $celda) {
                $referencia = (string) ($celda['r'] ?? '');
                $columna = $this->indiceColumna($referencia);
                if ($columna < 0) {
                    continue;
                }
                $tipo = (string) ($celda['t'] ?? '');
                $valor = '';
                if ($tipo === 'inlineStr') {
                    $textos = $celda->xpath('./*[local-name()="is"]//*[local-name()="t"]');
                    $valor = implode('', array_map(static fn (SimpleXMLElement $texto): string => (string) $texto, $textos ?: []));
                } else {
                    $nodosValor = $celda->xpath('./*[local-name()="v"]');
                    $valor = isset($nodosValor[0]) ? (string) $nodosValor[0] : '';
                    if ($tipo === 's') {
                        $valor = $cadenas[(int) $valor] ?? '';
                    }
                }
                $valores[$columna] = $valor;
            }
            yield [$numeroFila, $valores];
        }
        $lector->close();
    }

    private function normalizarCabeceras(array $valores): array
    {
        $cabeceras = [];
        foreach ($valores as $columna => $valor) {
            $cabeceras[$columna] = $this->normalizarTexto((string) $valor);
        }
        return $cabeceras;
    }

    private function sonCabecerasPlano(array $cabeceras): bool
    {
        $tieneRpu = in_array('rpu', $cabeceras, true) || in_array('clrpu', $cabeceras, true);
        $tieneMovimiento = in_array('tipomov', $cabeceras, true) || in_array('tipomovimiento', $cabeceras, true);
        return $tieneRpu && $tieneMovimiento;
    }

    private function asociarFila(array $cabeceras, array $valores): array
    {
        $registro = [];
        foreach ($cabeceras as $columna => $cabecera) {
            if ($cabecera !== '') {
                $clave = $cabecera;
                $repeticion = 2;
                while (array_key_exists($clave, $registro)) {
                    $clave = $cabecera . '_' . $repeticion;
                    $repeticion++;
                }
                $registro[$clave] = trim((string) ($valores[$columna] ?? ''));
            }
        }
        return $registro;
    }

    private function indiceColumna(string $referencia): int
    {
        if (!preg_match('/^([A-Z]+)\d+$/i', $referencia, $coincidencia)) {
            return -1;
        }
        $resultado = 0;
        foreach (str_split(strtoupper($coincidencia[1])) as $letra) {
            $resultado = $resultado * 26 + (ord($letra) - 64);
        }
        return $resultado - 1;
    }

    private function normalizarTexto(string $valor): string
    {
        $valor = trim($valor);
        $convertido = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $valor);
        if ($convertido !== false) {
            $valor = $convertido;
        }
        return strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '', $valor));
    }

    private function eliminarDirectorio(string $directorio): void
    {
        if (!is_dir($directorio)) {
            return;
        }
        $archivos = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directorio, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($archivos as $archivo) {
            $archivo->isDir() ? rmdir($archivo->getPathname()) : unlink($archivo->getPathname());
        }
        rmdir($directorio);
    }
}
