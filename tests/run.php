<?php
/**
 * tests/run.php
 * Runner mínimo para cuando no hay PHPUnit instalado (ver
 * tests/support/TestCaseShim.php y tests/bootstrap.php). Si tenés
 * PHPUnit real, usá `vendor/bin/phpunit` en cambio — mismos archivos
 * de test, sin cambiar nada.
 *
 * Uso: php tests/run.php   (normalmente invocado por tests/run-tests.sh,
 * que además levanta la base de datos y el servidor de test).
 */

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$archivosDeTest = glob(__DIR__ . '/*Test.php');
sort($archivosDeTest);

$clasesAntes = get_declared_classes();
foreach ($archivosDeTest as $archivo) {
    require $archivo;
}
$clasesNuevas = array_diff(get_declared_classes(), $clasesAntes);

$totalTests = 0;
$totalFallos = 0;
$fallosDetalle = [];

foreach ($clasesNuevas as $clase) {
    $reflexion = new ReflectionClass($clase);
    if (!$reflexion->isSubclassOf(\PHPUnit\Framework\TestCase::class) || $reflexion->isAbstract()) {
        continue;
    }

    $metodosDeTest = array_filter(
        $reflexion->getMethods(ReflectionMethod::IS_PUBLIC),
        fn(ReflectionMethod $m) => str_starts_with($m->getName(), 'test')
    );

    foreach ($metodosDeTest as $metodo) {
        $totalTests++;
        $instancia = $reflexion->newInstance();

        $metodoSetUp = $reflexion->getMethod('setUp');
        $metodoSetUp->setAccessible(true);
        $metodoTearDown = $reflexion->getMethod('tearDown');
        $metodoTearDown->setAccessible(true);

        try {
            $metodoSetUp->invoke($instancia);
            $instancia->{$metodo->getName()}();
            $metodoTearDown->invoke($instancia);
            echo ".";
        } catch (\Throwable $e) {
            $totalFallos++;
            $fallosDetalle[] = sprintf(
                "%s::%s\n    %s\n    en %s:%d",
                $clase,
                $metodo->getName(),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );
            echo "F";
        }
    }
}

echo "\n\n";

if ($fallosDetalle) {
    echo "Fallos:\n\n";
    foreach ($fallosDetalle as $i => $detalle) {
        echo ($i + 1) . ") {$detalle}\n\n";
    }
}

printf("Tests: %d, Fallos: %d\n", $totalTests, $totalFallos);

exit($totalFallos > 0 ? 1 : 0);
