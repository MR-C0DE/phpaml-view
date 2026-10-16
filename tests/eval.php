<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures/EvalLibrary.php';
use AML\View\Tests\EvalRuntime\EvalLibrary;
use AML\View\{Renderer, Testing\TestView};
if (in_array('--example', $argv, true)) { require __DIR__ . '/../examples/eval/page.php'; echo (new Renderer())->render(new \Exemples\Pages\EvalPage()); exit; }
$cases = [
    ['literal', 'literal', ''], ['literalError', 'literalError', ''], ['nested', 'nested', ''], ['literalFunctions', 'literalFunctions', ''],
    ['declarations', 'declarations', ''], ['referenceFrame', 'referenceFrame', ''], ['skipped', 'skipped', ''],
    ['empty', 'dynamic', ''], ['comments', 'dynamic', '// comment'], ['bare-return', 'dynamic', 'return;'],
    ['locals', 'dynamic', '$x += 2; $created = "André"; return $x * 3;'],
    ['state', 'dynamic', '$this->count += 5; return $this->count;'],
    ['cast', 'dynamic', 'return [gettype(1.0), (float) 1, (int) "12", (bool) "0", null, true, false];'],
    ['strings', 'dynamic', 'return ["\\xC3\\xA9", "\\u{1F600}", "a\\n$x", \'it\\\'s\\\\fine\', "\\0"];'],
    ['weak', 'dynamic', 'return strlen(123);'], ['strict', 'caught', 'declare(strict_types=1); strlen(123);'],
    ['operators', 'dynamic', '$x *= 2; $x++; --$x; $created = (2 ** 3 + 4 / 2) % 7; return [$x === 8, $x == "8", $x <=> 9, !false && true, $missing ?? 2, $x ? "yes" : "no"];'],
    ['arrays', 'dynamic', '$a = ["a" => 1, 4 => 2]; $a[] = 3; unset($a["a"]); [$x, $created] = [9, 8]; return [...$a, ...["s" => 4]];'],
    ['loops', 'dynamic', '$sum = 0; for ($i = 0; $i < 4; $i++) { if ($i === 2) { continue; } $sum += $i; } while ($x < 6) { $x++; } do { $created++; } while ($created < 2); foreach (["a" => 2, "b" => 3] as $k => $v) { $sum += $v; } return $sum;'],
    ['match', 'dynamic', 'switch ($x) { case 3: $x = 0; break; case 4: $x++; default: $x++; } return match ($x) { 1, 2 => "small", default => "large" };'],
    ['closures', 'dynamic', '$fn = function (int $n = 2) use (&$x): int { $x += $n; return $x; }; $arrow = fn($a) => $a + $x; return [$fn(n: 3), $arrow(2), $callback(7)];'],
    ['functions', 'dynamic', 'function aml_eval_dynamic_sum(int $n): int { return $n < 1 ? 0 : $n + aml_eval_dynamic_sum($n - 1); } return aml_eval_dynamic_sum(n: 4);'],
    ['references', 'dynamic', 'function aml_eval_dynamic_refs(int &$a, int ...$values): int { $a += array_sum($values); return $a; } return aml_eval_dynamic_refs($x, 2, 3);'],
    ['higher-order', 'dynamic', 'return array_map(fn($v) => strtoupper($v), ["a", "b"]);'],
    ['first-class', 'dynamic', '$upper = strtoupper(...); return $upper("hey");'],
    ['nested-dynamic', 'dynamic', '$inner = \'$x++; return $x;\'; return eval($inner);'],
    ['throw', 'dynamic', 'try { throw new InvalidArgumentException("bad", 7); } catch (InvalidArgumentException $e) { $created = $e->getCode(); return $e->getMessage(); } finally { $x++; }'],
    ['console', 'dynamic', 'echo "Bonjour $x", PHP_EOL; var_dump($x); print_r(["a" => 2]); return var_export(["x" => $x], true);'],
    ['parse', 'caught', 'return ( ;'], ['type', 'caught', 'strlen([]);'], ['division', 'caught', '$this->count = 4; return 1 / 0;'],
    ['precedence', 'dynamic', 'return ["total=" . 1 + 2 * 3, -2 ** 2, 2 ** 3 ** 2];'],
    ['conversions', 'dynamic', 'return [intval(value: "ff", base: 16), gettype(value: 2.0)];'],
    ['constants', 'dynamic', 'return [PHP_ROUND_HALF_EVEN, round(M_PI, 2), SORT_ASC];'],
    ['new-locals-captured', 'dynamic', 'eval(\'$new = 6;\'); $fn = fn() => $new + 1; return $fn();'],
    ['unknown-function', 'caught', 'aml_eval_not_registered();'],
    ['unknown-constant', 'caught', 'return AML_EVAL_UNDEFINED_CONSTANT;'],
    ['line', 'dynamic', "\nreturn __LINE__;"],
    ['union', 'dynamic', '$fn = fn(int|string|null $v = null): int|string|null => $v; return [$fn(), $fn("x"), $fn(3)];'],
    ['returned-closure', 'dynamic', '$fn = eval("return fn(\$n) => \$n + 1;"); return $fn(4);'],
    ['weak-state', 'dynamic', '$this->count = "7"; return $this->count;'],
    ['strict-state', 'caught', 'declare(strict_types=1); $this->count = "7";'],
    ['return-type', 'caught', '$fn = function (): int { return []; }; $fn();'],
    ['quiet', 'dynamic', 'return [isset($missing, $x), empty($missing), $missing ?? "fallback"];'],
    ['nested-arrows', 'dynamic', '$factory = fn($a) => fn($n) => $a + $n + $x; $fn = $factory(3); return $fn(2);'],
    ['strict-interpolation', 'caught', 'declare(strict_types=1); $fn = \'strlen\'; return "{$fn(123)}";'],
    ['rollback', 'dynamic', '$this->count = 9; echo "before"; throw new RuntimeException("stop");'],
];
$json = in_array('--json', $argv, true); $samples = [];
foreach ($cases as [$name, $handler, $code]) {
    if (($argv[1] ?? '') === '--case' && ($argv[2] ?? '') !== $name) continue;
    $page = new EvalLibrary(); $page->code = $code; $page->handler = $handler;
    $html = (new Renderer())->render($page);
    $attribute = static function (string $name) use ($html): array { preg_match('/' . $name . '="([^"]+)"/', $html, $match); return json_decode(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR); };
    $initial = $attribute('data-aml-state'); $config = $attribute('data-aml-state-config'); $action = $attribute('data-aml-client-click');
    $error = null; ob_start();
    try { $page->{$handler}(); } catch (Throwable $failure) { $error = ['name' => $failure::class, 'message' => $failure->getMessage()]; }
    finally { $output = ob_get_clean(); }
    $expected = ['code' => $code, 'result' => $page->result, 'count' => $page->count];
    $test = new TestView($html); $failure = null;
    try { $test->click('Run'); } catch (Throwable $caught) { $failure = $caught; }
    if (($failure !== null) !== ($error !== null) || $failure && $failure::class !== $error['name']) throw new RuntimeException("ViewTest {$name}: " . ($failure ? $failure::class . ': ' . $failure->getMessage() : 'unexpected success'), previous: $failure);
    foreach (($error ? $initial : $expected) as $key => $value) {try { $test->assertState($key, $value); } catch (Throwable $e) {throw new RuntimeException($name . ': ' . $e->getMessage() . ' expected=' . json_encode($value), previous: $e);}}
    if (implode('', $test->consoleOutput()) !== $output) throw new RuntimeException("ViewTest {$name}: console output differs");
    $samples[] = compact('name', 'initial', 'config', 'action', 'expected', 'error', 'output');
}
$readonly = new class extends \AML\View\Page {
    #[\AML\View\State] public string $code = 'return "hello";';
    public function body(): \AML\View\View { return \AML\View\Text(fn () => eval($this->code)); }
};
try { (new Renderer())->render($readonly); throw new RuntimeException('Reactive eval was accepted'); }
catch (InvalidArgumentException $error) { if (!str_contains($error->getMessage(), 'Run eval()')) throw $error; }
require __DIR__ . '/../examples/eval/page.php';
(new TestView((new Renderer())->render(new \Exemples\Pages\EvalPage())))->click('Exécuter')->assertState('compteur', 1)->assertState('message', 'Bonjour André : 1')->click('Ajouter avec du code fixe')->assertState('compteur', 11);
if ($json) echo json_encode($samples, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
else echo 'Eval: ' . count($samples) . " PHP/ViewTest scenarios passed.\n";
