<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures/StaticServices.php';
require __DIR__ . '/fixtures/StaticsPage.php';
use AML\View\Tests\Statics\{StaticsPage, Counter};
use AML\View\{Renderer, Testing\TestView};
$methods = ['values','arithmetic','inherited','redeclared','privateLate','traits','arrays','sorting','reference','referenceError','uninitialized','initialized','quiet','nullableReference','uninitializedReference','unsetStatic','badType','strictType','missing','instanceAsStatic','privateAccess','protectedAccess','caught','closures','objectValue','callableValue','evalLiteral','evalDynamic','evalScope','evalClosure'];
if (($argv[1] ?? '') === '--native') {
    $page = new StaticsPage(); $error = null;
    try { $page->{$argv[2]}(); } catch (Throwable $e) { $error = ['name' => $e::class]; }
    echo json_encode(['expected' => ['result' => $page->result, 'counter' => $page->counter], 'error' => $error], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION); exit;
}
$attribute = static function (string $html, string $name): array { preg_match('/' . $name . '="([^"]+)"/', $html, $m); return json_decode(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR); };
$cases = [];
// Frontend defaults must come from declarations, not the current server static values.
Counter::$count = 900;
foreach ($methods as $method) {
    $page = new StaticsPage(); $page->handler = $method; $html = (new Renderer())->render($page);
    $initial = $attribute($html, 'data-aml-state'); $config = $attribute($html, 'data-aml-state-config'); $action = $attribute($html, 'data-aml-client-click');
    $command = [PHP_BINARY, __FILE__, '--native', $method];
    $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $json = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0 || $stderr !== '') throw new RuntimeException($method . ': ' . $stderr);
    ['expected' => $expected, 'error' => $error] = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    $test = new TestView($html); $failure = null;
    try { $test->click('Run'); } catch (Throwable $e) { $failure = $e; }
    if (($failure?->getMessage() !== null) !== ($error !== null) || $failure && $failure::class !== $error['name']) throw new RuntimeException($method . ': ' . ($failure?->getMessage() ?? 'unexpected success'), previous: $failure);
    foreach ($error ? $initial : $expected as $name => $value) $test->assertState($name, $value);
    $cases[] = compact('method','initial','config','action','expected','error') + ['warnings' => [], 'output' => ''];
}
if (Counter::$count !== 900) throw new RuntimeException('Compiler or ViewTest changed server statics');
// Different handlers on the same component share service storage and retained callable definitions.
$lifecycle = [];
foreach (['next','read','failAfterWrite','saveObject','useObject','saveClosure','useClosure'] as $method) {
    $page = new StaticsPage(); $page->handler = $method; $html = (new Renderer())->render($page);
    $lifecycle[$method] = $attribute($html, 'data-aml-client-click');
}
$markup = preg_replace('/<button\b.*?<\/button>/s', '', $html);
foreach ($lifecycle as $name => $action) $markup .= '<button data-aml-client-click="' . htmlspecialchars(json_encode($action, JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8') . '">' . $name . '</button>';
$test = new TestView($markup);
$test->click('next')->click('next')->click('read')->assertState('result', [2]);
try { $test->click('failAfterWrite'); } catch (RuntimeException) {}
$test->assertState('counter', 0)->click('read')->assertState('result', [3]);
$test->click('saveObject')->click('useObject')->click('useObject')->assertState('result', [12]);
$test->click('saveClosure')->click('useClosure')->assertState('result', [11]);
(new TestView($markup))->click('read')->assertState('result', [0]);
if (in_array('--json', $argv, true)) echo json_encode(['cases' => $cases, 'lifecycle' => $lifecycle, 'markup' => $markup], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE);
else echo 'Statics: ' . count($cases) . " PHP scenarios and component lifecycle passed.\n";
