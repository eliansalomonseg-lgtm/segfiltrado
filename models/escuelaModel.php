<?php

require_once dirname(__DIR__) . '/services/conexion.php';

class EscuelaModel
{
    public static function rpuEnlazado(string $rpu): bool
    {
        $consulta = Conexion::conectar()->prepare('SELECT 1 FROM escuelas_rpu WHERE RPU = :rpu LIMIT 1');
        $consulta->execute(['rpu' => $rpu]);
        return (bool) $consulta->fetchColumn();
    }

    public static function guardarVinculacion(
        string $cct,
        string $rpu,
        ?string $nombreReciboCfe,
        ?string $poblacionCfe,
        ?string $tarifaCfe
    ): bool {
        $consulta = Conexion::conectar()->prepare(
            'INSERT INTO escuelas_rpu (CCT, RPU, nombre_recibo_cfe, poblacion_cfe, tarifa_cfe)
             VALUES (:cct, :rpu, :nombre, :poblacion, :tarifa)'
        );
        return $consulta->execute([
            'cct' => $cct,
            'rpu' => $rpu,
            'nombre' => $nombreReciboCfe,
            'poblacion' => $poblacionCfe,
            'tarifa' => $tarifaCfe
        ]);
    }
}
