<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures/LanguageLevelTwo.php';
use AML\View\Tests\Language\LanguageLevelTwo;
use AML\View\{Renderer, Testing\ViewTest};

$cases = [];
foreach (['run', 'divideByZero', 'moduloByZero', 'fractionalModuloZero', 'invalidArithmetic', 'invalidPower', 'invalidIncrement', 'booleanIncrement', 'nullDecrement', 'stringIncrement', 'numericStringIncrement', 'stringDecrement'] as $method) {
    foreach ($method === 'run' ? [[18, true], [17, true], [25, false]] : [[18, true]] as [$age, $active]) {
        $page = new LanguageLevelTwo(); $page->age = $age; $page->active = $active; $page->handler = $method;
        $html = (new Renderer())->render($page);
        $attribute = static function ($name) use ($html) {
            preg_match('/' . $name . '="([^"]+)"/', $html, $match);
            return json_decode(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR);
        };
        $initial = $attribute('data-aml-state'); $config = $attribute('data-aml-state-config'); $action = $attribute('data-aml-client-click');
        $warnings = []; $error = null;
        set_error_handler(static function ($severity, $message) use (&$warnings): bool { $warnings[] = $message; return true; });
        ob_start();
        try { $page->$method(); }
        catch (Throwable $failure) { $error = ['name' => $failure::class, 'message' => $failure->getMessage(), 'line' => $failure->getLine()]; }
        finally { $dump = ob_get_clean(); restore_error_handler(); }
        $expected = [];
        foreach (array_keys($initial) as $name) $expected[$name] = $page->$name;
        $test = new \AML\View\Testing\TestView($html);
        set_error_handler(static fn (): bool => true);
        try {
            try { $test->click('Run'); $testFailed = false; }
            catch (Throwable $failure) { $testFailed = true; }
            if ($testFailed !== ($error !== null)) throw new RuntimeException("ViewTest parity: {$method}: " . ($failure->getMessage() ?? 'unexpected success'));
            foreach (($error ? $initial : $expected) as $name => $value) $test->assertState($name, $value);
            if ($dump !== '' && $test->consoleOutput() !== [$dump]) throw new RuntimeException('ViewTest var_dump evaluated an operand twice.');
        } finally { restore_error_handler(); }
        $cases[] = compact('method', 'initial', 'config', 'action', 'expected', 'warnings', 'error', 'dump');
    }
}
$comparison = new \AML\View\Tests\Language\LevelTwoComparisons();
$html = (new Renderer())->render($comparison);
$attribute = static function ($name) use ($html) {
    preg_match('/' . $name . '="([^"]+)"/', $html, $match);
    return json_decode(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR);
};
$action = $attribute('data-aml-client-click'); $config = $attribute('data-aml-state-config');
$values = [null, false, true, 0, 1, -1, 1.0, 0.0, 0.5, 12, 2, '', '0', '00', '1', '01', '1.0', '-1', '1e0', 'abc', '12abc', ' 12', '12 ', '2', '12', '9007199254740992', '9007199254740993', '9223372036854775807', 9007199254740992.0, "\u{1F600}", "\u{E000}"];
foreach ($values as $left) foreach ($values as $right) {
    $page = new \AML\View\Tests\Language\LevelTwoComparisons();
    $page->left = $left; $page->right = $right;
    $initial = ['left' => $left, 'right' => $right, 'result' => $page->result];
    $config['valueTypes']['left'] = gettype($left); $config['valueTypes']['right'] = gettype($right);
    $page->compare();
    $expected = [...$initial, 'result' => $page->result];
    $cases[] = ['method' => 'comparison:' . json_encode([$left, $right], JSON_PRESERVE_ZERO_FRACTION), 'initial' => $initial, 'config' => $config, 'action' => $action, 'expected' => $expected, 'warnings' => [], 'error' => null, 'dump' => ''];
}
if (in_array('--json', $argv, true)) echo json_encode($cases, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
else echo 'Level 2: ' . count($cases) . " native operator scenarios checked, including body(), precedence and failures.\n";
