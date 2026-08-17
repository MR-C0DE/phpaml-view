<?php

declare(strict_types=1);

$root = dirname(__DIR__);
spl_autoload_register(static function (string $class) use ($root): void {
    $prefixes = [
        'AML\\View\\' => $root . '/src/',
        'AML\\Engine\\' => dirname($root) . '/phpaml-engine/src/',
    ];
    foreach ($prefixes as $prefix => $directory) {
        if (!str_starts_with($class, $prefix)) continue;
        $path = $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($path)) require $path;
        return;
    }
});
require $root . '/src/functions.php';
