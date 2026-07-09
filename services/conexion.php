<?php

class Conexion
{
    private const HOST = 'localhost';
    private const BASE_DATOS = 'seg';
    private const USUARIO = 'root';
    private const CONTRASENA = '1234';
    private static ?PDO $conexion = null;

    private function __construct()
    {
    }

    public static function conectar(): PDO
    {
        if (self::$conexion instanceof PDO) {
            return self::$conexion;
        }

        try {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', self::HOST, self::BASE_DATOS);
            self::$conexion = new PDO(
                $dsn,
                self::USUARIO,
                self::CONTRASENA,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );

            return self::$conexion;
        } catch (PDOException $e) {
            throw new RuntimeException('No fue posible conectar con la base de datos.', 0, $e);
        }
    }
}
