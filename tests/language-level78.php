<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures/LanguageBuiltinsWeak.php';
require __DIR__ . '/fixtures/LanguageBuiltins.php';
use AML\View\Tests\Language\LanguageBuiltins;
use AML\View\{Renderer, Testing\TestView};
require __DIR__ . '/../examples/niveaux-7-8/page.php';

if (in_array('--example', $argv, true)) { echo (new Renderer())->render(new \Exemples\Pages\SaisiePage()); exit; }

$cases = [];
$scenarios = array_map(static fn ($method) => ['method' => $method], ['strings', 'collections', 'replacement', 'arguments', 'numbers', 'validation', 'weak', 'money', 'random', 'strictString', 'strictNull', 'strictInt', 'emptySeparator', 'invalidArray', 'invalidReplacement', 'invalidRandom', 'divisionZero', 'invalidMode', 'missingArgument', 'extraArgument', 'unknownArgument', 'duplicateArgument', 'badReference']);
$scenarios[] = ['method' => 'spreadReference'];
$scenarios[] = ['method' => 'trimMasks'];
foreach ([-2.675, -1.5, -1.25, -0.005, 0.0, 0.045, 0.285, 1.005, 1.25, 1.35, 2.675, 999.995, 1e-25, 123456789.555, 1e21] as $number) {
    foreach ([-3, -1, 0, 1, 2, 10, 25] as $precision) foreach ([1, 2, 3, 4] as $mode) $scenarios[] = compact('number', 'precision', 'mode') + ['method' => 'rounding'];
}
foreach ($scenarios as $scenario) {
    $page = new LanguageBuiltins(); $method = $page->handler = $scenario['method'];
    foreach (['number', 'precision', 'mode'] as $field) if (isset($scenario[$field])) $page->$field = $scenario[$field];
    ob_start(); try { $html = (new Renderer())->render($page); } finally { $renderOutput = ob_get_clean(); }
    if ($renderOutput !== '' || $page->counter !== 0) throw new RuntimeException('Compilation executed application code');
    $attribute = static function (string $name) use ($html): array { preg_match('/' . $name . '="([^"]+)"/', $html, $match); return json_decode(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR); };
    $initial = $attribute('data-aml-state'); $config = $attribute('data-aml-state-config'); $action = $attribute('data-aml-client-click');
    $warnings = []; $error = null;
    set_error_handler(static function ($severity, $message) use (&$warnings): bool { $warnings[] = $message; return true; });
    try { $page->$method(); } catch (Throwable $failure) { $error = ['name' => $failure::class]; } finally { restore_error_handler(); }
    $expected = []; foreach (array_keys($initial) as $name) $expected[$name] = $page->$name;
    $test = new TestView($html); set_error_handler(static fn (): bool => true);
    try {
        try { $test->click('Run'); $failed = false; } catch (Throwable $failure) { $failed = true; }
        if ($failed !== ($error !== null)) throw new RuntimeException("ViewTest {$method}: " . ($failure->getMessage() ?? 'unexpected success'));
        foreach (($error ? $initial : $expected) as $name => $value) $test->assertState($name, $value);
    } finally { restore_error_handler(); }
    $cases[] = compact('method', 'initial', 'config', 'action', 'expected', 'warnings', 'error');
}
foreach ([['0,10', '3', 'total'], ['12.50', '3', 'total'], ['12.500', '3', 'total'], ['1e2', '3', 'total'], ['2', '0', 'total'], ['2', '2x', 'total'], ['.50', '2', 'total'], ['', '3', 'bonjour'], ['', '', 'tirage'], ['', '', 'unknown']] as [$price, $quantity, $choice]) {
    $page = new \Exemples\Pages\SaisiePage(); $page->prix = $price; $page->quantite = $quantity; $page->choix = $choice;
    $html = (new Renderer())->render($page);
    $attribute = static function (string $name) use ($html): array { preg_match('/' . $name . '="([^"]+)"/', $html, $match); return json_decode(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR); };
    $initial = $attribute('data-aml-state'); $config = $attribute('data-aml-state-config'); $action = $attribute('data-aml-client-click');
    ob_start(); try { $page->valider(); } finally { $output = ob_get_clean(); }
    $test = new TestView($html); $test->click('Valider');
    if ($choice === 'tirage') {
        if (!preg_match('/^André, ton nombre : (?:[1-9]|[1-9][0-9]|100)$/', $test->state('resultat'))) throw new RuntimeException('Invalid random menu result');
        continue;
    }
    $expected = [...$initial, 'resultat' => $page->resultat];
    foreach ($expected as $name => $value) $test->assertState($name, $value);
    if (implode('', $test->consoleOutput()) !== $output) throw new RuntimeException('Menu console output differs');
    $method = 'menu:' . json_encode([$price, $quantity, $choice]); $error = null; $warnings = [];
    $cases[] = compact('method', 'initial', 'config', 'action', 'expected', 'warnings', 'error', 'output');
}
$test = new TestView((new Renderer())->render(new \Exemples\Pages\SaisiePage()));
$test->fill('prix', '0,10')->fill('quantite', '3')->click('Valider')->assertState('resultat', 'André : 0,30 €');
foreach (['invalidUtf8', 'formatLimit'] as $method) {
    $page = new LanguageBuiltins(); $page->handler = $method; $html = (new Renderer())->render($page);
    $test = new TestView($html);
    try { $test->click('Run'); throw new RuntimeException('Missing frontend guard'); } catch (RangeException) { $test->assertState('counter', 0); }
}
foreach (['cliRead', 'cliWrite', 'cliError', 'cliArgs', 'cliExit', 'cliDie', 'cliLine'] as $method) {
    $page = new LanguageBuiltins(); $page->handler = $method;
    try { (new Renderer())->render($page); throw new RuntimeException('Terminal-only code was accepted'); }
    catch (InvalidArgumentException $error) { if (!str_contains($error->getMessage(), 'cannot run in a browser event') || !str_contains($error->getMessage(), 'LanguageBuiltins.php:')) throw $error; }
}
if (in_array('--json', $argv, true)) echo json_encode($cases, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE);
else echo 'Levels 7–8: ' . count($cases) . " native PHP scenarios passed, plus 7 CLI diagnostics and 2 browser limits.\n";
