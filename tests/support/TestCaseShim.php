<?php
/**
 * tests/support/TestCaseShim.php
 *
 * Implementación mínima, compatible con la API de
 * PHPUnit\Framework\TestCase (assertSame, assertTrue, setUp, etc.),
 * para poder correr esta misma suite de tests SIN tener PHPUnit
 * instalado (este entorno de desarrollo no tiene salida a internet
 * para bajarlo de Packagist).
 *
 * bootstrap.php sólo carga este archivo si NO existe vendor/autoload.php
 * (es decir, si todavía no corriste `composer require --dev
 * phpunit/phpunit`). En cuanto lo instales, PHPUnit real reemplaza
 * esta clase automáticamente y los mismos archivos de test corren sin
 * cambiar una línea.
 */

namespace PHPUnit\Framework;

abstract class TestCase
{
    protected function setUp(): void
    {
    }

    protected function tearDown(): void
    {
    }

    private static function fallar(string $mensaje): void
    {
        throw new \RuntimeException($mensaje);
    }

    public static function assertSame($esperado, $real, string $mensaje = ''): void
    {
        if ($esperado !== $real) {
            self::fallar($mensaje !== '' ? $mensaje : sprintf(
                'Failed asserting that %s is identical to %s.',
                var_export($real, true),
                var_export($esperado, true)
            ));
        }
    }

    public static function assertEquals($esperado, $real, string $mensaje = ''): void
    {
        if ($esperado != $real) {
            self::fallar($mensaje !== '' ? $mensaje : sprintf(
                'Failed asserting that %s equals %s.',
                var_export($real, true),
                var_export($esperado, true)
            ));
        }
    }

    public static function assertTrue($condicion, string $mensaje = ''): void
    {
        if ($condicion !== true) {
            self::fallar($mensaje !== '' ? $mensaje : 'Failed asserting that false is true.');
        }
    }

    public static function assertFalse($condicion, string $mensaje = ''): void
    {
        if ($condicion !== false) {
            self::fallar($mensaje !== '' ? $mensaje : 'Failed asserting that true is false.');
        }
    }

    public static function assertNull($valor, string $mensaje = ''): void
    {
        if ($valor !== null) {
            self::fallar($mensaje !== '' ? $mensaje : 'Failed asserting that value is null.');
        }
    }

    public static function assertNotEmpty($valor, string $mensaje = ''): void
    {
        if (empty($valor)) {
            self::fallar($mensaje !== '' ? $mensaje : 'Failed asserting that value is not empty.');
        }
    }

    public static function assertArrayHasKey($clave, $arreglo, string $mensaje = ''): void
    {
        if (!is_array($arreglo) || !array_key_exists($clave, $arreglo)) {
            self::fallar($mensaje !== '' ? $mensaje : "Failed asserting that array has key '{$clave}'.");
        }
    }

    public static function assertGreaterThan($esperado, $real, string $mensaje = ''): void
    {
        if (!($real > $esperado)) {
            self::fallar($mensaje !== '' ? $mensaje : "Failed asserting that {$real} is greater than {$esperado}.");
        }
    }

    public static function assertStringContainsString(string $aguja, string $pajar, string $mensaje = ''): void
    {
        if (strpos($pajar, $aguja) === false) {
            self::fallar($mensaje !== '' ? $mensaje : "Failed asserting that '{$pajar}' contains '{$aguja}'.");
        }
    }

    public static function assertCount(int $esperado, $contable, string $mensaje = ''): void
    {
        $real = is_countable($contable) ? count($contable) : -1;
        if ($real !== $esperado) {
            self::fallar($mensaje !== '' ? $mensaje : "Failed asserting that count {$real} matches expected {$esperado}.");
        }
    }
}
