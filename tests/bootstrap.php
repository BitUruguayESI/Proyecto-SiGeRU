<?php
/**
 * tests/bootstrap.php
 * Si ya corriste `composer require --dev phpunit/phpunit` (necesita
 * internet), se usa el PHPUnit real. Si no, se usa un shim mínimo con
 * la misma API (ver tests/support/TestCaseShim.php) para poder correr
 * la suite igual, con `php tests/run.php`.
 */

declare(strict_types=1);

$autoloadComposer = __DIR__ . '/../vendor/autoload.php';

if (file_exists($autoloadComposer)) {
    require $autoloadComposer;
} elseif (!class_exists(\PHPUnit\Framework\TestCase::class)) {
    require __DIR__ . '/support/TestCaseShim.php';
}

require __DIR__ . '/support/ApiClient.php';

/**
 * Administrador "semilla" de los tests.
 * Desde que registro.php exige sesión de Administrador (RN-22: solo
 * Administrador registra usuarios), la base de test necesita tener YA
 * un Administrador antes de que cualquier test pueda crear usuarios
 * por API. Como la base de test arranca vacía (solo el esquema, sin
 * datos_prueba.sql), se inserta este Administrador una sola vez,
 * directo por SQL, salteando la API. ApiClient lo usa automáticamente
 * (ver support/ApiClient.php) cuando un test llama a registro.php sin
 * haberse logueado antes — así ningún archivo de test necesita
 * cambios.
 */
const SIGERU_SEMILLA_CI         = '99999999';
const SIGERU_SEMILLA_CONTRASENA = 'SemillaTest1234';
const SIGERU_SEMILLA_EMAIL      = 'admin.semilla@sigeru.uy';

require __DIR__ . '/../Backend/src/Comun/conexion.php';

$pdoSemilla = obtenerConexion();
$existe = $pdoSemilla->prepare('SELECT ci FROM USUARIO WHERE ci = :ci');
$existe->execute(['ci' => SIGERU_SEMILLA_CI]);

if (!$existe->fetch()) {
    $pdoSemilla->beginTransaction();
    $pdoSemilla->prepare(
        'INSERT INTO USUARIO (ci, contrasena, email, nombre_completo, tipo_usuario, estado)
         VALUES (:ci, :contrasena, :email, :nombre, \'ADMINISTRADOR\', \'ACTIVO\')'
    )->execute([
        'ci'         => SIGERU_SEMILLA_CI,
        'contrasena' => password_hash(SIGERU_SEMILLA_CONTRASENA, PASSWORD_DEFAULT),
        'email'      => SIGERU_SEMILLA_EMAIL,
        'nombre'     => 'Administrador Semilla (tests)',
    ]);
    $pdoSemilla->prepare('INSERT INTO ADMINISTRADOR (ci_usuario) VALUES (:ci)')
        ->execute(['ci' => SIGERU_SEMILLA_CI]);
    $pdoSemilla->commit();
}
