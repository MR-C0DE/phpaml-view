<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use AML\View\{Renderer, Testing\TestView};

$directory = getenv('PHPAML_NESTED_AUDIT_DIR') ?: sys_get_temp_dir() . '/phpaml-nested-control-' . bin2hex(random_bytes(6));
$cases = []; $diagnostics = []; $compilationLimits = []; $exitCode = 0;
$selection = array_values(array_filter($argv, static fn ($argument) => str_starts_with($argument, '--case=')));
$benchmark = in_array('--benchmark', $argv, true);
if ($benchmark && $selection === []) throw new InvalidArgumentException('--benchmark requires --case=NAME.');
if (!mkdir($directory, 0700)) throw new RuntimeException('Unable to create an isolated nested-flow fixture directory.');
try {
    foreach (require __DIR__ . '/fixtures/NestedControlFlowPrograms.php' as $index => $program) {
        foreach (['event', 'function'] as $mode) {
            $name = $program['name'] . '-' . $mode;
            if ($selection !== [] && substr($selection[0], 7) !== $name) continue;
            if (in_array('--progress', $argv, true)) fwrite(STDERR, $name . "\n");
            $class = 'NestedControl' . $index . ucfirst($mode);
            $body = $mode === 'function' ? '$run = function (): void {' . $program['body'] . '}; $run();' : $program['body'];
            $source = strtr(<<<'PHP'
<?php
declare(strict_types=1);
namespace AML\View\Tests\NestedControl;
use AML\View\{Page, State, View};
use function AML\View\{Button, VStack};
final class CLASS_NAME extends Page
{
    #[State] public int $input = INITIAL_INPUT;
    #[State] public int $counter = 0;
    #[State] public array $result = [];
    public function body(): View
    {
        return VStack(
            Button('Run')->onClick($this->run(...)),
            Button('Recover')->onClick(function (): void { $this->counter = 42; }),
        );
    }
    public function run(): void
    {
PROGRAM_BODY
    }
}
PHP, ['CLASS_NAME' => $class, 'INITIAL_INPUT' => (string) $program['input'], 'PROGRAM_BODY' => $body]);
            $file = $directory . '/' . $class . '.php'; file_put_contents($file, $source); require $file;
            $class = 'AML\\View\\Tests\\NestedControl\\' . $class;
            $page = new $class();
            if ($benchmark) {
                // Measure compilation + final serialization, excluding rendering and execution.
                AML\View\StateInterpolation::enter(array_combine(['input', 'counter', 'result'], array_map(static fn ($name) => AML\Engine\StateRef::to($name), ['input', 'counter', 'result'])));
                try {
                    $timings = [];
                    for ($run = 0; $run < 4; $run++) {
                        $start = hrtime(true);
                        $json = AML\View\FrontendClosureCompiler::compile($page->run(...))->json();
                        if ($run > 0) $timings[] = (hrtime(true) - $start) / 1e6;
                    }
                    $sorted = $timings; sort($sorted);
                    $cases[] = ['name' => $name, 'milliseconds' => $timings, 'medianMs' => $sorted[1], 'bytes' => strlen($json), 'peakMemoryBytes' => memory_get_peak_usage(true)];
                } finally { AML\View\StateInterpolation::leave(); }
                continue;
            }
            // Native PHP runs even the finite loop-limit probes, independent of Engine.
            $page->run();
            $expected = ['input' => $page->input, 'counter' => $page->counter, 'result' => $page->result];
            if (in_array('--progress', $argv, true)) fwrite(STDERR, $name . ": compile\n");
            try { $html = (new Renderer())->render(new $class()); }
            catch (Throwable $error) {
                $issue = ['name' => $name, 'phase' => 'compile', 'error' => $error::class, 'message' => $error->getMessage(), 'native' => $expected];
                if ($program['name'] === 'if-depth-256' && $error instanceof JsonException && $error->getCode() === JSON_ERROR_DEPTH
                    && str_contains($error->getMessage(), '512 nested JSON containers')
                    && str_contains($error->getMessage(), '[PHP ' . basename($file) . ':20]')) $compilationLimits[] = $issue;
                else $diagnostics[] = $issue;
                continue;
            }
            if ($program['name'] === 'if-depth-256') {
                $diagnostics[] = ['name' => $name, 'message' => 'The serialization depth protection was bypassed.'];
                continue;
            }
            $document = new DOMDocument(); @$document->loadHTML($html);
            $manifest = $document->getElementsByTagName('template')->item(0);
            $decode = static fn ($element, string $attribute): array => json_decode($element->getAttribute($attribute), true, 512, JSON_THROW_ON_ERROR);
            $initial = $decode($manifest, 'data-aml-state'); $config = $decode($manifest, 'data-aml-state-config');
            $action = $decode($document->getElementsByTagName('button')->item(0), 'data-aml-client-click');
            $recovery = $decode($document->getElementsByTagName('button')->item(1), 'data-aml-client-click');
            if (in_array('--progress', $argv, true)) fwrite(STDERR, $name . ": ViewTest\n");
            $test = new TestView($html); $viewError = null;
            try { $test->click('Run'); }
            catch (Throwable $error) { $viewError = ['error' => $error::class, 'message' => $error->getMessage()]; }
            $limit = $program['loopLimit'];
            $limitKind = $program['name'] === 'combined-loop-budget' ? 'execution' : 'loop';
            $limitMessage = $limitKind === 'execution' ? '10000 iterations shared by all loops' : '1000 iterations per loop';
            $viewPass = $limit ? $viewError !== null && str_contains($viewError['message'], $limitMessage)
                && preg_match('/\[PHP .+\.php:\d+\]/', $viewError['message']) === 1 : $viewError === null;
            if ($viewPass) {
                try {
                    foreach (($limit ? $initial : $expected) as $key => $value) $test->assertState($key, $value);
                    $test->click('Recover')->assertState('counter', 42);
                }
                catch (Throwable $error) { $viewPass = false; $viewError = ['error' => $error::class, 'message' => $error->getMessage()]; }
            }
            $cases[] = compact('name', 'initial', 'config', 'action', 'recovery', 'expected', 'limit', 'limitKind', 'limitMessage', 'viewPass', 'viewError');
        }
    }
    $report = ['cases' => $cases, 'compilationLimits' => $compilationLimits, 'diagnostics' => $diagnostics];
    if (!$benchmark && ($diagnostics !== [] || $cases === [] && $compilationLimits === [] || count(array_filter($cases, static fn ($case) => $case['viewPass'])) !== count($cases))) $exitCode = 1;
    if ($benchmark || in_array('--json', $argv, true)) echo json_encode($report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    else {
        echo count($cases) . ' compiled scenarios; ' . count(array_filter($cases, static fn ($case) => $case['viewPass'])) . ' ViewTest checks passed; ' . count($compilationLimits) . " expected compilation limits; " . count($diagnostics) . " unexpected compilation errors.\n";
        foreach ($diagnostics as $issue) echo $issue['name'] . ': ' . $issue['message'] . "\n";
        foreach ($cases as $case) if (!$case['viewPass']) echo $case['name'] . ': ' . json_encode($case['viewError']) . "\n";
    }
} finally {
    foreach (glob($directory . '/*.php') as $file) unlink($file);
    rmdir($directory);
}
exit($exitCode);
