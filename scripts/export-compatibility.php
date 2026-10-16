<?php

declare(strict_types=1);

// Documentation comes from Engine's closed registry, including on PHP 8.2.
// No function is invoked or reflected and no application service is loaded.
$root = dirname(__DIR__);
$sibling = dirname($root) . '/phpaml-engine/src/BuiltinFunctions.php';
if (is_file($sibling)) {
    require_once $sibling;
} elseif (is_file($root . '/vendor/autoload.php')) {
    require_once $root . '/vendor/autoload.php';
}
if (!class_exists(\AML\Engine\BuiltinFunctions::class)) {
    fwrite(STDERR, "Install phpaml/engine before exporting the compatibility catalogue.\n");
    exit(1);
}

$names = \AML\Engine\BuiltinFunctions::NAMES;
if (count($names) !== count(array_unique($names))) {
    fwrite(STDERR, "Engine's native catalogue contains duplicate names.\n");
    exit(1);
}
sort($names, SORT_STRING);
$lines = [];
foreach (array_chunk($names, 8) as $chunk) {
    $lines[] = implode(', ', array_map(static fn (string $name): string => '`' . $name . '()`', $chunk));
}
$start = '<!-- native-catalogue:start -->';
$end = '<!-- native-catalogue:end -->';
$section = $start . "\n\n**" . count($names) . " fonctions et alias** déclarés dans `AML\\Engine\\BuiltinFunctions::NAMES`.\n\n"
    . implode(",\n", $lines) . ".\n\n" . $end;
$path = $root . '/docs/fr/compatibilite.md';
$previous = file_get_contents($path);
$updated = preg_replace('/' . preg_quote($start, '/') . '.*?' . preg_quote($end, '/') . '/s', $section, $previous, 1, $replacements);
if ($replacements !== 1 || !is_string($updated)) {
    fwrite(STDERR, "Missing native catalogue markers in {$path}.\n");
    exit(1);
}
if (in_array('--check', $argv, true)) {
    if ($updated !== $previous) {
        fwrite(STDERR, "Native compatibility catalogue is stale. Run composer docs:compatibility.\n");
        exit(1);
    }
    echo "Native compatibility catalogue matches Engine (" . count($names) . ").\n";
} else {
    file_put_contents($path, $updated);
    echo "Updated native compatibility catalogue (" . count($names) . ").\n";
}
