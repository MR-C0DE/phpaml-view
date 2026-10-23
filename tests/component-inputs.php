<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
if (in_array('--automatic', $argv, true)) {
    // Exercise the same end-to-end contracts with only the class declarations.
    $root = sys_get_temp_dir() . '/aml-auto-inputs-' . bin2hex(random_bytes(8));
    mkdir($root . '/components', 0700, true);
    mkdir($root . '/pages/home', 0700, true);
    register_shutdown_function(static function () use ($root): void {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            if ($file->isDir()) rmdir($file->getPathname()); else unlink($file->getPathname());
        }
        rmdir($root);
    });
    $source = file_get_contents(__DIR__ . '/fixtures/ComponentInputs.php');
    $source = preg_replace('/^function (Carte|Apercu)\([^\n]+\n/m', '', $source, -1, $removed);
    if ($removed !== 2) throw new RuntimeException('Automatic input fixture must remove exactly two factories');
    file_put_contents($root . '/components/Inputs.php', $source);
    file_put_contents($root . '/pages/home/page.php', '<?php class_alias(\\AML\\View\\Tests\\Inputs\\InputsPage::class, "AutoInputs\\Pages\\Home\\HomePage");');
    $application = new AML\View\FileApplication($root, namespace: 'AutoInputs');
    $application->paths();
} else {
    require __DIR__ . '/fixtures/ComponentInputs.php';
}

use AML\View\{Renderer, Testing\TestView, Testing\ViewTest};
use AML\View\Tests\Inputs\InputsPage;

$html = (new Renderer())->render(new InputsPage());
if (in_array('--json', $argv, true)) { echo json_encode(['html' => $html], JSON_THROW_ON_ERROR); exit; }
$test = new TestView($html);
$check = static function (string $id, string $expected) use ($test): void {
    $doc = new DOMDocument(); @$doc->loadHTML('<meta charset="UTF-8">' . $test->html());
    $actual = (new DOMXPath($doc))->query('//*[@data-test="' . $id . '"]')->item(0)?->textContent;
    if ($actual !== $expected) throw new RuntimeException($id . ': ' . json_encode([$expected, $actual]));
};
$test->click('card-set')->assertState('a', 'Modifié')->assertState('other', 'André');
$check('card-preview', 'Modifié'); $check('card-clicks', '1'); $check('sibling-clicks', '0');
$test->click('card-append')->assertState('a', 'Modifié!');
$test->click('card-method')->assertState('a', 'Méthode');
$test->click('card-reference')->assertState('a', 'Référence');
$test->click('card-preview-set')->assertState('a', 'Petit-enfant'); $check('card', 'Petit-enfant');
$test->click('Parent')->assertState('a', 'Parent'); $check('card-preview', 'Parent');
$test->fill('card-input', 'Formulaire')->assertState('a', 'Formulaire'); $check('card-preview', 'Formulaire');
$test->click('host-set')->assertState('a', 'Formulaire'); $check('host-preview', 'Modifié');
$test->click('sibling-set')->assertState('a', 'Formulaire')->assertState('other', 'Modifié');
$check('literal-preview', 'Bonjour'); $check('default-preview', '');
$test->click('literal-set')->assertState('a', 'Formulaire'); $check('literal-preview', 'Modifié'); $check('default-preview', '');
$test->click('default-preview-set'); $check('default', 'Petit-enfant'); $check('literal', 'Modifié');
$test->fill('literal-input', 'Local'); $check('literal-preview', 'Local');
$test->click('Data')->assertState('data.count', 2)->assertState('data.items', ['new'])->assertState('price', 2.0)->assertState('optional', 'fallback');
$check('price-type', 'double');
$test->click('Array reference')->assertState('data.items', ['new', 'builtin']);
ViewTest::assertThrows(fn () => $test->click('typed-invalid'), TypeError::class, 'Carte::$a');
$test->assertState('typed', 'Typé');
$check('typed-clicks', '0');
ViewTest::assertThrows(fn () => $test->click('Readonly'), Error::class, 'readonly');
$test->assertState('a', 'Formulaire');
if ($test->renderErrors()) throw new RuntimeException('Unexpected render errors');
$instance = new AML\View\Tests\Inputs\Carte();
$renderer = new Renderer();
if ($renderer->render($instance) !== $renderer->render($instance)) throw new RuntimeException('Local input bindings leaked across renders');
ViewTest::assertThrows(fn () => $renderer->render(new AML\View\Tests\Inputs\CalculatedPage()), InvalidArgumentException::class, 'calculated inputs are read-only');
echo "Component inputs: parent writes, forwarding, namespaces, types, readonly and forms passed.\n";
