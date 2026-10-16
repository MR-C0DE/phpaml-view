<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures/ReactiveBindings.php';
use AML\View\{Renderer, Testing\TestView};
$html = (new Renderer())->render(new AML\View\Tests\Bindings\BindingsPage());
$before = ['compound'=>'Bonjour à vous André','branch'=>'André actif!!','snapshot'=>'André actif','loop'=>'6','sort'=>'1,2,3','spread'=>'Préfixe André / 2 fin','factory'=>'Dynamique André','helper'=>'TITRE : André','nested-helper'=>'IMBRIQUé : André actif!!','named-helper'=>'NOM : André','division'=>'5'];
$after = ['compound'=>'Bonjour à vous <b>Marie</b>','branch'=>'<b>Marie</b> inactif!!!','snapshot'=>'<b>Marie</b> inactif','loop'=>'10','sort'=>'2,8','spread'=>'Préfixe <b>Marie</b> / 3 fin','factory'=>'Dynamique <b>Marie</b>','helper'=>'TITRE : <b>Marie</b>','nested-helper'=>'IMBRIQUé : <b>Marie</b> inactif!!!','named-helper'=>'NOM : <b>Marie</b>','division'=>'5'];
$before += ['array-helper'=>'3 / 6','hash'=>hash('sha256','André'),'validation'=>'non'];
$after += ['array-helper'=>'2 / 10','hash'=>hash('sha256','<b>Marie</b>'),'validation'=>'non'];
$before += ['switch-while'=>'deux / 2','caught'=>'5'];
$after += ['switch-while'=>'autre / 3','caught'=>'5'];
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
$test->assertState('numbers', [3,1,2]);
$test->click('Changer'); $test->assertState('numbers', [8,2]); $check($test->html(), $after);
$test->click('Erreur')->assertState('divisor', 0);
$check($test->html(), [...$after, 'caught'=>'impossible']);
$errors = $test->renderErrors();
if (count($errors) !== 1 || !$errors[0]['error'] instanceof DivisionByZeroError || !str_contains($errors[0]['source']['file'] ?? '', 'ReactiveBindings.php')) throw new RuntimeException('Missing located display diagnostic');
$test->click('Réparer')->assertState('divisor', 5);
$check($test->html(), [...$after, 'division'=>'2','caught'=>'2']);
if (count($test->renderErrors()) !== 1) throw new RuntimeException('Unexpected render error after recovery');
$ambiguous = new class extends \AML\View\Page {
    #[\AML\View\State] public string $message = 'André';
    public function body(): \AML\View\View {
        $label = $this->message;
        return \AML\View\VStack(\AML\View\Tests\Bindings\caption($label), \AML\View\Tests\Bindings\caption('fixe'));
    }
};
try {
    (new Renderer())->render($ambiguous);
    throw new RuntimeException('Ambiguous helper dependencies were silently frozen');
} catch (InvalidArgumentException $error) {
    if (!str_contains($error->getMessage(), 'Ambiguous reactive helper') || !str_contains($error->getMessage(), '.php:')) throw $error;
}
echo 'Reactive bindings: '.(count($before)*3).' SSR/ViewTest comparisons passed.'.PHP_EOL;
