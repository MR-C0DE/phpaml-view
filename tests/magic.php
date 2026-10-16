<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures/MagicServices.php';
require __DIR__ . '/fixtures/MagicWeakServices.php';
require __DIR__ . '/fixtures/MagicPage.php';
use AML\View\Tests\Magic\MagicPage;
use AML\View\{Renderer, Testing\TestView};
$methods = ['referenceGet','notInvokable','readonlyUnsetClone','unsetWrite','unsetTwice','arrayCoalesce','arrayIncrement','arrayReference','weakString','weakReturn','magicFloat','explicitMagic','instanceStaticError','absentStaticProperty','read','write','quiet','coalesceAssign','remove','arrayRead','arrayWrite','arrayUnset','reference','nativeReference','returnedObject','pending','unsetDeclared','unsetTypedError','recursive','method','named','spread','duplicate','staticMethod','firstClass','invoke','invokeReference','invokeError','string','strictStringError','stringBuiltinError','stringBuiltins','badString','throwingString','cloned','privateClone','internalClone','readonlyClone','readonlyTwice','getterError','caught','evalMagic'];
if (($argv[1] ?? '') === '--native') {
    $page = new MagicPage(); $error = null; $warnings = [];
    set_error_handler(static function ($severity, $message) use (&$warnings): bool { $warnings[] = $message; return true; }); ob_start();
    try { $page->{$argv[2]}(); } catch (Throwable $e) { $error = ['name' => $e::class]; }
    finally { $output = ob_get_clean(); restore_error_handler(); }
    echo json_encode(['expected' => ['result' => $page->result, 'counter' => $page->counter], 'error' => $error, 'warnings' => $warnings, 'output' => $output], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION); exit;
}
$attribute = static function (string $html, string $name): array { preg_match('/' . $name . '="([^"]+)"/', $html, $m); return json_decode(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR); };
$cases = [];
foreach ($methods as $method) {
    $page = new MagicPage(); $page->handler = $method; $html = (new Renderer())->render($page);
    $initial = $attribute($html, 'data-aml-state'); $config = $attribute($html, 'data-aml-state-config'); $action = $attribute($html, 'data-aml-client-click');
    $process = proc_open([PHP_BINARY, __FILE__, '--native', $method], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $json = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0 || $stderr !== '') throw new RuntimeException($method . ': ' . $stderr);
    ['expected' => $expected, 'error' => $error, 'warnings' => $warnings, 'output' => $output] = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    $test = new TestView($html); $failure = null; $viewWarnings = [];
    set_error_handler(static function ($severity, $message) use (&$viewWarnings): bool { $viewWarnings[] = $message; return true; });
    try { $test->click('Run'); } catch (Throwable $e) { $failure = $e; } finally { restore_error_handler(); }
    if (($failure !== null) !== ($error !== null) || $failure && $failure::class !== $error['name']) throw new RuntimeException($method . ': ' . ($failure?->getMessage() ?? 'unexpected success'), previous: $failure);
    try { foreach ($error ? $initial : $expected as $name => $value) $test->assertState($name, $value); }
    catch (Throwable $e) { throw new RuntimeException($method . ': ' . $e->getMessage(), previous: $e); }
    if (count($viewWarnings) !== count($warnings)) throw new RuntimeException($method . ': warnings ' . json_encode($viewWarnings) . ' expected ' . json_encode($warnings));
    if (implode('', $test->consoleOutput()) !== $output) throw new RuntimeException($method . ': console output differs');
    $cases[] = compact('method','initial','config','action','expected','error','warnings','output');
}
foreach (['destructor' => 'magic method'] as $method => $message) {
    $page = new MagicPage; $page->handler = $method;
    try { (new Renderer())->render($page); throw new RuntimeException('Unsupported magic method accepted'); }
    catch (InvalidArgumentException $e) { if (!str_contains($e->getMessage(), $message)) throw $e; }
}
$page = new MagicPage; $page->handler = 'recursionLimit'; $html = (new Renderer())->render($page);
$test = new TestView($html);
try { $test->click('Run'); throw new RuntimeException('Recursive magic calls were unbounded'); }
catch (RangeException $e) { $test->assertState('counter', 0); }
$cases[] = ['method' => 'recursionLimit', 'callLimit' => true, 'initial' => $attribute($html, 'data-aml-state'), 'config' => $attribute($html, 'data-aml-state-config'), 'action' => $attribute($html, 'data-aml-client-click'), 'error' => ['name' => 'RangeError'], 'output' => '', 'warnings' => []];
require __DIR__ . '/../examples/methodes-magiques/page.php';
(new TestView((new Renderer())->render(new \Exemples\Magie\Pages\MagiePage())))
    ->click('Créer les profils')->assertState('message', 'André — 15 points | André (copie) — 99 points');
if (in_array('--json', $argv, true)) echo json_encode($cases, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE);
else echo 'Magic methods: ' . count($cases) . " PHP/ViewTest scenarios passed.\n";
