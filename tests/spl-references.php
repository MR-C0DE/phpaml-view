<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures/SplReferencesPage.php';
use AML\View\Tests\SplReferences\SplReferencesPage;
use AML\View\{Renderer, Testing\TestView};
$methods = ['cycleReference','referencePatternBoundary','basic', 'offsets', 'manual', 'object', 'copy', 'cloneIt', 'sorts', 'iteratorBuiltins', 'apply', 'evalSpl', 'spread', 'spreadCall', 'spreadGenerator', 'spreadKeyError', 'aliases', 'arrayAlias', 'arrayDetach', 'arrayRebind', 'arrayBind', 'evalAlias', 'foreachRef', 'foreachUnset', 'foreachAppend', 'foreachReplace', 'foreachDelete', 'foreachFunction', 'returnRef', 'returnLocal', 'returnNested', 'returnByValue', 'evalReference', 'arrayLiteralRef', 'stateRef', 'stateOffsetRef', 'referenceRollback', 'evalLiteralReference', 'methodReference', 'functionDeclarationRef', 'generatorForeachRef', 'copySharedRefs', 'aliasClosureRebind', 'nestedAlias', 'arrayHelperRefs', 'arraySortRefs', 'splForeachRef', 'splOffsetRef', 'magicReference', 'offsetReference', 'stateForeachRef', 'readonlyReference', 'iteratorReferenceCopy', 'splSortReenter', 'seekError', 'wrongType'];
$normalize = static fn (string $text): string => preg_replace('/object\(class@anonymous.*?\)(?:#\d+)?/s', 'object(anonymous)', preg_replace('/(object\([^\n]*?\))#\d+/', '$1', $text));
if (($argv[1] ?? '') === '--native') {
    $page = new SplReferencesPage(); $error = null; $warnings = [];
    set_error_handler(static function ($severity, $message) use (&$warnings): bool { $warnings[] = $message; return true; }); ob_start();
    try { $page->{$argv[2]}(); } catch (Throwable $e) { $error = ['name' => $e::class]; }
    finally { $output = ob_get_clean(); restore_error_handler(); }
    echo json_encode(['expected' => ['result' => $page->result, 'counter' => $page->counter], 'error' => $error, 'warnings' => $warnings, 'output' => $output], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION); exit;
}
$attribute = static function (string $html, string $name): array { preg_match('/' . $name . '="([^"]+)"/', $html, $m); return json_decode(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR); };
$cases = [];
foreach ($methods as $method) {
    $page = new SplReferencesPage(); $page->handler = $method; $html = (new Renderer())->render($page);
    $initial = $attribute($html, 'data-aml-state'); $config = $attribute($html, 'data-aml-state-config'); $action = $attribute($html, 'data-aml-client-click');
    $process = proc_open([PHP_BINARY, __FILE__, '--native', $method], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $json = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0 || $stderr !== '') throw new RuntimeException($method . ': ' . $stderr);
    ['expected' => $expected, 'error' => $error, 'warnings' => $warnings, 'output' => $output] = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    $output = $normalize($output);
    $test = new TestView($html); $failure = null; $viewWarnings = [];
    set_error_handler(static function ($severity, $message) use (&$viewWarnings): bool { $viewWarnings[] = $message; return true; });
    try { $test->click('Run'); } catch (Throwable $e) { $failure = $e; } finally { restore_error_handler(); }
    if (($failure !== null) !== ($error !== null) || $failure && $failure::class !== $error['name']) throw new RuntimeException($method . ': ' . ($failure?->getMessage() ?? 'unexpected success'), previous: $failure);
    try { foreach ($error ? $initial : $expected as $name => $value) $test->assertState($name, $value); }
    catch (Throwable $e) { throw new RuntimeException($method . ': ' . $e->getMessage(), previous: $e); }
    if (count($viewWarnings) !== count($warnings)) throw new RuntimeException($method . ': warnings ' . json_encode($viewWarnings) . ' expected ' . json_encode($warnings));
    if ($normalize(implode('', $test->consoleOutput())) !== $output) throw new RuntimeException($method . ': console output differs: ' . json_encode($test->consoleOutput()) . ' expected ' . json_encode($output));
    $cases[] = compact('method','initial','config','action','expected','error','warnings','output');
}
foreach (['splSubclassBoundary' => 'internal SPL'] as $method => $message) {
    $page = new SplReferencesPage(); $page->handler = $method;
    try { (new Renderer())->render($page); throw new RuntimeException($method . ': unsupported boundary accepted'); }
    catch (InvalidArgumentException $e) { if (!str_contains(strtolower($e->getMessage()), strtolower($message))) throw $e; }
}
foreach (['splFlagBoundary' => ['name' => 'ValueError', 'message' => 'flags'], 'propertyArrayReferenceBoundary' => ['name' => 'Error', 'message' => 'reference cannot be stored'], 'propertyBindingBoundary' => ['name' => 'Error', 'message' => 'Reference binding requires']] as $method => $error) {
    $page = new SplReferencesPage(); $page->handler = $method; $html = (new Renderer())->render($page); $test = new TestView($html);
    try { $test->click('Run'); throw new RuntimeException($method . ': unsupported boundary accepted'); }
    catch (Throwable $e) { if ($e::class !== $error['name'] || !str_contains($e->getMessage(), $error['message'])) throw $e; }
    $test->assertState('result', []);
    $cases[] = ['method' => $method, 'initial' => $attribute($html, 'data-aml-state'), 'config' => $attribute($html, 'data-aml-state-config'), 'action' => $attribute($html, 'data-aml-client-click'), 'error' => $error, 'warnings' => [], 'output' => ''];
}
// PHP has no automatic event transaction. Compare the successful native path,
// ViewTest's control path and ViewTest's failed-then-recovered path explicitly.
$page = new SplReferencesPage(); $page->handler = 'topology'; $html = (new Renderer())->render($page);
$control = new TestView($html); $control->click('Init')->click('Continue')->assertState('result', [3,3]);
$recovered = new TestView($html); $recovered->click('Init');
try { $recovered->click('Fail'); throw new LogicException('Expected failed event'); } catch (RuntimeException $e) { if (!str_starts_with($e->getMessage(), 'rollback topology')) throw $e; }
$recovered->assertState('result', [1,1])->click('Continue')->assertState('result', [3,3]);
$native = new SplReferencesPage(); $native->beginTopology(); $native->continueTopology();
if ($native->result !== [3,3]) throw new RuntimeException('Native reference control differs');
preg_match_all('/data-aml-client-click="([^"]+)"/', $html, $matches);
$actions = array_map(static fn ($value) => json_decode(html_entity_decode($value, ENT_QUOTES, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR), $matches[1]);
$cases[] = ['method' => 'rollbackTopology', 'initial' => $attribute($html, 'data-aml-state'), 'config' => $attribute($html, 'data-aml-state-config'), 'multiClick' => [
    ['label' => 'Init', 'action' => $actions[0], 'expected' => ['result' => [1,1], 'counter' => 0]],
    ['label' => 'Fail', 'action' => $actions[1], 'error' => 'RuntimeException', 'expected' => ['result' => [1,1], 'counter' => 0]],
    ['label' => 'Continue', 'action' => $actions[2], 'expected' => ['result' => [3,3], 'counter' => 0]],
]];
if (in_array('--json', $argv, true)) echo json_encode($cases, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE);
else echo 'SPL/references: ' . count($cases) . " PHP/ViewTest scenarios passed.\n";
