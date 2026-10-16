<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures/LanguageLevelsThreeToFive.php';
use AML\View\Tests\Language\LanguageLevelsThreeToFive;
use AML\View\{Renderer, Testing\TestView};

$cases = [];
$scenarios = [];
foreach ([0, 12, 13, 18, '18', 18.0, 19, 70, null, false, true, '', '0', [], [1]] as $input) $scenarios[] = ['conditions', $input];
foreach (['loops', 'arrays', 'noMatch', 'singleNoMatch', 'badCount', 'badKey', 'badScalarOffset', 'missingOffset'] as $method) $scenarios[] = [$method, 18];
foreach ($scenarios as [$method, $input]) {
    $page = new LanguageLevelsThreeToFive(); $page->handler = $method; $page->input = $input;
    $html = (new Renderer())->render($page);
    $attribute = static function (string $name) use ($html): array {
        preg_match('/' . $name . '="([^"]+)"/', $html, $match);
        return json_decode(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR);
    };
    $initial = $attribute('data-aml-state'); $config = $attribute('data-aml-state-config'); $action = $attribute('data-aml-client-click');
    $warnings = []; $error = null;
    set_error_handler(static function ($severity, $message) use (&$warnings): bool { $warnings[] = $message; return true; });
    ob_start();
    try { $page->$method(); }
    catch (Throwable $failure) { $error = ['name' => $failure::class, 'line' => $failure->getLine()]; }
    finally { $output = ob_get_clean(); restore_error_handler(); }
    $expected = []; foreach (array_keys($initial) as $name) $expected[$name] = $page->$name;
    $test = new TestView($html);
    set_error_handler(static fn (): bool => true);
    try {
        try { $test->click('Run'); $failed = false; }
        catch (Throwable $failure) { $failed = true; }
        if ($failed !== ($error !== null)) throw new RuntimeException("ViewTest {$method}: " . ($failure->getMessage() ?? 'unexpected success'));
        foreach (($error ? $initial : $expected) as $name => $value) $test->assertState($name, $value);
        if (!$error && implode('', $test->consoleOutput()) !== $output) throw new RuntimeException("ViewTest {$method}: echo mismatch");
    } finally { restore_error_handler(); }
    $cases[] = compact('method', 'initial', 'config', 'action', 'expected', 'warnings', 'error', 'output');
}
foreach (['infinite', 'nestedBudget'] as $method) {
    $page = new LanguageLevelsThreeToFive(); $page->handler = $method;
    $html = (new Renderer())->render($page);
    $attribute = static function (string $name) use ($html): array { preg_match('/' . $name . '="([^"]+)"/', $html, $match); return json_decode(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR); };
    $initial = $attribute('data-aml-state'); $config = $attribute('data-aml-state-config'); $action = $attribute('data-aml-client-click');
    $test = new TestView($html);
    try { $test->click('Run'); throw new RuntimeException('Loop limit did not interrupt execution'); }
    catch (RangeException $failure) { $test->assertState('counter', 0); }
    $cases[] = ['method' => $method, 'initial' => $initial, 'config' => $config, 'action' => $action, 'loopLimit' => true];
}
if (in_array('--json', $argv, true)) echo json_encode($cases, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE);
else echo 'Levels 3–5: ' . count($cases) . " PHP scenarios passed, including loop bounds and rollback.\n";
