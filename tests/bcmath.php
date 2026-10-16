<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures/BCMathService.php';
require __DIR__ . '/fixtures/BCMathLibrary.php';

use AML\View\Tests\Decimal\BCMathLibrary;
use AML\View\{Renderer, Testing\TestView};

if (!extension_loaded('bcmath')) { echo in_array('--json', $argv, true) ? '[]' : "BCMath skipped: ext-bcmath is not installed.\n"; exit; }
if (in_array('--example', $argv, true)) { require __DIR__ . '/../examples/calcul-decimal/page.php'; echo (new Renderer())->render(new \Exemples\Pages\CalculDecimalPage()); exit; }
$cases = [];
$add = static function (string $function, array $arguments = [], string $handler = 'run', int $scale = 0) use (&$cases): void { if (function_exists($function) || $handler !== 'run') $cases[] = [$function, $arguments, $handler, $scale]; };
$numbers = ['0', '-0.00', '0.1', '-0.2', '1.999', '-1.001', '1.005', '-1.005', '1.015', '999.995', '2.5', '-2.5', '00012.3400', '.000001', '-.000009', '9007199254740993.01999', '999999999999999999999999999999.99', '', '+', '-', '.', '1.', ' 1', '1 ', '1e2', 'NaN', '1,20'];
$numbers = [...$numbers, "1\n", "1\r", "\n1", "1\u{2028}", "1\0"];
foreach ($numbers as $left) foreach (['0', '0.2', '-1.3', '1.999', '9007199254740993.01'] as $right) foreach ([0, 2, 8] as $scale) foreach (['bcadd', 'bcsub', 'bcmul', 'bcdiv', 'bcmod', 'bcdivmod', 'bccomp'] as $function) $add($function, [$left, $right, $scale]);
foreach ($numbers as $number) {
    foreach ([0, 2, 20, 100] as $scale) $add('bcsqrt', [$number, $scale]);
    foreach (['bcfloor', 'bcceil'] as $function) $add($function, [$number]);
    if (function_exists('bcround')) foreach ([-3, -1, 0, 1, 2, 8] as $precision) {
        $add('bcround', [$number, $precision]);
        foreach (RoundingMode::cases() as $mode) $add('bcround', [$number, $precision, $mode->name], 'rounded');
    }
}
foreach (['0', '1.000', '-1', '1.2', '-2', '0.001', '123.45'] as $base) foreach (['-10', '-3', '-1', '0', '1', '2.00', '3', '10', '1.2', 'bad'] as $exponent) foreach ([0, 2, 10] as $scale) $add('bcpow', [$base, $exponent, $scale]);
foreach (['0', '-7', '2', '2.00', '2.1', '9007199254740993'] as $base) foreach (['-1', '0', '1', '2', '15', '100000000000000000000000000000000001', '2.5'] as $exponent) foreach (['0', '1', '-5', '13', '5.00', '1.5'] as $modulus) $add('bcpowmod', [$base, $exponent, $modulus, 3]);
foreach ([0, 2, 8] as $scale) {
    $add('bcadd', ['0.1', '0.2'], scale: $scale);
    $add('bcdiv', ['1', '3', null], scale: $scale);
    $add('bcscale', [], scale: $scale);
    $add('bcscale', [null], scale: $scale);
    $add('bcscale', [5], scale: $scale);
    $add('scales', [], 'scales', $scale);
}
$add('bcadd', ['num2' => '0.2', 'scale' => 2, 'num1' => '0.1']);
foreach ([['bcadd', []], ['bcadd', ['1']], ['bcadd', [1, '2']], ['bcadd', [[], '2']], ['bcadd', ['1', '2', -1]], ['bcscale', [-1]], ['bcdiv', ['1', '2', '2']], ['bcround', ['2.5', 0, 1]], ['bcround', ['2.5', 0, null]], ['bcpow', ['1', '9223372036854775808']]] as [$function, $arguments]) $add($function, $arguments);
foreach (['composition', 'scales', 'caught', 'uncaught', ...(function_exists('bcround') ? ['enumValues'] : [])] as $method) $add($method, [], $method);

$json = in_array('--json', $argv, true); $samples = 0; if ($json) echo '[';
foreach ($cases as $index => [$function, $arguments, $handler, $scale]) {
    if (isset($argv[2]) && $argv[1] === '--case' && $function !== $argv[2]) continue;
    $name = $function . ':' . $index; $previousScale = bcscale($scale);
    $page = new BCMathLibrary(); $page->functionName = $function; $page->arguments = $arguments; $page->handler = $handler;
    $html = (new Renderer())->render($page);
    $attribute = static function (string $name) use ($html): array { preg_match('/' . $name . '="([^"]+)"/', $html, $match); return json_decode(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR); };
    $initial = $attribute('data-aml-state'); $config = $attribute('data-aml-state-config'); $action = $attribute('data-aml-client-click');
    $error = null; ob_start();
    try { $page->{$handler}(); } catch (Throwable $failure) { $error = ['name' => $failure::class, 'message' => $failure->getMessage()]; }
    finally { $output = ob_get_clean(); bcscale($scale); }
    $expected = ['functionName' => $function, 'arguments' => $arguments, 'result' => $page->result];
    $test = new TestView($html); $failure = null;
    try { $test->click('Run'); } catch (Throwable $caught) { $failure = $caught; }
    if (($failure !== null) !== ($error !== null) || $failure && $failure::class !== $error['name']) throw new RuntimeException("ViewTest {$name}: " . ($failure ? $failure::class . ': ' . $failure->getMessage() : 'unexpected success'));
    foreach (($error ? $initial : $expected) as $key => $value) $test->assertState($key, $value);
    if (implode('', $test->consoleOutput()) !== $output) throw new RuntimeException("ViewTest {$name}: console output differs");
    if (bcscale() !== $scale) throw new RuntimeException("ViewTest {$name} leaked bcscale into PHP");
    if ($handler === 'scales') { $test->click('Run'); $test->assertState('result', $expected['result']); }
    if ($json) echo ($samples ? ',' : ''), json_encode(compact('name', 'initial', 'config', 'action', 'expected', 'error', 'output'), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    $samples++; bcscale($previousScale);
}
// Reject a rendering side effect before evaluating the display closure.
$beforeScale = bcscale();
foreach ([new class extends \AML\View\Page {
        #[\AML\View\State] public string $amount = '0.1';
        public function body(): \AML\View\View { return \AML\View\Text(function (): string { bcscale(4); return bcadd($this->amount, '0.2'); }); }
    }, new class extends \AML\View\Page {
        #[\AML\View\State] public string $amount = '0.1';
        public function body(): \AML\View\View { return \AML\View\Text(function (): string { $setter = 'bcscale'; $setter(4); return bcadd($this->amount, '0.2'); }); }
    }, new class extends \AML\View\Page {
        #[\AML\View\State] public string $amount = '0.1';
        public function body(): \AML\View\View { return \AML\View\Text(function (): string { array_map('bcscale', [4]); return bcadd($this->amount, '0.2'); }); }
    }] as $impure) try {
    (new Renderer())->render($impure);
    throw new RuntimeException('Reactive bcscale mutation was accepted');
} catch (InvalidArgumentException $error) { if (!str_contains($error->getMessage(), 'bcscale') && !str_contains($error->getMessage(), 'must be pure')) throw $error; }
if (bcscale() !== $beforeScale) throw new RuntimeException('Rejected display mutated PHP bcscale');
if ($json) echo ']'; else echo "BCMath: {$samples} PHP/ViewTest scenarios, scale isolation and reactive diagnostic passed.\n";
