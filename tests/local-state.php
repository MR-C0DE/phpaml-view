<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures/LocalState.php';

use AML\View\{Page, Renderer, View, Testing\TestView, Testing\ViewTest};
use AML\View\Tests\LocalState\LocalPage;
use function AML\View\{State, Text};

$renderer = new Renderer();
$html = $renderer->render(new LocalPage());
if (in_array('--json', $argv, true)) { echo json_encode(['html' => $html], JSON_THROW_ON_ERROR); exit; }
$test = new TestView($html);
$test->click('Add')->click('Add')->click('Double')->click('Subtract')->assertState('__locals.state1', 3);
ViewTest::assertThrows(fn () => $test->click('Invalid count'), TypeError::class, 'State(');
$test->assertState('__locals.state1', 3);
$test->click('Append')->assertState('__locals.state2', 'André!');
$test->click('Child')->assertState('__locals.state2', 'Shared');
$test->fill('child-message', 'Edited')->assertState('__locals.state2', 'Edited');
$test->click('Toggle')->assertState('__locals.state3', true);
$test->fill('enabled', false)->assertState('__locals.state3', false);
$test->click('Resize')->assertState('__locals.state4', 24.0);
$test->fill('optional', 42)->assertState('__locals.state5', 42);
$test->click('Clear')->assertState('__locals.state5', null);
$test->click('Union')->assertState('__locals.state6', 'text');
ViewTest::assertThrows(fn () => $test->click('Invalid union'), TypeError::class);
$test->click('Push')->assertState('__locals.state7', ['first', 'second']);
ViewTest::assertThrows(fn () => $test->click('Invalid array'), TypeError::class);
$test->click('Property')->assertState('property', 4);
$left = 'components.AML.View.Tests.LocalState.Counter.i1.__locals.state1';
$right = 'components.AML.View.Tests.LocalState.Counter.i2.__locals.state1';
$test->click('left+')->click('left+')->click('right+')->click('left-')->assertState($left, 1)->assertState($right, 1);
if ($test->renderErrors()) throw new RuntimeException('Unexpected render errors.');
if ($html !== $renderer->render(new LocalPage())) throw new RuntimeException('Local states leaked across renders.');

$renderValue = static function (mixed $initial, ?string $type = null) use ($renderer): string {
    return $renderer->render(new class($initial, $type) extends Page {
        public function __construct(private mixed $initial, private ?string $type) {}
        public function body(): View { return Text(State($this->initial, $this->type)); }
    });
};
foreach ([[0, null], [1.5, null], [true, null], ['text', null], [null, null], [null, '?string'], [1, 'float'], [false, 'false'], [null, 'mixed'], [1.5, 'int|float'], [null, 'string|null']] as [$value, $type]) $renderValue($value, $type);
foreach (['', 'integer', 'object', '?mixed', '?null', '?int|string', 'int|int', 'mixed|string', 'bool|true', 'true|false', 'array<string>'] as $type) ViewTest::assertThrows(fn () => $renderValue(null, $type), InvalidArgumentException::class, 'State() type');
foreach ([[1, 'string'], ['1', 'int'], [null, 'int'], [true, 'false'], [1.5, 'int'], [false, 'int|string']] as [$value, $type]) ViewTest::assertThrows(fn () => $renderValue($value, $type), TypeError::class, 'Cannot initialize');
foreach ([new stdClass(), ['nested' => new stdClass()]] as $value) ViewTest::assertThrows(fn () => $renderValue($value, 'mixed'), InvalidArgumentException::class, 'scalars');
foreach ([INF, NAN, PHP_INT_MAX] as $value) ViewTest::assertThrows(fn () => $renderValue($value), RangeException::class);
ViewTest::assertThrows(fn () => State(0), LogicException::class, 'component body');
if (AML\View\InlineStateRegistry::currentTypeRules() !== []) throw new RuntimeException('Type scope was not cleaned after a failed render.');
if ($html !== $renderer->render(new LocalPage())) throw new RuntimeException('Failed renders leaked local state.');
echo "Local State: types, updates, forms, child forwarding, instance isolation and render cleanup passed.\n";
