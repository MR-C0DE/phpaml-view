<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
use AML\View\{Component, Page, State, Style, View, Renderer};
use function AML\View\{Component, Text, Button, VStack, Group, When};

function styleHelper(string $color, int $size): Style { return Style::make()->color($color)->fontSize($size + 2); }

final class StyleCounter extends Component
{
    #[State] public int $size = 20;
    public function body(): View
    {
        return VStack(Text('Counter')->fontSize($this->size)->class('counter-text'), Button('Grow')->class('counter-grow')->onClick(function (): void { $this->size += 2; }))->class('counter');
    }
}
final class StyleChild extends Component
{
    public function __construct(private string $color, private int $size) {}
    public function body(): View { return Text('Child')->style(Style::make()->color($this->color)->fontSize($this->size))->id('reactive-child'); }
}
final class ReactiveStylesPage extends Page
{
    #[State] public string $color = '#7047eb';
    #[State] public string $other = '#7047eb';
    #[State] public int $size = 24;
    #[State] public int $space = 12;
    #[State] public bool $active = false;
    #[State] public bool $visible = false;
    public function body(): View
    {
        $shared = Style::make()->background($this->color)->padding($this->space)->borderRadius($this->space / 2)
            ->hover(Style::make()->background($this->other))->media('(max-width: 600px)', ['padding' => $this->space / 2]);
        $named = Style::create(['title' => ['fontSize' => $this->size, 'color' => $this->color], 'muted' => Style::make()->opacity(0.5)]);
        $alias = $this->size + 3;
        $offset = 5;
        $snapshot = $this->size;
        $captured = ['fontSize' => fn () => $snapshot + 7];
        $map = ['padding' => $this->space + 1, 'color' => $this->color];
        $module = Style::create('fixtures/styles/reactive.style');
        return VStack(
            Text('Direct')->fontSize($this->size)->fontWeight($this->active ? 700 : 400)->textColor($this->color)->padding($this->space)->id('reactive-direct'),
            Button('Shared A')->style($shared)->id('shared-a'), Button('Shared B')->style($shared)->id('shared-b'),
            Text('Inline')->style(Style::create()->fontSize($this->size + 1)->color($this->color))->id('reactive-inline'),
            Text('Named')->style($named['title'])->id('reactive-named'),
            Text('Mixed')->style([$named['title'], ['padding' => $this->space], false, null])->id('reactive-mixed'),
            Text('Map')->style($map)->id('reactive-map'),
            Text('Template')->style(Style::make()->property('border', '1px solid {color}')->property('width', '{size}px'))->id('reactive-template'),
            Text('Concat')->style(Style::make()->property('width', ($this->size * 2) . 'px'))->id('reactive-concat'),
            Text('PHP')->style(Style::make()->width("{$this->size}px"))->id('reactive-php'),
            Text('Alias')->fontSize($alias)->id('reactive-alias'),
            Text('Helper')->style(styleHelper($this->color, $this->size))->id('reactive-helper'),
            Text('Closure')->style(Style::make()->fontSize(fn () => $this->size + 4))->id('reactive-closure'),
            Text('Explicit')->style(Style::make()->color($this->state('color')))->id('reactive-explicit'),
            Text('Legacy')->style('width', $this->size . 'px')->id('reactive-legacy'),
            Text('Module')->style([$module['title'], ['--accent' => $this->color, '--size' => $this->size . 'px']])->id('reactive-module'),
            Text('Other')->color($this->other)->id('reactive-other'), Text('Static')->color('#7047eb')->id('reactive-static'),
            Button('Change')->onClick(function (): void { $this->color = '#dc2626'; $this->size = 32; $this->space = 20; $this->active = true; })->id('change-style'),
            Button('Invalid')->onClick(function (): void { $this->color = 'red;}body{color:black'; })->id('invalid-style'),
            Button('Recover')->onClick(function (): void { $this->color = '#008000'; })->id('recover-style'),
            Button('Reveal')->onClick(function (): void { $this->visible = !$this->visible; })->id('reveal-style'),
            When($this->state('visible'), Text('Conditional')->fontSize($this->size)->color($this->color)->id('reactive-conditional')),
            Text('Named arguments')->style(Style::create(definitions: ['caption' => ['fontSize' => $this->size]])['caption'])->id('reactive-arguments'),
            Text('Closure map')->style(['fontSize' => fn () => $this->size + $offset])->id('reactive-closure-map'),
            Text('Snapshot')->style($captured)->id('reactive-snapshot'),
            Text('Static closure')->fontSize(fn () => 25)->id('reactive-static-closure'),
            Text('Ref map')->style(['color' => $this->state('color')])->id('reactive-ref-map'),
            Component(StyleChild::class, $this->color, $this->size),
            new StyleCounter(), new StyleCounter(),
        );
    }
}
$html = (new Renderer())->render(new ReactiveStylesPage());
if (in_array('--json', $argv, true)) { echo json_encode(['html' => $html], JSON_THROW_ON_ERROR); exit; }
$dom = new DOMDocument(); @$dom->loadHTML($html); $xpath = new DOMXPath($dom);
$nodes = $xpath->query('//*[@data-aml-style-bindings]');
if ($nodes->length < 20) throw new RuntimeException('Missing reactive style bindings: ' . $nodes->length);
foreach ($nodes as $node) foreach (json_decode($node->getAttribute('data-aml-style-bindings'), true, flags: JSON_THROW_ON_ERROR) as $variable => $binding) {
    if (!str_contains($node->getAttribute('style'), $variable . ':')) throw new RuntimeException('Missing initial CSS variable');
    if (!str_contains(json_encode($binding), '$state')) throw new RuntimeException('Missing State dependency');
}
if ($dom->getElementById('reactive-static')->hasAttribute('data-aml-style-bindings')) throw new RuntimeException('Static and equal State values must remain distinct.');
// Invalid initial values and impure closures must fail before mutating State.
$invalid = new class extends Page {
    #[State] public int $size = 24;
    public function body(): View { return Text('Invalid')->style(['fontSize' => fn () => ++$this->size]); }
};
try { (new Renderer())->render($invalid); throw new RuntimeException('Impure styles must fail.'); }
catch (InvalidArgumentException $error) { if ($invalid->size !== 24) throw new RuntimeException('Style compilation mutated PHP State.'); }
$unsafe = new ReactiveStylesPage(); $unsafe->color = 'red;display:none';
try { (new Renderer())->render($unsafe); throw new RuntimeException('Unsafe initial styles must fail.'); }
catch (InvalidArgumentException $error) {}
if ((new Renderer())->render(new ReactiveStylesPage()) !== $html) throw new RuntimeException('Failed renders leaked State or style context.');
echo $nodes->length . " reactive style elements, read-only validation and context isolation checked.\n";
