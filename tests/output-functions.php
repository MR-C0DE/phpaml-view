<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures/OutputLibrary.php';
use AML\View\Tests\Output\OutputLibrary;
use AML\View\{Renderer, Testing\TestView};

if (in_array('--example', $argv, true)) { require __DIR__ . '/../examples/sorties-console/page.php'; echo (new Renderer())->render(new \Exemples\Pages\SortiesConsolePage()); exit; }
$values = [null, true, false, 0, 1, -1, 1.0, -0.0, 0.1 + 0.2, 9007199254740991, '', 'André 👩‍💻', "\\'\"\n\r\t\0\0", '<script>alert(1)</script>', [], [1, true, null], ['a' => ['x' => [false, null, 1.0, -0.0]], 'z' => []], [8 => 'eight', '02' => 'zero two', 1 => 'one', '' => []], ["a\0'b\\" => "\0"]];
foreach ([-300, -30, -10, -5, -4, -1, 0, 10, 13, 14, 16, 17, 20, 30, 100, 300] as $exponent) foreach ([1.0, -1.0, 1.2345678901234567] as $coefficient) $values[] = $coefficient * 10 ** $exponent;
$cases = [];
foreach (['print_r', 'var_export'] as $function) foreach ($values as $value) foreach ([[$value], [$value, false], [$value, true], ['return' => true, 'value' => $value]] as $arguments) $cases[] = [$function, $arguments, 'run'];
foreach (['print_r', 'var_export'] as $function) foreach ([[], ['x', null], ['x', []], ['x', true, false], ['invalid' => 1]] as $arguments) $cases[] = [$function, $arguments, 'run'];
foreach (['callbacks', 'special', 'caught', 'uncaught', ...(enum_exists('RoundingMode') ? ['enumValues'] : [])] as $handler) $cases[] = [$handler, [], $handler];
foreach ([-1, 1, 2, 5, 14, 17, 30, 100] as $precision) $cases[] = ['precision', [], 'precision', $precision];
$json = in_array('--json', $argv, true); $samples = 0; if ($json) echo '[';
foreach ($cases as $index => [$function, $arguments, $handler]) {
    if (($argv[1] ?? '') === '--case' && $function !== $argv[2]) continue;
    $name = $function . ':' . $index;
    ini_set('precision', (string) ($cases[$index][3] ?? 14)); ini_set('serialize_precision', (string) ($cases[$index][3] ?? -1));
    $page = new OutputLibrary(); $page->functionName = $function; $page->arguments = $arguments; $page->handler = $handler;
    $html = (new Renderer())->render($page);
    $attribute = static function (string $name) use ($html): array { preg_match('/' . $name . '="([^"]+)"/', $html, $match); return json_decode(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR); };
    $initial = $attribute('data-aml-state'); $config = $attribute('data-aml-state-config'); $action = $attribute('data-aml-client-click');
    $error = null; ob_start();
    try { $page->{$handler}(); } catch (Throwable $failure) { $error = ['name' => $failure::class, 'message' => $failure->getMessage()]; }
    finally { $output = ob_get_clean(); }
    $expected = ['functionName' => $function, 'arguments' => $arguments, 'result' => $page->result];
    $test = new TestView($html); $failure = null;
    try { $test->click('Run'); } catch (Throwable $caught) { $failure = $caught; }
    if (($failure !== null) !== ($error !== null) || $failure && $failure::class !== $error['name']) throw new RuntimeException("ViewTest {$name}: " . ($failure ? $failure::class . ': ' . $failure->getMessage() : 'unexpected success'));
    foreach (($error ? $initial : $expected) as $key => $value) $test->assertState($key, $value);
    if (implode('', $test->consoleOutput()) !== $output) throw new RuntimeException("ViewTest {$name}: console output differs");
    if ($json) echo ($samples ? ',' : ''), json_encode(compact('name', 'initial', 'config', 'action', 'expected', 'error', 'output'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    $samples++;
}
ini_set('precision', '14'); ini_set('serialize_precision', '-1');
foreach ([new class extends \AML\View\Page {
    #[\AML\View\State] public array $data = ['a' => 1];
    public function body(): \AML\View\View { return \AML\View\Text(function (): string { return print_r($this->data); }); }
}, new class extends \AML\View\Page {
    #[\AML\View\State] public array $data = ['a' => 1];
    public function body(): \AML\View\View { return \AML\View\Text(function (): string { return implode('', array_map('var_export', $this->data)); }); }
}] as $impure) {
    ob_start();
    try { (new Renderer())->render($impure); throw new RuntimeException('Reactive output was accepted'); }
    catch (InvalidArgumentException $error) { if (!str_contains($error->getMessage(), 'return: true') && !str_contains($error->getMessage(), 'must be pure')) throw $error; }
    finally { if (ob_get_clean() !== '') throw new RuntimeException('Rejected display emitted PHP output'); }
}
if ($json) echo ']'; else echo "Output functions: {$samples} PHP/ViewTest scenarios and reactive diagnostics passed.\n";
