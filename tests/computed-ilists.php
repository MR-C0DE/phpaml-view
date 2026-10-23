<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
\AML\View\FunctionalViews::load(__DIR__ . '/fixtures/ComputedILists.php');
use AML\View\{Renderer, Testing\ViewTest};
use AML\View\Tests\ComputedILists\ComputedListsPage;
$html = (new Renderer())->render(new ComputedListsPage());
if (in_array('--json', $argv, true)) { echo json_encode(['html' => $html], JSON_THROW_ON_ERROR); exit; }
$dom = new DOMDocument(); @$dom->loadHTML('<meta charset="utf-8">' . $html);
$xpath = new DOMXPath($dom);
$check = static function (bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); };
foreach (['class-inline' => ['Alpha'], 'class-variable' => ['Alpha'], 'class-helper' => ['Hi ALPHA'], 'class-loop' => ['Alpha'], 'class-template' => ['Alpha', 'Beta'], 'filtered-one' => ['Alpha', 'Beta'], 'mapped-one' => ['ALPHA', 'BETA'], 'slice-one' => ['Beta']] as $id => $labels) {
    $check(array_map(fn ($node) => $node->textContent, iterator_to_array($xpath->query('//*[@id="' . $id . '"]/li[@data-aml-list-key]'))) === $labels, 'Initial calculated rows mismatch: ' . $id);
    $check($dom->getElementById($id)->hasAttribute('data-aml-list-value'), 'Missing calculation: ' . $id);
}
$test = ViewTest::render(new ComputedListsPage());
$test->click('Append one')->click('Nested one')->assertSee(':3');
try { $test->click('Invalid one'); throw new RuntimeException('Typed StateRef assignment accepted'); } catch (TypeError) {}
$test->assertSee(':3');
// Display closures must remain pure even when the StateRef is captured indirectly.
final class InvalidComputedList extends \AML\View\Component
{
    public function __construct(private int $mode) {}
    public function body(): \AML\View\View
    {
        $items = \AML\View\State(['Initial']);
        if ($this->mode === 0) return \AML\View\IList(function () use ($items): array { $items[] = 'Forbidden'; return $items; });
        if ($this->mode === 1) return \AML\View\IList(fn (): string => 'bad');
        if ($this->mode === 2) return \AML\View\IList(fn (): array => $items)->sortable();
        if ($this->mode === 4) return \AML\View\IList(function (): array { while (true) {} return []; });
        $mutate = function () use ($items): array { $items = []; return $items; };
        return \AML\View\IList(fn (): array => $mutate());
    }
}
foreach ([0, 1, 3] as $mode) ViewTest::assertThrows(fn () => (new Renderer())->render(new InvalidComputedList($mode)), InvalidArgumentException::class);
ViewTest::assertThrows(fn () => (new Renderer())->render(new InvalidComputedList(2)), LogicException::class);
ViewTest::assertThrows(fn () => (new Renderer())->render(new InvalidComputedList(4)), RangeException::class);
echo "Calculated IList initial values, captured StateRef writes, nested closures and type rollback passed.\n";
