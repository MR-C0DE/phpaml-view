<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures/styles/conditions/page.php';
use AML\View\{Style, Renderer};
use App\Views\Pages\StyleConditions\StyleConditionsPage;
use function AML\View\Text;

$html = (new Renderer())->render(new StyleConditionsPage());
if (in_array('--json', $argv, true)) { echo json_encode(['html' => $html], JSON_THROW_ON_ERROR); exit; }
$checks = 0;
function conditionCheck(bool $ok, string $message): void { global $checks; if (!$ok) throw new RuntimeException($message); $checks++; }
function conditionReject(callable $call): void {
    try { $call(); } catch (InvalidArgumentException $error) { conditionCheck(true, 'Rejected invalid CSS'); return; }
    throw new RuntimeException('Invalid style condition was accepted.');
}
foreach (['@supports (display: grid)', '@container card (min-width: 500px)', '::before', '::after', ':nth-child(2n)', ':checked', ':valid', ':invalid', ':focus-within', '::placeholder', '600px < width < 2000px'] as $css) conditionCheck(str_contains($html, $css), 'Missing CSS: ' . $css);
conditionCheck(!str_contains($html, 'animation:pulse ') && str_contains($html, '-kf-pulse 1s'), 'Animation names must be scoped without rewriting duration units.');
$base = Style::make()->keyframes('pulse', ['to' => ['opacity' => 0.5]])->property('animation-name', 'pulse');
$copy = $base->keyframes('pulse', ['to' => ['opacity' => 0.8]]);
conditionCheck($base->compiled() !== $copy->compiled(), 'Keyframes must be immutable and content-scoped.');
$composed = $base->merge($copy)->compiled();
conditionCheck(str_contains(implode('', $composed['sheets']), 'opacity:0.8;') && !str_contains(implode('', $composed['sheets']), 'opacity:0.5;'), 'Composed definitions use the last keyframes with the same name.');
$media = $base->media('(max-width: 600px)', $copy)->compiled();
$css = implode('', $media['sheets']);
conditionCheck(strpos($css, 'opacity:0.5;') < strpos($css, '@media'), 'Conditional keyframes must override their base definition in source order.');
foreach (['', '(display:grid', '(display:grid))', '(x:1);body', '(x:1){', '(x:1)/*', '(x:"unclosed)'] as $query) {
    conditionReject(fn () => Style::make()->supports($query, []));
    conditionReject(fn () => Style::make()->container($query, []));
}
foreach (['2n) body', '2n of .global', 'odd,body', ''] as $step) conditionReject(fn () => Style::make()->nthChild($step, []));
foreach (['linear', 'none', '1bad', 'bad name', 'pulse}'] as $name) conditionReject(fn () => Style::make()->keyframes($name, ['to' => ['opacity' => 1]]));
conditionReject(fn () => Style::make()->keyframes('pulse', []));
conditionReject(fn () => Style::make()->keyframes('pulse', ['101%' => ['opacity' => 1]]));
conditionReject(fn () => Style::make()->keyframes('pulse', ['to' => Style::make()->hover(['color' => 'red'])]));
conditionReject(fn () => $base->property('animation-name', 'var(--name)')->compiled());
$raw = Style::module('raw-fixture', '.raw::before{content:"</StYlE><script>";}@media(width < 600px){.raw{color:red}}', 'raw');
$rendered = (new Renderer())->render(Text('raw')->style($raw));
conditionCheck(substr_count(strtolower($rendered), '</style>') === 1 && str_contains($rendered, 'width < 600px'), 'Raw-text escaping must preserve range query operators.');
conditionCheck((new Renderer())->render(new StyleConditionsPage()) === $html, 'Repeated renders must remain deterministic.');
echo "$checks style condition and animation checks passed.\n";
