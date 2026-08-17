<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$vendorAutoload = $root . '/vendor/autoload.php';
if (is_file($vendorAutoload)) {
    require $vendorAutoload;
}

spl_autoload_register(static function (string $class) use ($root): void {
    $prefixes = [
        'App\\' => __DIR__ . '/src/',
        'AML\\View\\' => $root . '/src/',
        'AML\\Engine\\' => dirname($root) . '/phpaml-engine/src/',
    ];
    foreach ($prefixes as $prefix => $directory) {
        if (!str_starts_with($class, $prefix)) continue;
        $file = $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) require_once $file;
        return;
    }
});

require_once $root . '/src/functions.php';
