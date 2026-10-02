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
