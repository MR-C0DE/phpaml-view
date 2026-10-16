<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures/LanguageExceptionsService.php';
require __DIR__ . '/fixtures/LanguageExceptionsWeak.php';
require __DIR__ . '/fixtures/LanguageExceptions.php';

use AML\View\Tests\Exceptions\LanguageExceptions;
use AML\View\{Renderer, Testing\TestView};

if (in_array('--html', $argv, true)) {
    require __DIR__ . '/../examples/niveau-10/page.php';
    echo (new Renderer())->render(new \App\Examples\Erreurs\ErreursPage());
    exit;
}
if (in_array('--extra-json', $argv, true)) {
    $extra = [];
    foreach (['trace', 'invalidState'] as $method) {
        $page = new LanguageExceptions(); $page->handler = $method;
        $html = (new Renderer())->render($page); $test = new TestView($html);
        if ($method === 'trace') {
            $test->click('Run');
            $result = $test->state('result');
            if (!str_ends_with($result[0], 'LanguageExceptionsService.php') || $result[1] < 1 || count($result[2]) !== 2 || !str_contains($result[3], 'failDeep()')) throw new RuntimeException('ViewTest lost service trace');
        } else {
            try { $test->click('Run'); throw new RuntimeException('Throwable entered State'); }
            catch (TypeError $error) { $test->assertState('result', []); }
        }
        $extra[$method] = $html;
    }
    echo json_encode($extra, JSON_THROW_ON_ERROR); exit;
}
$cases = [];
$methods = ['simple', 'inherited', 'unionCatch', 'identityAndRethrow', 'previous', 'finallyThrows', 'finallyAfterCatchThrows', 'finallyCycle', 'finallyReturn', 'suppressed', 'loopControl', 'nativeErrors', 'throwExpression', 'badThrow', 'constructorArgs', 'assertion', 'assertionString', 'weakArguments', 'aliasedContracts', 'nestedLocals', 'unmatched', 'uncaughtService', 'catchThrows', 'emptyTry', 'exceptionClosure', 'badPrevious', 'unknownCatch'];
foreach ($methods as $method) {
    $page = new LanguageExceptions(); $page->handler = $method;
    ob_start(); try { $html = (new Renderer())->render($page); } finally { $renderOutput = ob_get_clean(); }
    if ($renderOutput !== '' || $page->count !== 0) throw new RuntimeException('Compilation executed application code');
    $attribute = static function (string $name) use ($html): array { preg_match('/' . $name . '="([^"]+)"/', $html, $match); return json_decode(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR); };
    $initial = $attribute('data-aml-state'); $config = $attribute('data-aml-state-config'); $action = $attribute('data-aml-client-click');
    $error = null; ob_start();
    try { $page->$method(); } catch (Throwable $failure) { $error = ['name' => $failure::class, 'message' => $failure->getMessage()]; } finally { $output = ob_get_clean(); }
    $expected = []; foreach (array_keys($initial) as $name) $expected[$name] = $page->$name;
    $test = new TestView($html);
    try { $test->click('Run'); $failed = false; } catch (Throwable $failure) { $failed = true; }
    if ($failed !== ($error !== null)) throw new RuntimeException("ViewTest {$method}: " . ($failure->getMessage() ?? 'unexpected success'));
    foreach (($error ? $initial : $expected) as $name => $value) {
        try { $test->assertState($name, $value); } catch (Throwable $failure) { throw new RuntimeException("{$method}.{$name}: " . $failure->getMessage(), previous: $failure); }
    }
    if (implode('', $test->consoleOutput()) !== $output) throw new RuntimeException("{$method}: console output differs");
    $cases[] = compact('method', 'initial', 'config', 'action', 'expected', 'error', 'output');
}
foreach (['unsupported', 'serverLog'] as $method) {
    $page = new LanguageExceptions(); $page->handler = $method;
    try { (new Renderer())->render($page); throw new RuntimeException('Unsupported exception code accepted'); }
    catch (InvalidArgumentException $error) { if (!str_contains($error->getMessage(), 'LanguageExceptions.php:')) throw $error; }
}
if (in_array('--json', $argv, true)) { echo json_encode($cases, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE); exit; }
echo 'Level 10: ' . count($cases) . ' native exception scenarios passed; assertions=' . ini_get('zend.assertions') . ".\n";
