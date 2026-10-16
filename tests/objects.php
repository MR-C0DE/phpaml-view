<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/fixtures/ObjectServices.php';
require __DIR__ . '/fixtures/ObjectsPage.php';
use AML\View\Tests\Objects\ObjectsPage;
use AML\View\{Renderer, Testing\TestView};

$cases = [];
$scenarios = array_map(static fn ($name) => [$name, 0], ['oneLine','plainNamedError','plainSpreadError','plainPositional','constants','evalSelf','factoryFirst','floatKinds','arrays','methodArguments','methodError','staticReturnError','guarded','privateConstructor','constructorOutput','evalLiteral','evalDynamic','evalBound','evalNew','evalStatic','basic','named','spread','identity','equalObjects','properties','inherited','chaining','captures','callback','references','valueParameter','cloned','nullable','initialization','plain','abstractConcrete','staticFactory','badPropertyType','badParameter','privateProperty','protectedProperty','privateMethod','missingMethod','uninitialized','readonlyWrite','readonlyUnset','abstractNew','caught']);
foreach ($scenarios as [$method, $input]) {
    $page = new ObjectsPage(); $page->handler = $method;
    ob_start();
    try { $html = (new Renderer())->render($page); } finally { $renderOutput = ob_get_clean(); }
    if ($renderOutput !== '' || $page->counter !== 0) throw new RuntimeException('Compilation executed application code');
    $attribute = static function (string $name) use ($html): array {
        preg_match('/' . $name . '="([^"]+)"/', $html, $match);
        return json_decode(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR);
    };
    $initial = $attribute('data-aml-state'); $config = $attribute('data-aml-state-config'); $action = $attribute('data-aml-client-click');
    $warnings = []; $error = null;
    set_error_handler(static function ($severity, $message) use (&$warnings): bool { $warnings[] = $message; return true; });
    ob_start();
    try { $page->$method(); }
    catch (Throwable $failure) { $error = ['name' => $failure::class, 'line' => $failure->getLine()]; }
    finally { $output = ob_get_clean(); restore_error_handler(); }
    $expected = []; foreach (array_keys($initial) as $name) $expected[$name] = $page->$name;
    $test = new TestView($html);
    set_error_handler(static fn (): bool => true);
    try {
        try { $test->click('Run'); $failed = false; }
        catch (Throwable $failure) { $failed = true; }
        if ($failed && !$error) throw $failure;
        if ($failed !== ($error !== null)) throw new RuntimeException("ViewTest {$method}: " . ($failure->getMessage() ?? 'unexpected success'));
        foreach (($error ? $initial : $expected) as $name => $value) $test->assertState($name, $value);
        if (!$error && implode('', $test->consoleOutput()) !== $output) throw new RuntimeException("ViewTest {$method}: echo mismatch");
    } finally { restore_error_handler(); }
    $cases[] = compact('method', 'initial', 'config', 'action', 'expected', 'warnings', 'error', 'output');
}
$page = new ObjectsPage(); $page->handler = 'invalidState';
$html = (new Renderer())->render($page); $test = new TestView($html);
try { $test->click('Run'); throw new RuntimeException('Object State was accepted'); }
catch (TypeError $failure) { $test->assertState('counter', 0); $test->assertState('result', []); }
$attribute = static function (string $name) use ($html): array { preg_match('/' . $name . '="([^"]+)"/', $html, $match); return json_decode(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR); };
$cases[] = ['method' => 'invalidState', 'initial' => $attribute('data-aml-state'), 'config' => $attribute('data-aml-state-config'), 'action' => $attribute('data-aml-client-click'), 'invalidState' => true];
$autoloads = [];
$spy = static function (string $name) use (&$autoloads): void { if (str_contains($name, 'NeverLoadedDependency')) $autoloads[] = $name; };
spl_autoload_register($spy);
try {
    foreach (['methodDefault' => 'cannot instantiate objects', 'missingClass' => 'Unknown or uncompiled', 'lazyDefault' => 'cannot autoload'] as $method => $message) {
        $page = new ObjectsPage(); $page->handler = $method;
        try { (new Renderer())->render($page); throw new RuntimeException('Unsupported class was accepted: ' . $method); }
        catch (InvalidArgumentException $error) { if (!str_contains($error->getMessage(), $message)) throw $error; }
    }
} finally { spl_autoload_unregister($spy); }
if (\AML\View\Tests\Objects\DefaultProbe::$calls !== 0) throw new RuntimeException('Reflection executed a default constructor.');
if ($autoloads !== []) throw new RuntimeException('Frontend inspection executed application autoload code.');
require __DIR__ . '/../examples/objets/page.php';
$example = new TestView((new Renderer())->render(new \Exemples\Objets\Pages\ObjetsPage()));
$example->click('Calculer')->assertState('message', '2 lignes — 32,50 €');
$example->click('Ajouter un café')->click('Calculer')->assertState('message', '2 lignes — 43,75 €');
if (in_array('--json', $argv, true)) echo json_encode($cases, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE);
else echo 'Objects: ' . count($cases) . " PHP scenarios passed.\n";
