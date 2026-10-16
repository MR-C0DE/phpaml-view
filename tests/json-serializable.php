<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures/JsonSerializablePage.php';
use AML\View\Tests\JsonSerializable\JsonSerializablePage;
use AML\View\{Renderer, Testing\TestView};
$methods = ['values', 'selfValue', 'inheritance', 'enums', 'flags', 'recursiveArray', 'recursiveObjects', 'selfCycle', 'nestedEncode', 'errorContext', 'thrown', 'rollback', 'siblingEffects', 'callbacks', 'anonymousEval', 'depth', 'unsupportedReturns', 'validateType', 'stateEffect', 'staticEffect', 'throwThenRetry', 'nestedOuterObject', 'nestedThrown'];
$normalize = static fn (string $text): string => preg_replace('/object\(class@anonymous.*?\)(?:#\d+)?/s', 'object(anonymous)', preg_replace('/(object\([^\n]*?\))#\d+/', '$1', $text));
if (($argv[1] ?? '') === '--native') {
    $page = new JsonSerializablePage(); $error = null; $warnings = [];
    set_error_handler(static function ($severity, $message) use (&$warnings): bool { $warnings[] = $message; return true; }); ob_start();
    try { for ($i = 0; $i < (in_array($argv[2], ['staticGenerator', 'staticBudget'], true) ? 2 : 1); $i++) $page->{$argv[2]}(); } catch (Throwable $e) { $error = ['name' => $e::class]; }
    finally { $output = ob_get_clean(); restore_error_handler(); }
    echo json_encode(['expected' => ['result' => $page->result, 'counter' => $page->counter], 'error' => $error, 'warnings' => $warnings, 'output' => $output], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION); exit;
}
$attribute = static function (string $html, string $name): array { preg_match('/' . $name . '="([^"]+)"/', $html, $m); return json_decode(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR); };
$cases = [];
foreach ($methods as $method) {
    $repetitions = in_array($method, ['staticGenerator', 'staticBudget'], true) ? 2 : 1;
    $page = new JsonSerializablePage(); $page->handler = $method; $html = (new Renderer())->render($page);
    $initial = $attribute($html, 'data-aml-state'); $config = $attribute($html, 'data-aml-state-config'); $action = $attribute($html, 'data-aml-client-click');
    $process = proc_open([PHP_BINARY, __FILE__, '--native', $method], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $json = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0 || $stderr !== '') throw new RuntimeException($method . ': ' . $stderr);
    ['expected' => $expected, 'error' => $error, 'warnings' => $warnings, 'output' => $output] = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    $output = $normalize($output);
    $test = new TestView($html); $failure = null; $viewWarnings = [];
    set_error_handler(static function ($severity, $message) use (&$viewWarnings): bool { $viewWarnings[] = $message; return true; });
    try { for ($i = 0; $i < ($repetitions ?? 1); $i++) $test->click('Run'); } catch (Throwable $e) { $failure = $e; } finally { restore_error_handler(); }
    if (($failure !== null) !== ($error !== null) || $failure && $failure::class !== $error['name']) throw new RuntimeException($method . ': ' . ($failure?->getMessage() ?? 'unexpected success'), previous: $failure);
    try { foreach ($error ? $initial : $expected as $name => $value) $test->assertState($name, $value); }
    catch (Throwable $e) { throw new RuntimeException($method . ': ' . $e->getMessage(), previous: $e); }
    if (count($viewWarnings) !== count($warnings)) throw new RuntimeException($method . ': warnings ' . json_encode($viewWarnings) . ' expected ' . json_encode($warnings));
    if ($normalize(implode('', $test->consoleOutput())) !== $output) throw new RuntimeException($method . ': console output differs: ' . json_encode($test->consoleOutput()) . ' expected ' . json_encode($output));
    $cases[] = compact('repetitions','method','initial','config','action','expected','error','warnings','output');
}
$page = new JsonSerializablePage(); $page->handler = 'growthBoundary'; $html = (new Renderer())->render($page); $test = new TestView($html);
try { $test->click('Run'); throw new RuntimeException('Unbounded serializer chain accepted'); }
catch (RangeException $error) { $test->assertState('result', []); }
$cases[] = ['method' => 'growthBoundary', 'initial' => $attribute($html, 'data-aml-state'), 'config' => $attribute($html, 'data-aml-state-config'), 'action' => $attribute($html, 'data-aml-client-click'), 'error' => ['name'=>'RangeError'], 'output'=>'', 'warnings'=>[]];
try { (new Renderer())->render(new \AML\View\Tests\JsonSerializable\JsonSerializableDisplayPage()); throw new RuntimeException('Read-only serializer accepted'); }
catch (InvalidArgumentException $error) { if (!str_contains($error->getMessage(), 'event')) throw $error; }
if (\AML\View\Tests\JsonSerializable\DisplayCounter::$calls !== 0) throw new RuntimeException('Read-only serializer was executed before validation');
if (in_array('--json', $argv, true)) echo json_encode($cases, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE);
else echo 'JsonSerializable: ' . count($cases) . " PHP/ViewTest scenarios passed.\n";
