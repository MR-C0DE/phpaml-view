<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures/AnonymousPage.php';
use AML\View\Tests\Anonymous\AnonymousPage;
use AML\View\{Renderer, Testing\TestView};
$methods = ['evalStringable','floatDefault','variadicIntersection','closureDnf','defaultConstant','readonly','readonlyError','staticMethod','methodReference','anonymousDnf','dnfError','evalReturned','evalDistinct','evalIntersection','evalInheritance','basic','properties','inheritance','abstractParent','namedArguments','constructorDefault','statics','distinct','nested','closures','constants','magic','intersection','closureIntersection','referenceIntersection','propertyIntersection','anonymousIntersection','argumentError','returnError','propertyError','staticError','constructorError','visibilityError','uninitializedError','caught','evalLiteral','evalDynamic'];
if (($argv[1] ?? '') === '--native') {
    $page = new AnonymousPage(); $error = null; $warnings = [];
    set_error_handler(static function ($severity, $message) use (&$warnings): bool { $warnings[] = $message; return true; }); ob_start();
    try { $page->{$argv[2]}(); } catch (Throwable $e) { $error = ['name' => $e::class]; }
    finally { $output = ob_get_clean(); restore_error_handler(); }
    echo json_encode(['expected' => ['result' => $page->result, 'counter' => $page->counter], 'error' => $error, 'warnings' => $warnings, 'output' => $output], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION); exit;
}
$attribute = static function (string $html, string $name): array { preg_match('/' . $name . '="([^"]+)"/', $html, $m); return json_decode(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR); };
$cases = [];
foreach ($methods as $method) {
    $page = new AnonymousPage(); $page->handler = $method; $html = (new Renderer())->render($page);
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

// Compiler diagnostics use source text only; none of these classes is instantiated by PHP.
$negative = [
    'trait' => 'return new class { use UnknownTrait; };',
    'destructor' => 'return new class { public function __destruct() {} };',
    'missing interface method' => 'return new class implements \\AML\\View\\Tests\\Anonymous\\Named {};',
    'incompatible interface signature' => 'return new class implements \\AML\\View\\Tests\\Anonymous\\Named { public function name(): int { return 1; } };',
    'abstract parent method' => 'return new class extends \\AML\\View\\Tests\\Anonymous\\AbstractBase {};',
    'readonly without type' => 'return new class { public readonly $x; };',
    'invalid scalar default' => 'return new class { public int $n = "text"; };',
    'computed default' => 'return new class { public $value = strlen("abc"); };',
];
foreach ($negative as $label => $code) {
    $program = \AML\View\FrontendClosureCompiler::compileEvalProgram($code, ['source' => ['file' => 'anonymous-diagnostics.php', 'line' => 1]]);
    if (!isset($program['error'])) throw new RuntimeException('Accepted invalid anonymous declaration: ' . $label);
}
// The same declaration keeps its static cells across clicks within one component.
$page = new AnonymousPage; $page->handler = 'statics';
(new TestView((new Renderer)->render($page)))->click('Run')->click('Run')->assertState('result', [3, 4, 5, true, true, true]);

require __DIR__ . '/../examples/classes-anonymes/page.php';
(new TestView((new Renderer)->render(new \Exemples\ClassesAnonymes\Pages\ClassesAnonymesPage)))->click('Créer un profil')->assertState('message', 'André — 15 points · création 1')->click('Créer un profil')->assertState('message', 'André — 15 points · création 2');
if (in_array('--json', $argv, true)) echo json_encode($cases, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE);
else echo 'Anonymous classes and intersections: ' . count($cases) . " PHP/ViewTest scenarios passed.\n";
