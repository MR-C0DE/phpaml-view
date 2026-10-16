<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../examples/compatibilite/page.php';

use AML\View\{Renderer, Testing\TestView};
use Exemples\Compatibilite\Pages\CompatibilitePage;

$variants = [
    'valid' => null,
    'invalidJson' => '{',
    'invalidCode' => 'bad-code',
    'invalidEnum' => 'absent',
];
$sourceFor = static function (string $variant): string {
    $page = new CompatibilitePage();
    if ($variant === 'invalidJson') { return '{'; }
    $source = json_decode($page->source, true, flags: JSON_THROW_ON_ERROR);
    if ($variant === 'invalidCode') { $source['articles'][0]['code'] = 'bad-code'; }
    if ($variant === 'invalidEnum') { $source['articles'][0]['statut'] = 'absent'; }
    return json_encode($source, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
};
if (($argv[1] ?? '') === '--native') {
    $page = new CompatibilitePage();
    $page->source = $sourceFor($argv[2]);
    ob_start();
    $page->preparer();
    $page->preparer();
    $output = ob_get_clean();
    echo json_encode(['state' => [
        'source' => $page->source, 'message' => $page->message,
        'export' => $page->export, 'erreur' => $page->erreur,
        'preparations' => $page->preparations,
    ], 'output' => $output], JSON_THROW_ON_ERROR);
    exit;
}
$attribute = static function (string $html, string $name): array {
    if (!preg_match('/' . $name . '="([^"]+)"/', $html, $match)) { throw new RuntimeException('Missing ' . $name); }
    return json_decode(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'), true, flags: JSON_THROW_ON_ERROR);
};
$samples = [];
foreach (array_keys($variants) as $variant) {
    $page = new CompatibilitePage();
    $page->source = $sourceFor($variant);
    $html = (new Renderer())->render($page);
    $process = proc_open([PHP_BINARY, __FILE__, '--native', $variant], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $json = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0 || $stderr !== '') { throw new RuntimeException($variant . ': ' . $stderr); }
    $native = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    $view = new TestView($html);
    $view->click('Préparer le catalogue')->click('Préparer le catalogue');
    foreach ($native['state'] as $key => $value) {
        // Native error messages can differ in class spelling/source formatting.
        if ($key === 'erreur' && $variant !== 'valid') { continue; }
        $view->assertState($key, $value);
    }
    if ($variant !== 'valid') {
        $view->assertSee(explode(' : ', $native['state']['erreur'])[0]);
    }
    if (implode('', $view->consoleOutput()) !== $native['output']) { throw new RuntimeException($variant . ': console differs'); }
    if ($variant === 'valid') { $view->assertSee('2 articles · 40,00 $ · 2026-10-15')->assertSee('Café moulu'); }
    $samples[] = [
        'variant' => $variant, 'html' => $html,
        'initial' => $attribute($html, 'data-aml-state'),
        'config' => $attribute($html, 'data-aml-state-config'),
        'action' => $attribute($html, 'data-aml-client-click'),
        'expected' => $native['state'], 'output' => $native['output'],
    ];
}
if (in_array('--json', $argv, true)) { echo json_encode($samples, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE); }
else { echo 'Compatibility program: ' . count($samples) . " PHP/ViewTest scenarios passed.\n"; }
