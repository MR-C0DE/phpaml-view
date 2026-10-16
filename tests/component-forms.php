<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures/ComponentForms.php';
use AML\View\{Renderer, Testing\TestView};
$html = (new Renderer())->render(new AML\View\Tests\Forms\FormsPage());
if (in_array('--json', $argv, true)) { echo json_encode(['html'=>$html], JSON_THROW_ON_ERROR); exit; }
$test = new TestView($html);
$check = static function (string $id, string $expected) use ($test): void {
    $doc = new DOMDocument(); @$doc->loadHTML('<meta charset="UTF-8">' . $test->html());
    $actual = (new DOMXPath($doc))->query('//*[@data-test="' . $id . '"]')->item(0)?->textContent;
    if ($actual !== $expected) throw new RuntimeException($id . ': ' . json_encode([$expected,$actual,$test->renderErrors()]));
};
$check('complex', 'Bonjour ANDRé!'); $check('hooked', '[André]');
$test->fill('a-name', 'Alice')->assertState('name', 'Alice')->assertState('other', 'Voisin');
$check('inherited-private', 'Alice'); $check('constructed-number', '5'); $check('nested-function', '5'); $check('raw-helper', 'Alice'); $check('raw', 'Alice'); $check('raw-alias', 'Alice'); $check('direct-return', 'Alice');
$check('a-label', 'Alice'); $check('b-label', 'Voisin'); $check('complex', 'Bonjour ALICE!'); $check('hooked', '[Alice]');
$test->fill('named-field', 'Tableau changé')->assertState('fields.value', 'Tableau changé');
$test->fill('spread-field', 'Spread')->assertState('name', 'Spread');
$test->fill('nested-field', 'Alice')->assertState('name', 'Alice');
$test->fill('a-note', 'Bonjour')->assertState('note', 'Bonjour');
$test->fill('a-quantity', 9)->assertState('quantity', 9);
$test->fill('a-enabled', true)->assertState('enabled', true);
$test->fill('a-choice', 'b')->assertState('choice', 'b');
$test->fill('a-city', 'Laval')->assertState('profile.city', 'Laval')->assertState('profile.keep', 'intact');
$test->fill('a-local', 'personnel')->assertState('name', 'Alice')->assertState('other', 'Voisin');
$test->click('Parent')->assertState('name', 'Marie'); $check('a-label', 'Marie'); $check('complex', 'Bonjour MARIE!');
$test->click('Vide'); $check('complex', 'vide'); $check('hooked', '[]');
if ($test->renderErrors()) throw new RuntimeException('Unexpected render errors');
$test->fill('a-name', 'bad'); $check('constructed-number', '0');
if (count($test->renderErrors()) !== 1 || !$test->renderErrors()[0]['error'] instanceof TypeError) throw new RuntimeException('Missing constructor type error');
$test->fill('a-name', 'Repaired'); $check('constructed-number', '8'); $check('nested-function', '8');
echo "Component forms: parent writes, sibling isolation, hooks and constructor control flow passed.\n";

$calculated = new class extends \AML\View\Page {
    #[\AML\View\State] public string $name = 'André';
    public function body(): \AML\View\View { return \AML\View\TextField('calculated', strtoupper($this->name)); }
};
\AML\View\Testing\ViewTest::assertThrows(fn () => (new Renderer())->render($calculated), InvalidArgumentException::class, 'calculated values are read-only');

$staticField = new class extends \AML\View\Page {
    #[\AML\View\State] public string $name = 'André';
    private function fields(string $name): \AML\View\View { return \AML\View\VStack(\AML\View\Text($name), \AML\View\TextField('constant', 'fixe')); }
    public function body(): \AML\View\View { return $this->fields($this->name); }
};
$staticHtml = (new Renderer())->render($staticField);
if (str_contains($staticHtml, 'data-aml-model=')) throw new RuntimeException('Static field acquired an unrelated state binding');
echo "Component forms: calculated input diagnostic and static helper field passed.\n";
