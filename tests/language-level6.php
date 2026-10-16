<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures/LanguageFunctionsService.php';
require __DIR__ . '/fixtures/LanguageFunctionsWeakService.php';
require __DIR__ . '/fixtures/LanguageFunctions.php';
use AML\View\Tests\Language\LanguageFunctions;
use AML\View\{Renderer, Testing\TestView};

$cases = [];
$scenarios = array_map(static fn ($name) => [$name, 18], ['captured', 'captureInitialize', 'offsetWarning', 'onlyReturn', 'emptyCallbackError', 'weakPropertyReference', 'calls', 'references', 'variadics', 'closures', 'recursiveClosure', 'callbacks', 'types', 'earlyReturn', 'additionalCalls', 'nestedClosures', 'refFailure', 'nestedDeclaration', 'badArgument', 'badReturn', 'absentReturn', 'absentArgument', 'badName', 'duplicateName', 'badReference', 'badCallable', 'badReturnInside', 'nestedError', 'invalidNever']);
foreach ($scenarios as [$method, $input]) {
    $page = new LanguageFunctions(); $page->handler = $method;
    ob_start();
    try { $html = (new Renderer())->render($page); } finally { $renderOutput = ob_get_clean(); }
    if ($renderOutput !== '' || $page->counter !== 0) throw new RuntimeException('Compilation executed application code');
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
foreach (['recursionLimit', 'invalidState'] as $method) {
    $page = new LanguageFunctions(); $page->handler = $method;
    $html = (new Renderer())->render($page);
    $attribute = static function (string $name) use ($html): array { preg_match('/' . $name . '="([^"]+)"/', $html, $match); return json_decode(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR); };
    $initial = $attribute('data-aml-state'); $config = $attribute('data-aml-state-config'); $action = $attribute('data-aml-client-click');
    $test = new TestView($html);
    try { $test->click('Run'); throw new RuntimeException('Call limit did not interrupt execution'); }
    catch (RangeException|TypeError $failure) { $test->assertState('counter', 0); }
    $cases[] = ['method' => $method, 'initial' => $initial, 'config' => $config, 'action' => $action, 'callLimit' => $method === 'recursionLimit', 'invalidState' => $method === 'invalidState'];
}
$page = new LanguageFunctions(); $page->handler = 'serverOnlyCall';
try { (new Renderer())->render($page); throw new RuntimeException('Server-only function was accepted'); }
catch (InvalidArgumentException $error) { if (!str_contains($error->getMessage(), 'file_put_contents') || !str_contains($error->getMessage(), 'LanguageFunctionsService.php:')) throw $error; }
if (is_file('/private/tmp/phpaml-level6-must-not-execute')) throw new RuntimeException('Compilation executed a service function');
$page = new LanguageFunctions(); $page->handler = 'objectDefaultCall';
try { (new Renderer())->render($page); throw new RuntimeException('Object default was accepted'); }
catch (InvalidArgumentException $error) { if (!str_contains($error->getMessage(), 'cannot instantiate objects')) throw $error; }
if (\AML\View\Tests\Language\Service\ConstructorProbe::$calls !== 0) throw new RuntimeException('Reflection executed a parameter default constructor');
if (in_array('--json', $argv, true)) echo json_encode($cases, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE);
else echo 'Level 6: ' . count($cases) . " PHP scenarios passed, including function calls, references and rollback.\n";
