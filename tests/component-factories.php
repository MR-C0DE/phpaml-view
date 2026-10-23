<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use AML\View\{FileApplication, Testing\ViewTest, Testing\TestView};
use AML\View\Tests\Factories\Components as C;
use function AML\View\Tests\Factories\Components\{Values, Child, ParentType, Intersection, Dnf, Tail, Reference, ObjectDefaults};

$source = __DIR__ . '/fixtures/factories';
$app = new FileApplication($source, namespace: 'AML\\View\\Tests\\Factories');
$check = static function (bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); };
$check($app->paths() === ['/'], 'Discovery failed');
$check(C\Counted::$calls === 0, 'Discovery evaluated an object default');
$html = $app->mount('/')->rootHtml();
$check(str_contains($html, 'André') && str_contains($html, 'handwritten'), 'Automatic/manual factories failed');
$check(C\Counted::$calls === 1, 'Reactive binding replayed an object default');
$check((new FileApplication($source . '/.', namespace: 'AML\\View\\Tests\\Factories'))->mount('/')->rootHtml() === $html, 'Repeated discovery redefined functions');
$check(C\Counted::$calls === 2, 'Repeated rendering replayed an object default');
$test = new TestView($html);
$test->click('Change')->assertState('label', 'Alice')->assertState('numbers', [10, 20, 30]);
$check(str_contains($test->html(), 'data-test="auto-label">Alice</span>') && str_contains($test->html(), 'data-test="auto-total">60</span>') && !$test->renderErrors(), 'Generated forwarding/variadics lost State bindings');
foreach (['AbstractCard', 'PrivateCard', 'APage', 'ALayout', 'Utility', 'Mode', 'Both', 'EmptyAlias'] as $name) {
    $check(!function_exists('AML\\View\\Tests\\Factories\\Components\\' . $name), 'Generated a non-component factory: ' . $name);
}
$factory = new ReflectionFunction(C\Values::class);
$constructor = new ReflectionMethod(C\Values::class, '__construct');
foreach ($constructor->getParameters() as $i => $parameter) {
    $actual = $factory->getParameters()[$i];
    $check($actual->getName() === $parameter->getName() && (string) $actual->getType() === (string) $parameter->getType()
        && $actual->getDefaultValue() === $parameter->getDefaultValue(), 'Signature mismatch: ' . $parameter->getName());
}
$check((string) $factory->getReturnType() === C\Values::class, 'Factory return type missing');
$value = Values(count: 5, label: 'Alice', union: 2);
$check($value->count === 5 && $value->label === 'Alice' && $value->union === 2 && $value->amount === 12.5, 'Named/default arguments changed');
$check(Values(...['label' => 'Spread', 'active' => false])->label === 'Spread', 'Named spread failed');
ViewTest::assertThrows(fn () => Values(count: 'bad'), TypeError::class);
ViewTest::assertThrows(fn () => Values(unknown: true), Error::class);
$parent = new C\Inherited();
$child = Child($parent);
$check($child instanceof C\Child && $child->previous === $parent && $child->label === 'hérité', 'Inherited self/default scope failed');
$check(ParentType($parent)->owner === $parent, 'Parent type failed');
$both = new C\Both();
$check(Intersection($both)->value === $both && Dnf($both)->value === $both && Dnf()->value === null, 'Intersection/DNF types failed');
ViewTest::assertThrows(fn () => Intersection(new stdClass()), TypeError::class);
$check(Tail('one', 1, 2)->values === [1, 2] && Tail(last: 9)->values === ['last' => 9], 'Variadic keys changed');
$first = 'a'; $second = 'b'; $third = 'c'; Reference($first, $second, last: $third);
$check([$first, $second, $third] === ['a!', 'b!', 'c!'], 'References were lost');
$objects = ObjectDefaults();
$check(C\Counted::$calls === 4 && $objects->value->label === 'object' && $objects->nested[0]->label === $source . '/components'
    && $objects->options['mode'] === C\Mode::Compact, 'Object/default lexical context failed');
$another = ObjectDefaults();
$check(C\Counted::$calls === 6 && $another->value !== $objects->value, 'Object defaults are shared or replayed');
echo "Component factories: signatures, inheritance, defaults, references, discovery and manual compatibility passed.\n";
