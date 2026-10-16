<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures/CollectionObjectsPage.php';
use AML\View\Tests\Collections\CollectionObjectsPage;
use AML\View\{Renderer, Testing\TestView};
$methods = ['basic', 'write', 'quiet', 'coalesce', 'compound', 'increments', 'remove', 'nestedRead', 'nestedWrite', 'nestedCompound', 'nestedCoalesce', 'references', 'copyModify', 'objectOffsets', 'rawKeys', 'counts', 'valueArguments', 'spreadArguments', 'temporary', 'countConversions', 'weakCounts', 'evaluationOrder', 'predicates', 'named', 'callbacks', 'inherited', 'selfOffsets', 'destructure', 'plainRead', 'plainWrite', 'plainQuiet', 'plainUnset', 'plainCount', 'countModeError', 'countReturnError', 'countMissing', 'countExtra', 'countNamedError', 'getError', 'setError', 'existsError', 'unsetError', 'caught', 'arrayOnly', 'arrayMapError', 'invalidKey', 'evalAccess', 'anonymous', 'evalAnonymous'];
$normalize = static fn (string $text): string => preg_replace('/object\(class@anonymous.*?\)(?:#\d+)?/s', 'object(anonymous)', preg_replace('/(object\([^\n]*?\))#\d+/', '$1', $text));
if (($argv[1] ?? '') === '--native') {
    $page = new CollectionObjectsPage(); $error = null; $warnings = [];
    set_error_handler(static function ($severity, $message) use (&$warnings): bool { $warnings[] = $message; return true; }); ob_start();
    try { $page->{$argv[2]}(); } catch (Throwable $e) { $error = ['name' => $e::class]; }
    finally { $output = ob_get_clean(); restore_error_handler(); }
    echo json_encode(['expected' => ['result' => $page->result, 'counter' => $page->counter], 'error' => $error, 'warnings' => $warnings, 'output' => $output], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION); exit;
}
$attribute = static function (string $html, string $name): array { preg_match('/' . $name . '="([^"]+)"/', $html, $m); return json_decode(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR); };
$cases = [];
foreach ($methods as $method) {
    $page = new CollectionObjectsPage(); $page->handler = $method; $html = (new Renderer())->render($page);
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
$method = 'recursionLimit'; $page = new CollectionObjectsPage; $page->handler = $method; $html = (new Renderer())->render($page);
$test = new TestView($html);
try { $test->click('Run'); throw new RuntimeException('Unbounded count recursion'); }
catch (RangeException $e) { $test->assertState('counter', 0); }
$cases[] = ['method' => $method, 'callLimit' => true, 'initial' => $attribute($html, 'data-aml-state'), 'config' => $attribute($html, 'data-aml-state-config'), 'action' => $attribute($html, 'data-aml-client-click'), 'error' => ['name' => 'RangeError'], 'output' => '', 'warnings' => []];
$page = new CollectionObjectsPage; $page->handler = 'invalidState'; $html = (new Renderer)->render($page); $test = new TestView($html);
$failure = null; try { $test->click('Run'); } catch (Throwable $e) { $failure = $e; }
if (!$failure || !str_contains($failure->getMessage(), 'remain local')) throw new RuntimeException('Collection object escaped into State', previous: $failure);
$test->assertState('counter', 0)->assertState('result', []);
$cases[] = ['method' => 'invalidState', 'invalidState' => true, 'initial' => $attribute($html, 'data-aml-state'), 'config' => $attribute($html, 'data-aml-state-config'), 'action' => $attribute($html, 'data-aml-client-click'), 'output' => '', 'warnings' => []];
$diagnostics = json_decode(file_get_contents(__DIR__ . '/fixtures/collection-diagnostics.json'), true, 512, JSON_THROW_ON_ERROR);
foreach ($diagnostics as $label => $code) {
    $program = \AML\View\FrontendClosureCompiler::compileEvalProgram($code, ['source' => ['file' => 'collection-diagnostics.php', 'line' => 1]]);
    if (!isset($program['error'])) throw new RuntimeException('Accepted invalid collection declaration: ' . $label);
}
require __DIR__ . '/../examples/collections/page.php';
(new TestView((new Renderer)->render(new \Exemples\Collections\Pages\CollectionsPage)))->click('Ajouter une orange')->assertState('message', '2 articles : Pomme, Orange')->click('Ajouter une orange')->assertState('message', '3 articles : Pomme, Orange, Orange');
if (in_array('--json', $argv, true)) echo json_encode($cases, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE);
else echo 'Collection objects: ' . count($cases) . " PHP/ViewTest scenarios passed.\n";
