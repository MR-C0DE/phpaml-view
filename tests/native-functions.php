<?php

declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures/NativeLibrary.php';

use AML\View\Tests\Native\NativeLibrary;
use AML\View\{Renderer, Testing\TestView};

if (in_array('--example', $argv, true)) {
    require __DIR__ . '/../examples/fonctions-natives/page.php';
    echo (new Renderer())->render(new \Exemples\Pages\FonctionsNativesPage());
    exit;
}

function describeNative(mixed $value): array
{
    if (is_array($value)) return ['type' => 'array', 'entries' => array_map(static fn ($key) => ['key' => $key, 'item' => describeNative($value[$key])], array_keys($value))];
    return ['type' => gettype($value), 'value' => $value];
}
$cases = [];
foreach ((new ReflectionClass(NativeLibrary::class))->getMethods() as $method) {
    if (!str_starts_with($method->name, 'case_')) continue;
    if (PHP_VERSION_ID < 80400 && in_array($method->name, ['case_array_all', 'case_array_any', 'case_array_find', 'case_array_find_key', 'case_fpow'], true)) continue;
    if (PHP_VERSION_ID < 80300 && in_array($method->name, ['case_str_increment', 'case_str_decrement'], true)) continue;
    if (isset($argv[2]) && $argv[1] === '--case' && $method->name !== 'case_' . $argv[2]) continue;
    $page = new NativeLibrary(); $page->handler = $method->name;
    try { $html = (new Renderer())->render($page); }
    catch (Throwable $error) { throw new RuntimeException("Compile {$method->name}: " . $error->getMessage(), previous: $error); }
    $attribute = static function (string $name) use ($html): array { preg_match('/' . $name . '="([^"]+)"/', $html, $match); return json_decode(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR); };
    $initial = $attribute('data-aml-state'); $config = $attribute('data-aml-state-config'); $action = $attribute('data-aml-client-click');
    $warnings = []; set_error_handler(static function (int $level, string $message) use (&$warnings): bool { $warnings[] = $message; return true; });
    $error = null; ob_start();
    try { $page->{$method->name}(); } catch (Throwable $failure) { $error = ['name' => $failure::class, 'message' => $failure->getMessage()]; }
    finally { $output = ob_get_clean(); restore_error_handler(); }
    $expected = ['result' => $page->result, 'counter' => $page->counter];
    $test = new TestView($html); $failure = null; set_error_handler(static fn () => true);
    try { $test->click('Run'); } catch (Throwable $caught) { $failure = $caught; } finally { restore_error_handler(); }
    if (($failure !== null) !== ($error !== null)) throw new RuntimeException("ViewTest {$method->name}: " . ($failure?->getMessage() ?? 'unexpected success'));
    foreach (($error ? $initial : $expected) as $key => $value) {
        try { $test->assertState($key, $value); } catch (Throwable $failure) { throw new RuntimeException("ViewTest {$method->name}.{$key}: " . $failure->getMessage(), previous: $failure); }
    }
    if (implode('', $test->consoleOutput()) !== $output) throw new RuntimeException("ViewTest {$method->name}: console output differs");
    $typed = describeNative($expected['result']); $name = $method->name;
    $cases[] = compact('name', 'initial', 'config', 'action', 'expected', 'typed', 'error', 'warnings', 'output');
}
$diagnostics = 0;
foreach (['unsupportedLocale' => 'locale', 'unsupportedEncoding' => 'UTF-8', 'unsupportedFileHash' => 'server file'] as $method => $message) {
    $page = new NativeLibrary(); $page->handler = $method;
    try { (new Renderer())->render($page); throw new RuntimeException("{$method} was accepted"); }
    catch (InvalidArgumentException $error) { if (!str_contains($error->getMessage(), $message) || !str_contains($error->getMessage(), 'NativeLibrary.php:')) throw $error; $diagnostics++; }
}
foreach ([
    new class extends \AML\View\Page {
        #[\AML\View\State] public array $values = [2, 1];
        public function body(): \AML\View\View { return \AML\View\Text(fn () => sort($this->values)); }
    },
    new class extends \AML\View\Page {
        #[\AML\View\State] public string $value = 'hello';
        public function body(): \AML\View\View { return \AML\View\Text(fn () => printf('%s', $this->value)); }
    },
    new class extends \AML\View\Page {
        #[\AML\View\State] public string $value = 'hello';
        public function body(): \AML\View\View { return \AML\View\Text(function (): string { $output = printf(...); $output('%s', $this->value); return ''; }); }
    },
] as $page) {
    ob_start();
    try { (new Renderer())->render($page); throw new RuntimeException('Impure reactive native function accepted'); }
    catch (InvalidArgumentException $error) { if (!str_contains($error->getMessage(), 'reactive text') && !str_contains($error->getMessage(), 'Reactive text')) throw $error; $diagnostics++; }
    finally { if (ob_get_clean() !== '') throw new RuntimeException('Rejected display callable produced output'); }
}
if (in_array('--json', $argv, true)) echo json_encode($cases, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE);
else echo 'Native library: ' . count($cases) . " PHP/ViewTest scenarios and {$diagnostics} compiler diagnostics passed.\n";
