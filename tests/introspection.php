<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures/IntrospectionPage.php';
use AML\View\Tests\Introspection\IntrospectionPage;
use AML\View\{Renderer, Testing\TestView};
$methods = ['shadowed','aliases','exists','kinds','parents','relations','methods','properties','publicVars','parentScope','childScope','callableScope','lateStatic','changedVars','copies','named','spread','callbacks','unknownMethods','unknownClass','badObject','badClass','badMember','missingArg','extraArg','duplicateArg','unknownArg','globalClass','globalCalled','globalParent','debug','debugNested','debugEmpty','debugRecursive','anonymous','evalIntrospection','evalDebug'];
$normalize = static fn (string $text): string => preg_replace('/object\(class@anonymous.*?\)(?:#\d+)?/s', 'object(anonymous)', preg_replace('/(object\([^\n]*?\))#\d+/', '$1', $text));
if (($argv[1] ?? '') === '--native') {
    $page = new IntrospectionPage(); $error = null; $warnings = [];
    set_error_handler(static function ($severity, $message) use (&$warnings): bool { $warnings[] = $message; return true; }); ob_start();
    try { $page->{$argv[2]}(); } catch (Throwable $e) { $error = ['name' => $e::class]; }
    finally { $output = ob_get_clean(); restore_error_handler(); }
    echo json_encode(['expected' => ['result' => $page->result, 'counter' => $page->counter], 'error' => $error, 'warnings' => $warnings, 'output' => $output], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION); exit;
}
$attribute = static function (string $html, string $name): array { preg_match('/' . $name . '="([^"]+)"/', $html, $m); return json_decode(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR); };
$cases = [];
foreach ($methods as $method) {
    $page = new IntrospectionPage(); $page->handler = $method; $html = (new Renderer())->render($page);
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
foreach (['debugInvalid' => 'TypeError', 'debugThrows' => 'RuntimeException', 'debugCaught' => null, 'debugLimit' => 'RangeException'] as $method => $errorName) {
    $page = new IntrospectionPage; $page->handler = $method; $html = (new Renderer())->render($page);
    $initial = $attribute($html, 'data-aml-state'); $config = $attribute($html, 'data-aml-state-config'); $action = $attribute($html, 'data-aml-client-click');
    $test = new TestView($html); $failure = null;
    try { $test->click('Run'); } catch (Throwable $e) { $failure = $e; }
    if (($failure?->getMessage() !== null) !== ($errorName !== null) || $failure && $failure::class !== $errorName) throw new RuntimeException($method . ': ' . ($failure?->getMessage() ?? 'unexpected success'), previous: $failure);
    $expected = $method === 'debugCaught' ? ['result' => ['caught'], 'counter' => 0] : $initial;
    foreach ($expected as $name => $value) $test->assertState($name, $value);
    $error = $errorName ? ['name' => $errorName === 'RangeException' ? 'RangeError' : $errorName] : null;
    $callLimit = $method === 'debugLimit'; $warnings = []; $output = $method === 'debugThrows' ? 'before' : '';
    if (implode('', $test->consoleOutput()) !== $output) throw new RuntimeException($method . ': console output differs');
    $cases[] = compact('method','initial','config','action','expected','error','warnings','output','callLimit');
}
foreach (['public static function __debugInfo() {}', 'public function __debugInfo($a = null) {}', 'public function __debugInfo(): mixed {}', 'private function __debugInfo() {}'] as $method) {
    $program = \AML\View\FrontendClosureCompiler::compileEvalProgram('return new class { ' . $method . ' };', ['source' => ['file' => 'introspection-diagnostics.php', 'line' => 1]]);
    if (!isset($program['error'])) throw new RuntimeException('Accepted invalid debug hook: ' . $method);
}
// The existing pure is_object predicate must remain usable in reactive text.
final class ObjectPredicatePage extends \AML\View\Page {
    #[\AML\View\State] public string $value = 'text';
    public function body(): \AML\View\View { return \AML\View\Text(fn () => is_object($this->value) ? 'object' : $this->value); }
}
(new TestView((new Renderer)->render(new ObjectPredicatePage)))->assertSee('text');
require __DIR__ . '/../examples/introspection/page.php';
$example = new TestView((new Renderer)->render(new \Exemples\Introspection\Pages\IntrospectionPage));
$example->click('Inspecter le profil')->assertState('message', 'André — Montréal · 2 propriétés publiques')->assertState('methodes', '__construct, etiquette, __debugInfo');
if (!str_contains(implode('', $example->consoleOutput()), '["points"]')) throw new RuntimeException('The example must dump its debug view to the console');
if (in_array('--json', $argv, true)) echo json_encode($cases, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE);
else echo 'Introspection: ' . count($cases) . " PHP/ViewTest scenarios passed.\n";
