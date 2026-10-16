<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../examples/reservation/page.php';

use AML\View\{Renderer, Testing\TestView};
use Exemples\Reservation\Pages\ReservationPage;

$variants = [
    'valid' => [],
    'leap' => ['date' => '2024-02-28'],
    'daylight' => ['date' => '2026-03-07'],
    'invalidDate' => ['date' => '2026-02-30'],
    'invalidEmail' => ['email' => 'adresse-invalide'],
    'invalidQuantity' => ['nuits' => '2.5'],
    'quantityLimit' => ['nuits' => '21'],
];
$state = static fn (ReservationPage $page): array => array_intersect_key(get_object_vars($page), array_flip(['date', 'nuits', 'email', 'message', 'export', 'erreur']));
if (($argv[1] ?? '') === '--native') {
    $page = new ReservationPage();
    foreach ($variants[$argv[2]] as $key => $value) { $page->$key = $value; }
    ob_start(); $page->valider(); $page->valider(); $output = ob_get_clean();
    echo json_encode(['state' => $state($page), 'output' => $output], JSON_THROW_ON_ERROR);
    exit;
}
$attribute = static function (string $html, string $name): array {
    if (!preg_match('/' . $name . '="([^"]+)"/', $html, $match)) { throw new RuntimeException('Missing ' . $name); }
    return json_decode(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'), true, flags: JSON_THROW_ON_ERROR);
};
$cases = [];
foreach ($variants as $variant => $inputs) {
    $page = new ReservationPage();
    foreach ($inputs as $key => $value) { $page->$key = $value; }
    $html = (new Renderer())->render($page);
    $process = proc_open([PHP_BINARY, __FILE__, '--native', $variant], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $json = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0 || $stderr !== '') { throw new RuntimeException($variant . ': ' . $stderr); }
    $native = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    $test = new TestView($html);
    $test->click('Préparer la réservation')->click('Préparer la réservation');
    foreach ($native['state'] as $key => $value) { $test->assertState($key, $value); }
    if (implode('', $test->consoleOutput()) !== $native['output']) { throw new RuntimeException($variant . ': console differs'); }
    $cases[] = [
        'variant' => $variant, 'html' => $html,
        'initial' => $attribute($html, 'data-aml-state'),
        'config' => $attribute($html, 'data-aml-state-config'),
        'action' => $attribute($html, 'data-aml-client-click'),
        'expected' => $native['state'], 'output' => $native['output'],
    ];
}
if (in_array('--json', $argv, true)) { echo json_encode($cases, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE); }
else { echo 'Reservation program: ' . count($cases) . " PHP/ViewTest scenarios passed.\n"; }
