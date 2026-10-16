<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use AML\View\{Component, FrontendImports, Page, Renderer, State, View, Testing\TestView};
use function AML\View\{Button, Text, VStack};

final class StabilizationChild extends Component
{
    #[State] public int $count = 0;
    public function __construct(private string $label) {}
    public function body(): View
    {
        return VStack(Text($this->label . ': ' . $this->count), Button($this->label)->onClick(function (): void { $this->count++; }));
    }
}

set_error_handler(static function (int $severity, string $message, string $file, int $line): never { throw new ErrorException($message, 0, $severity, $file, $line); });
try {
    // CLI/evaluated parents have no readable source, but their ordinary children
    // and the children's own State must still render and remain isolated.
    $parent = eval('return new class extends \\AML\\View\\Page { public function body(): \\AML\\View\\View { return \\AML\\View\\VStack(new \\StabilizationChild("First"), new \\StabilizationChild("Second")); } };');
    $html = (new Renderer())->render($parent);
    (new TestView($html))->click('First')->assertSee('First: 1')->assertSee('Second: 0');
    $returned = eval('return new class extends \\AML\\View\\Page { public function body(): \\AML\\View\\View { return new \\StabilizationChild("Direct"); } };');
    (new TestView((new Renderer())->render($returned)))->click('Direct')->assertSee('Direct: 1');

    // Import reuse must respect lexical namespaces and edits that keep both size
    // and modification time (common with generated sources).
    $file = tempnam(sys_get_temp_dir(), 'aml-imports-');
    try {
        $source = <<<'PHP'
<?php
namespace AMLStabilityOne {
use DateTime as Clock;
use function strlen as size;
use const PHP_VERSION as VERSION;
function sample() {}
}
namespace AMLStabilityTwo {
use stdClass as Clock;
function sample() {}
}
PHP;
        file_put_contents($file, $source); require $file;
        $first = new ReflectionFunction('AMLStabilityOne\\sample');
        $second = new ReflectionFunction('AMLStabilityTwo\\sample');
        $expected = ['class'=>['clock'=>'DateTime'], 'const'=>['VERSION'=>'PHP_VERSION'], 'function'=>['size'=>'strlen']];
        foreach ([$first, $second, $first] as $reflection) {
            $imports = FrontendImports::forFunction($reflection);
            $want = $reflection === $first ? $expected : ['class'=>['clock'=>'stdClass'], 'const'=>[], 'function'=>[]];
            if ($imports !== $want) throw new RuntimeException('Lexical imports changed between namespaces.');
        }
        $timestamp = filemtime($file);
        file_put_contents($file, str_replace('DateTime as Clock', 'stdClass as Clock', $source)); touch($file, $timestamp);
        $expected['class']['clock'] = 'stdClass';
        if (FrontendImports::forFunction($first) !== $expected) throw new RuntimeException('Stale source imports after an equal-size edit.');
    } finally { unlink($file); }
} finally { restore_error_handler(); }
echo "Stabilization: source-less parents, isolated child State and source import invalidation passed.\n";
