<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures/LanguageLevelOne.php';

use AML\View\Tests\Language\LanguageLevelOne;
use AML\View\Renderer;
use AML\View\Testing\ViewTest;

if (in_array('--engine-root', $argv, true)) {
    echo dirname((new ReflectionClass(\AML\Engine\EngineRuntime::class))->getFileName(), 2);
    exit;
}

if (in_array('--eval-asset', $argv, true)) {
    echo \AML\Engine\EngineRuntime::evalAssetPath();
    exit;
}

$cases = [];
foreach ([true, false] as $enabled) {
    $page = new LanguageLevelOne();
    $page->enabled = $enabled;
    $html = (new Renderer())->render($page);
    preg_match('/data-aml-client-click="([^"]+)"/', $html, $match);
    $action = json_decode(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR);
    $initial = ['count' => 1, 'whole' => 2.0, 'enabled' => $enabled, 'text' => '0', 'result' => ['snapshot' => 0]];
    ob_start();
    try { $page->run(); $dump = ob_get_contents(); }
    finally { ob_end_clean(); }
    $expected = [...$initial, 'count' => $page->count, 'result' => $page->result];
    $testPage = new LanguageLevelOne();
    $testPage->enabled = $enabled;
    $test = ViewTest::render($testPage);
    $test->click('Level one')->assertState('result', $page->result);
    if ($test->consoleOutput() !== [$dump]) throw new RuntimeException('var_dump output differs from PHP.');
    $cases[] = ['initial' => $initial, 'types' => ['count' => 'int', 'whole' => 'float', 'enabled' => 'bool', 'text' => 'string', 'result' => 'array'], 'action' => $action, 'expected' => $expected, 'dump' => $dump];
}
// Invalid names are rejected by PHP's parser; unknown names and unsafe access
// must also fail closed when the frontend source is compiled.
$invalid = new class extends \AML\View\Page {
    #[\AML\View\State] public string $text = '';
    public function body(): \AML\View\View {
        return \AML\View\Button('Invalid')->onClick(function (): void {
            $name = 'case sensitive';
            $this->text = $Name;
        });
    }
};
ViewTest::assertThrows(static fn () => (new Renderer())->render($invalid), OutOfBoundsException::class, 'Undefined frontend local variable');
$unsafe = new class extends \AML\View\Page {
    #[\AML\View\State] public string $text = '';
    public function body(): \AML\View\View {
        return \AML\View\Button('Invalid')->onClick(function (): void {
            $_ENV = 'forbidden';
            $this->text = $_ENV;
        });
    }
};
ViewTest::assertThrows(static fn () => (new Renderer())->render($unsafe), InvalidArgumentException::class, 'Unsupported frontend local variable');
$oversized = new class extends \AML\View\Page {
    #[\AML\View\State] public string $text = '';
    public function body(): \AML\View\View {
        return \AML\View\Button('Invalid')->onClick(fn () => $this->text = (string) 9007199254740992);
    }
};
ViewTest::assertThrows(static fn () => (new Renderer())->render($oversized), InvalidArgumentException::class, 'safe integer');
$captured = new class extends \AML\View\Page {
    #[\AML\View\State] public string $text = '';
    public function body(): \AML\View\View {
        $name = 'captured';
        return \AML\View\Button('Capture')->onClick(fn () => $this->text = "Hello $name");
    }
};
ViewTest::render($captured)->click('Capture')->assertState('text', 'Hello captured');

if (in_array('--json', $argv, true)) echo json_encode($cases, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
else echo "Level 1: compiled variables, scalar types, constants, strings and dumps match native PHP.\n";
