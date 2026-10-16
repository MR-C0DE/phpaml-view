<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures/ReactiveComposition.php';
use AML\View\{Renderer, Testing\TestView};
AML\View\Tests\Composition\Decorated::$constructions = 0;
$html = (new Renderer())->render(new AML\View\Tests\Composition\CompositionPage());
$before = ['variadic'=>'André: 0,1 / 2 / 5', 'named-variadic'=>'André: first,last / 2 / 17', 'spread-keys'=>'fixe: first / 1 / 4', 'unpack-local'=>'André ?', 'forward'=>'André!', 'child'=>'André', 'child-numbers'=>'2/5', 'static'=>'André', 'static-numbers'=>'0/0', 'decorated'=>'Bonjour André!', 'decorated-numbers'=>'0/0', 'counter-a'=>'André: 1', 'counter-b'=>'André: 1'];
$after = [...$before, 'variadic'=>'<b>Marie</b>: 0,1,2 / 3 / 60', 'named-variadic'=>'<b>Marie</b>: first,last / 2 / 17', 'spread-keys'=>'fixe: left,right / 2 / 11', 'unpack-local'=>'<b>Marie</b> ?', 'forward'=>'Alice!!', 'child'=>'<b>Marie</b>', 'child-numbers'=>'3/60', 'decorated'=>'Bonjour <b>Marie</b>!', 'counter-a'=>'<b>Marie</b>: 1', 'counter-b'=>'<b>Marie</b>: 1'];
$before += ['template-option'=>'André','mixed-input'=>'André','explicit-input'=>'André','slot'=>'Fixe','custom'=>'André','custom-numbers'=>'0/0','interpolated-child'=>'Salut André','typed-child'=>'3', 'template-child'=>'André / 2', 'variadic-child'=>'2/5', 'callable-helper'=>'André ?', 'counter-a-observed'=>'', 'counter-b-observed'=>''];
$after += ['template-option'=>'<b>Marie</b>','mixed-input'=>'<b>Marie</b>','explicit-input'=>'<b>Marie</b>','slot'=>'Fixe','custom'=>'<b>Marie</b>','custom-numbers'=>'0/0','interpolated-child'=>'Salut <b>Marie</b>','typed-child'=>'3', 'template-child'=>'<b>Marie</b> / 10', 'variadic-child'=>'3/60', 'callable-helper'=>'<b>Marie</b> ?', 'counter-a-observed'=>'', 'counter-b-observed'=>''];
if (in_array('--json', $argv, true)) {echo json_encode(compact('html','before','after'), JSON_THROW_ON_ERROR); exit;}
$check = static function (string $html, array $expected): void {
    $document = new DOMDocument(); @$document->loadHTML('<meta charset="UTF-8">'.$html);
    $xpath = new DOMXPath($document);
    foreach ($expected as $id=>$value) {
        $actual = $xpath->query('//*[@data-test="'.$id.'"]')->item(0)?->textContent;
        if ($actual !== $value) throw new RuntimeException($id.': '.json_encode([$value,$actual]));
    }
};
$check($html, $before);
$test = new TestView($html); $check($test->html(), $before);
$test->click('Changer'); $check($test->html(), $after);
$test->click('Compter counter-a'); $check($test->html(), [...$after, 'counter-a'=>'<b>Marie</b>: 2']);
$test->click('Lire counter-a'); $check($test->html(), [...$after, 'counter-a'=>'<b>Marie</b>: 2', 'counter-a-observed'=>'Lu <b>Marie</b>']);
if ($test->renderErrors()) throw new RuntimeException('Unexpected render errors');
$test->click('Type invalide')->assertState('typed', 'invalide');
$check($test->html(), [...$after, 'counter-a'=>'<b>Marie</b>: 2', 'counter-a-observed'=>'Lu <b>Marie</b>']);
if (count($test->renderErrors()) !== 1 || !$test->renderErrors()[0]['error'] instanceof TypeError) throw new RuntimeException('Missing child property type diagnostic');
$test->click('Réparer type');
$check($test->html(), [...$after, 'counter-a'=>'<b>Marie</b>: 2', 'counter-a-observed'=>'Lu <b>Marie</b>', 'typed-child'=>'6']);
$test->click('Vider extras');
$check($test->html(), [...$after, 'counter-a'=>'<b>Marie</b>: 2', 'counter-a-observed'=>'Lu <b>Marie</b>', 'typed-child'=>'6', 'spread-keys'=>'fixe:  / 0 / 0']);
if (AML\View\Tests\Composition\Decorated::$constructions !== 1) throw new RuntimeException('Component constructor was replayed');
echo 'Reactive composition: '.(count($before)*8).' SSR/ViewTest comparisons passed.'.PHP_EOL;

$referencePage = new class extends \AML\View\Page {
    #[\AML\View\State] public string $message = 'André';
    private function caption(string &$message): \AML\View\View { return \AML\View\Text($message); }
    public function body(): \AML\View\View { return $this->caption($this->message); }
};
\AML\View\Testing\ViewTest::assertThrows(fn () => (new Renderer())->render($referencePage), InvalidArgumentException::class, 'cannot be passed by reference');
$unpackedClassPage = new class extends \AML\View\Page {
    #[\AML\View\State] public string $message = 'André';
    public function body(): \AML\View\View {
        $arguments = [\AML\View\Tests\Composition\Caption::class, $this->message, 'invalid'];
        return \AML\View\Component(...$arguments);
    }
};
(new TestView((new Renderer())->render($unpackedClassPage)))->assertSee('André');
$writePage = new class extends \AML\View\Page {
    #[\AML\View\State] public string $message = 'André';
    public function body(): \AML\View\View { return \AML\View\Component(\AML\View\Tests\Composition\InvalidInputWrite::class, $this->message); }
};
\AML\View\Testing\ViewTest::assertThrows(fn () => (new Renderer())->render($writePage), InvalidArgumentException::class);
echo 'Reactive composition: class unpacking and explicit diagnostics for references and input writes passed.'.PHP_EOL;
foreach ([\AML\View\Tests\Composition\EarlyReturnInput::class, \AML\View\Tests\Composition\MethodInput::class] as $class) {
    $supported = new class($class) extends \AML\View\Page {
        #[\AML\View\State] public string $message = 'André';
        public function __construct(private string $factory) {}
        public function body(): \AML\View\View {
            return \AML\View\VStack(
                \AML\View\Component($this->factory, $this->message),
                \AML\View\Button('Remplacer')->onClick(function () { $this->message = 'Alice'; }),
            );
        }
    };
    $test = new TestView((new Renderer())->render($supported));
    $test->click('Remplacer');
    if ($test->renderErrors() || !str_contains($test->html(), $class === \AML\View\Tests\Composition\MethodInput::class ? 'ALICE' : 'Alice')) throw new RuntimeException('Constructor projection did not update');
}
echo 'Reactive composition: early returns and constructor helpers update correctly.'.PHP_EOL;
