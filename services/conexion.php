<?php

class Conexion
{
    public static function conectar(): PDO
    {
        try {
            return new PDO(
                'mysql:host=localhost;dbname=escuelaseg;charset=utf8mb4',
                'root',
                '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'
                ]
            );
        } catch (PDOException $e) {
            die('Error: ' . $e->getMessage());
        }
    }
}
