<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
\AML\View\FunctionalViews::load(__DIR__ . '/fixtures/ILists.php');

use AML\Engine\StateRef;
use AML\View\{CollectionItem, Renderer, View, Testing\ViewTest};
use AML\View\Tests\ILists\ListsPage;
use function AML\View\{IList, ListItem, Text};

$renderer = new Renderer();
$html = $renderer->render(new ListsPage());
if (in_array('--json', $argv, true)) { echo json_encode(['html' => $html], JSON_THROW_ON_ERROR); exit; }
$check = static function (bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); };
$dom = new DOMDocument(); @$dom->loadHTML('<meta charset="utf-8">' . $html);
$xpath = new DOMXPath($dom);
$check($xpath->query('//ul[@id="static-default"]/li')->length === 3, 'Static items were not wrapped once.');
$check($xpath->query('//li/li')->length === 0, 'Lists must not double-wrap ListItem.');
$check($xpath->query('//ol[@id="numbered" and @start="5" and @reversed]')->length === 1, 'Ordered options missing.');
$check($dom->getElementById('natural-list')->getAttribute('data-aml-list') === 'names', 'Natural State source lost its binding.');
$check(str_contains($html, 'list-style-type:upper-roman') && str_contains($html, 'list-style-position:inside'), 'Marker styles missing.');
$check(str_contains($html, '&lt;safe&gt;') && str_contains($html, '{literal}'), 'Data text was not literal and escaped.');
$check($xpath->query('//*[@id="custom-records"]/template')->length === 1, 'Custom reactive template missing.');
$check($xpath->query('//*[@id="custom-records"]/li[@data-aml-list-empty and @hidden]')->length === 1, 'Nonempty list must hide its placeholder.');
$check($xpath->query('//*[@id="static-empty"]/li[@data-aml-list-empty and not(@hidden)]')->length === 1, 'Empty list lost its placeholder.');
$list = IList('A')->ordered()->start(0)->reversed();
$check(str_contains($renderer->render($list), 'start="0"'), 'Zero start was lost.');
$unordered = $renderer->render($list->unordered());
$check(str_starts_with($unordered, '<ul') && !str_contains($unordered, 'start=') && !str_contains($unordered, ' reversed='), 'Switching type left ordered attributes.');
$check(str_contains($renderer->render($list->ordered()), 'reversed'), 'Ordered options were not preserved.');
foreach ([
    fn () => IList(['nested' => 'bad']),
    fn () => IList(StateRef::to('bad', 'not an array')),
    fn () => IList(new stdClass()),
    fn () => IList()->type('menu'),
    fn () => IList()->marker('url(https://example.com)'),
    fn () => IList()->markerPosition('middle'),
    fn () => IList()->key('__proto__'),
    fn () => IList()->label('constructor.value'),
    fn () => $renderer->render(IList([['id' => 'x', 'label' => 'A'], ['id' => 'x', 'label' => 'B']])),
    fn () => $renderer->render(IList([['id' => [], 'label' => 'A']])),
    fn () => $renderer->render(IList([['label' => []]])),
    fn () => $renderer->render(IList(['A'])->renderItem(fn () => ListItem('Not content'))),
] as $invalid) ViewTest::assertThrows($invalid, InvalidArgumentException::class);
ViewTest::assertThrows(fn () => $renderer->render(IList('A')->sortable()), LogicException::class);
ViewTest::assertThrows(fn () => $renderer->render(IList(['A'])->renderItem(fn () => 'Not a View')), UnexpectedValueException::class);
$custom = $renderer->render(IList(['A'])->renderItem(fn (CollectionItem $item): View => $item->text('value')));
$check(str_contains($custom, '>A</span>') && !str_contains($custom, '<template'), 'Static custom rendering failed.');
$check($html === $renderer->render(new ListsPage()), 'List rendering leaked across page instances.');
echo "IList: native semantics, markers, State bindings, templates, empty content and invalid configurations passed.\n";
