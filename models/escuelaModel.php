<?php

require_once dirname(__DIR__) . '/services/conexion.php';

class EscuelaModel
{
    private PDO $conexion;

    public function __construct()
    {
        $this->conexion = Conexion::conectar();
    }

    public function obtenerEscuelas(): array
    {
        return $this->conexion
            ->query('SELECT CCT, NOMBRECT, MUNICIPIO, NOMBRELOC FROM escuelas')
            ->fetchAll();
    }

    public function reemplazarPrecarga(array $registros): void
    {
        $this->conexion->beginTransaction();
        try {
            $this->conexion->exec('DELETE FROM cfe_precarga');
            $consulta = $this->conexion->prepare(
                'INSERT INTO cfe_precarga (RPU, nombre_cfe, poblacion_cfe, tarifa_cfe, periodo_vence)
                 VALUES (:rpu, :nombre, :poblacion, :tarifa, :periodo)'
            );
            foreach ($registros as $registro) {
                $consulta->execute([
                    'rpu' => $registro['rpu'],
                    'nombre' => $registro['nombre_cfe'],
                    'poblacion' => $registro['poblacion_cfe'],
                    'tarifa' => $registro['tarifa_cfe'],
                    'periodo' => $registro['periodo_vence']
                ]);
            }
            $this->conexion->commit();
        } catch (Throwable $e) {
            if ($this->conexion->inTransaction()) {
                $this->conexion->rollBack();
            }
            throw $e;
        }
    }

    public function rpuRegistrado(string $rpu): bool
    {
        $consulta = $this->conexion->prepare('SELECT 1 FROM escuelas_rpu WHERE RPU = :rpu LIMIT 1');
        $consulta->execute(['rpu' => $rpu]);
        return (bool) $consulta->fetchColumn();
    }

    public function insertarVinculo(string $cct, string $rpu, ?string $nombre): bool
    {
        $consulta = $this->conexion->prepare(
            'INSERT INTO escuelas_rpu (CCT, RPU, nombre_recibo_cfe) VALUES (:cct, :rpu, :nombre)'
        );
        return $consulta->execute(['cct' => $cct, 'rpu' => $rpu, 'nombre' => $nombre]);
    }
}
