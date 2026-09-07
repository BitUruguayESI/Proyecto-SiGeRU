<?php
/**
 * Comun/conexion.php
 * Conexión única a la base de datos (PDO), usada por todos los módulos
 * (api-usuarios, api-gestion, api-incidencias, api-recoleccion).
 *
 * Uso en cualquier otro archivo PHP:
 *   require_once __DIR__ . '/../Comun/conexion.php';
 *   $pdo = obtenerConexion();
 */

function obtenerConexion(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $host = getenv('SIGERU_DB_HOST') ?: '127.0.0.1';
        $puerto = getenv('SIGERU_DB_PORT') ?: '3306';
        $baseDatos = getenv('SIGERU_DB_NAME') ?: 'sigeru';
        $usuario = getenv('SIGERU_DB_USER') ?: 'root';
        $clave = getenv('SIGERU_DB_PASS') ?: '';

        $dsn = "mysql:host={$host};port={$puerto};dbname={$baseDatos};charset=utf8mb4";

        try {
            $pdo = new PDO($dsn, $usuario, $clave, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['exito' => false, 'mensaje' => 'Error de conexión a la base de datos.']));
        }
    }

    return $pdo;
}
