<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures/styles/home/load.php';
require __DIR__ . '/fixtures/styles/other/load.php';

use AML\View\{Style, StyleCollection, StyleModule, StyleModuleCss, Renderer, RenderContext, Page, State, View};
use function AML\View\{Text, Button, VStack, When, ContextProvider};

final class StylesPage extends Page
{
    #[State] public int $count = 0;
    #[State] public bool $visible = false;
    public function body(): View
    {
        $primary = Style::make()->background('#7047eb')->color('#ffffff')->padding(12)->borderRadius(10)
            ->hover(Style::make()->background('#5632bd'))->focus(Style::make()->property('outline', '3px solid #0064c8'));
        $home = homeStyles(); $other = otherStyles();
        $named = Style::create(['title' => ['fontSize' => 28, 'fontWeight' => 700, 'lineHeight' => 1.5], 'disabled' => ['opacity' => 0.5]]);
        return VStack(
            Text('Bonjour André')->fontSize(24)->fontWeight(700)->textColor('#7047eb')->padding(16)->id('direct'),
            Button('Enregistrer')->style($primary)->id('save')->onClick(function (): void { $this->count++; }),
            Button('Continuer')->style($primary)->id('continue'),
            Text('Inline')->style(Style::create()->fontSize(24)->fontWeight(700))->id('inline'),
            Text('Named')->style([$named['title'], ['color' => '#dc2626'], false, null])->id('named'),
            Text('First')->style(Style::make()->color('red'))->textColor('blue')->id('first'),
            Text('Second')->textColor('blue')->style(Style::make()->color('red'))->id('second'),
            Text('Original')->style($primary)->id('original'),
            Text('Variant')->style($primary->background('black'))->id('variant'),
            Text('Plain title')->class('title')->id('plain'),
            VStack(Text('Home')->style($home['title'])->id('home-title'), Button('Module')->style($home['button'])->id('module-button'))
                ->style($home['container'])->id('home-container'),
            Text('Other')->style($other['title'])->id('other-title'),
            Text($this->count)->id('count'),
            Button('Toggle')->onClick(function (): void { $this->visible = !$this->visible; })->id('toggle'),
            When($this->state('visible'), ContextProvider('demo', 1, Text('Conditional')->style(Style::make()->color('green'))->id('conditional'))),
        );
    }
}

$renderer = new Renderer();
$html = $renderer->render(new StylesPage());
if (in_array('--json', $argv, true)) { echo json_encode(['html' => $html], JSON_THROW_ON_ERROR); exit; }

$passed = 0;
function styleCheck(bool $condition, string $message): void { global $passed; if (!$condition) throw new RuntimeException($message); $passed++; }
function styleReject(callable $call, string $message): void {
    try { $call(); } catch (InvalidArgumentException|OutOfBoundsException|LogicException $error) { styleCheck(true, $message); return; }
    throw new RuntimeException($message);
}
styleCheck(Style::make()->color('red')->compiled() === Style::create()->color('red')->compiled(), 'make/create must be equivalent.');
styleCheck(str_contains($html, 'style="font-size:24px;font-weight:700;color:#7047eb;padding:16px"'), 'Legacy modifiers must preserve their output.');
$base = Style::make()->color('red'); $variant = $base->color('blue');
styleCheck($base->compiled() !== $variant->compiled() && str_contains(implode('', $base->compiled()['sheets']), 'color:red;'), 'Styles must be immutable.');
styleCheck(substr_count($renderer->render(VStack(Text('a')->style($base), Text('b')->style($base))), 'data-aml-style=') === 1, 'Reusable CSS must be emitted once per render.');
styleCheck(!str_contains($renderer->render(Text('empty')), 'data-aml-style'), 'Render contexts must not leak sheets.');
styleCheck($renderer->render(new StylesPage()) === $html, 'Rendering must be deterministic.');
styleCheck(str_contains($html, 'line-height:1.5;') && str_contains($html, 'font-size:28px;') && !str_contains($html, '700px'), 'Numeric units must follow property semantics.');
$home = homeStyles(); $other = otherStyles();
styleCheck($home['title']->compiled()['classes'] !== $other['title']->compiled()['classes'], 'Same basename in different folders must be isolated.');
styleCheck($home === homeStyles(), 'Repeated module reads should reuse a content-validated cache.');
styleCheck($home->title === $home['title'] && count($home) === 3, 'Named style lookup must be consistent.');
styleCheck(str_contains($html, '-kf-reveal') && !str_contains($html, 'animation:reveal '), 'Local animation names must be rewritten.');
styleReject(fn () => $home['missing'], 'Missing class must be reported.');
styleReject(fn () => $home->offsetSet('title', $base), 'Collections must be immutable.');
styleReject(fn () => Style::create('missing.style'), 'Missing files must be reported.');
styleReject(fn () => Style::create('/tmp/private.style.css'), 'Absolute module paths must be rejected.');
styleReject(fn () => Style::create('https://example.test/home.style.css'), 'Remote module paths must be rejected.');
foreach (['.a, body { color:red; }', ':not(.a) { color:red; }', ':is(.a, body) { color:red; }', '@import "a.css";', '@font-face { font-family:test; }', '.a { .b {color:red} }', '.a {color:red', '.a {color:var(--x;}', '.a {background:url(relative.png)}', '@keyframes linear {to{opacity:1}}'] as $css) {
    styleReject(fn () => (new StyleModuleCss($css, 'fixture'))->compile(), 'Unsafe or unsupported CSS must not silently become global: ' . $css);
}
[$css] = (new StyleModuleCss('.a[data-label=".b"]::after {content:"url(example) ; } .b </style>";} .a:not(.b) {color:red}', 'fixture'))->compile();
styleCheck(str_contains($css, '[data-label=".b"]') && str_contains($css, ':not(.fixture-b)'), 'Strings and pseudo-class selectors must be handled separately.');
[$commented] = (new StyleModuleCss('/* intro */ .a/* note .ignored */:hover {color/* note */:red;} /* end */', 'fixture'))->compile();
styleCheck(str_contains($commented, '.fixture-a/* note .ignored */:hover{color:red;}'), 'Comments must not turn a compound selector into a descendant selector.');
[$animated] = (new StyleModuleCss('.a {animation: s 1s linear 200ms;} @keyframes s {to {opacity:1}} @keyframes ms {to {opacity:1}}', 'fixture'))->compile();
styleCheck(str_contains($animated, 'animation:fixture-kf-s 1s linear 200ms;'), 'Keyframe scoping must not rewrite duration units.');
$unsafeContent = Style::module('fixture', $css, 'fixture-a');
$rendered = $renderer->render(Text('safe')->style($unsafeContent));
styleCheck(substr_count($rendered, '</style>') === 1 && str_contains($rendered, '\\3c /style>'), 'A CSS string must not terminate the HTML style element.');
foreach ([fn () => Style::make()->fontSize(INF), fn () => Style::make()->color('red;}body{color:red'), fn () => Style::make()->media('x}body{', []), fn () => Text('x')->style('color'), fn () => Style::create(['bad' => 'red']), fn () => Style::from(['color' => new stdClass()])] as $call) styleReject($call, 'Invalid style configuration must fail.');
$context = new RenderContext();
$fragment = Text('manual')->style($base)->render($context);
styleCheck(str_contains($fragment, 'aml-s-') && str_contains($context->styles(), 'color:red'), 'Manual render contexts expose their collected CSS.');
$mixed = $renderer->render(Text('mixed')->style('-webkit-line-clamp', 2)->style($base)->style('color', ''));
styleCheck(str_contains($mixed, '-webkit-line-clamp:2;') && str_contains($mixed, 'color:;'), 'Existing vendor properties and empty values must remain usable with structured styles.');
$temporary = sys_get_temp_dir() . '/phpaml-style-' . bin2hex(random_bytes(6));
mkdir($temporary . '/src/views/pages/home', 0777, true);
$caller = $temporary . '/src/views/pages/home/page.php';
$module = dirname($caller) . '/home.style.css';
file_put_contents($caller, '<?php');
try {
    file_put_contents($module, '.title { color:red; }');
    $first = StyleModule::load('home.style', $caller)['title']->compiled();
    file_put_contents($module, '.title { color:tan; }');
    $second = StyleModule::load('home.style', $caller)['title']->compiled();
    styleCheck($first !== $second, 'Module cache must see same-size edits without depending on mtime.');
    file_put_contents($temporary . '/outside.style.css', '.title {color:blue}');
    styleReject(fn () => StyleModule::load('../../../../outside.style', $caller), 'Paths must stay inside views.');
    if (function_exists('symlink') && @symlink($temporary . '/outside.style.css', dirname($caller) . '/escape.style.css')) {
        styleReject(fn () => StyleModule::load('escape.style', $caller), 'Symlinks must not escape views.');
    }
    $one = new StyleModuleCss('.title{color:red}', 'stable');
    $two = new StyleModuleCss('.title{color:red}', 'stable');
    styleCheck($one->compile() === $two->compile(), 'Module compilation must be deterministic.');
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temporary, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $entry) { if ($entry->isDir() && !$entry->isLink()) rmdir($entry->getPathname()); else unlink($entry->getPathname()); }
    rmdir($temporary);
}
echo "{$passed} style API and module checks passed.\n";
